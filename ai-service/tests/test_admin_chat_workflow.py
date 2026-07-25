import hashlib
import hmac
import time

import pytest
from fastapi.testclient import TestClient

from app import deps
from app.config import get_settings
from app.db import chat_repo
from app.engines.base import EngineDeps
from app.engines.baseline import BaselineEngine
from app.llm import FakeLLM
from app.main import app
from app.api.chat import _admin_transaction


def _admin_header() -> dict:
    timestamp = int(time.time())
    secret = get_settings().internal_api_secret
    signature = hmac.new(
        secret.encode(),
        f"admin:{timestamp}".encode(),
        hashlib.sha256,
    ).hexdigest()
    return {"X-Admin-Bypass": f"{timestamp}:{signature}"}


def _client_without_database(fake_store) -> TestClient:
    engine_deps = EngineDeps(
        llm=FakeLLM([]),
        store=fake_store,
        settings=get_settings(),
        conn_factory=lambda: None,
    )
    app.dependency_overrides[deps.get_engine] = (
        lambda: BaselineEngine(engine_deps)
    )
    return TestClient(app)


class TransactionConnection:
    def __init__(self):
        self.calls = []

    def begin(self):
        self.calls.append("begin")

    def commit(self):
        self.calls.append("commit")

    def rollback(self):
        self.calls.append("rollback")


def test_admin_transaction_commits_success():
    conn = TransactionConnection()

    with _admin_transaction(conn):
        conn.calls.append("work")

    assert conn.calls == ["begin", "work", "commit"]


def test_admin_transaction_rolls_back_failure():
    conn = TransactionConnection()

    with pytest.raises(RuntimeError, match="audit insert failed"):
        with _admin_transaction(conn):
            conn.calls.append("work")
            raise RuntimeError("audit insert failed")

    assert conn.calls == ["begin", "work", "rollback"]


def test_claim_session_is_idempotent_for_same_admin(monkeypatch):
    session = {
        "id": 9,
        "status": "in_progress",
        "assigned_admin_id": 3,
    }
    locking_reads = []

    def fake_get_session(_conn, _session_id, for_update=False):
        locking_reads.append(for_update)
        return session

    monkeypatch.setattr(chat_repo, "get_session", fake_get_session)

    assert chat_repo.claim_session(object(), 9, 3) == session
    assert locking_reads == [True]


def test_session_action_without_database_reports_migration_state(fake_store):
    client = _client_without_database(fake_store)

    response = client.post(
        "/admin/session-action",
        json={"session_id": 9, "admin_id": 3, "action": "claim"},
        headers=_admin_header(),
    )

    assert response.status_code == 200
    assert response.json() == {
        "session": None,
        "workflow_ready": False,
    }
    app.dependency_overrides.clear()


def test_session_action_rejects_unknown_transition(fake_store):
    client = _client_without_database(fake_store)

    response = client.post(
        "/admin/session-action",
        json={"session_id": 9, "admin_id": 3, "action": "delete"},
        headers=_admin_header(),
    )

    assert response.status_code == 422
    assert response.json()["detail"] == "invalid_session_action"
    app.dependency_overrides.clear()


def test_admin_reply_keeps_stable_no_database_contract(fake_store):
    client = _client_without_database(fake_store)

    response = client.post(
        "/admin/reply",
        json={"session_id": 9, "admin_id": 3, "content": "Đã nhận yêu cầu."},
        headers=_admin_header(),
    )

    assert response.status_code == 200
    assert response.json() == {
        "message_id": None,
        "session": None,
        "workflow_ready": False,
    }
    app.dependency_overrides.clear()


def test_admin_reply_rejects_whitespace_only_content(fake_store):
    client = _client_without_database(fake_store)

    response = client.post(
        "/admin/reply",
        json={"session_id": 9, "admin_id": 3, "content": "   "},
        headers=_admin_header(),
    )

    assert response.status_code == 422
    assert response.json()["detail"] == "content_required"
    app.dependency_overrides.clear()


def test_admin_sessions_rejects_unknown_filter(fake_store):
    client = _client_without_database(fake_store)

    response = client.get(
        "/admin/sessions",
        params={"view": "deleted", "admin_id": 3},
        headers=_admin_header(),
    )

    assert response.status_code == 422
    assert response.json()["detail"] == "invalid_session_view"
    app.dependency_overrides.clear()
