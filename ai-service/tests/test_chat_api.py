import hashlib
import hmac
import time

from fastapi.testclient import TestClient
from app.main import app
from app import deps
from app.engines.baseline import BaselineEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings


def _admin_header() -> dict:
    ts = int(time.time())
    secret = get_settings().internal_api_secret
    sig = hmac.new(secret.encode(), f"admin:{ts}".encode(), hashlib.sha256).hexdigest()
    return {"X-Admin-Bypass": f"{ts}:{sig}"}


def test_chat_send_returns_reply(fake_store, monkeypatch):
    fake_store.add("faq", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    d = EngineDeps(llm=FakeLLM(['{"answer": "Trong ngày", "confidence": 0.9, "sources": []}']),
                   store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    r = client.post("/chat/send", json={"message": "ship bao lâu", "guest_token": "g1"})
    assert r.status_code == 200
    body = r.json()
    assert body["reply"]["content"] == "Trong ngày"
    app.dependency_overrides.clear()


def test_chat_send_without_db_returns_session_id_zero(fake_store):
    d = EngineDeps(llm=FakeLLM(['{"answer": "OK", "confidence": 0.9, "sources": []}']),
                   store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    r = client.post("/chat/send", json={"message": "hi", "guest_token": "g2"})
    assert r.status_code == 200
    body = r.json()
    assert body["session_id"] == 0
    assert body["handoff"] is False
    app.dependency_overrides.clear()


def test_chat_history_without_db_returns_empty_list(fake_store):
    d = EngineDeps(llm=FakeLLM([]), store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    r = client.get("/chat/history", params={"session_id": 1})
    assert r.status_code == 200
    body = r.json()
    assert body == {"session_id": 1, "messages": []}
    app.dependency_overrides.clear()


def test_knowledge_index_without_db_indexes_policies_and_faq(fake_store):
    d = EngineDeps(llm=FakeLLM([]), store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    r = client.post("/knowledge/index", params={"source": "faq"}, headers=_admin_header())
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "ok"
    assert isinstance(body["indexed_count"], int)
    app.dependency_overrides.clear()


def test_get_engine_returns_demo_engine_when_configured(fake_store, monkeypatch):
    from app.engines.demo import DemoEngine
    d = EngineDeps(llm=FakeLLM([]), store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    monkeypatch.setattr(deps, "build_deps", lambda: d)
    monkeypatch.setattr(get_settings(), "engine", "demo")
    eng = deps.get_engine()
    assert isinstance(eng, DemoEngine)
