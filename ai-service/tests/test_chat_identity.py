"""Security tests: signed user identity + order-lookup access control.

Covers the IDOR fix — /chat/send must not trust a body-supplied user_id, and
order_status must only ever return the authenticated caller's own orders.
"""
import hashlib
import hmac
import time

from fastapi.testclient import TestClient

from app.main import app
from app import deps
from app.api import chat as chat_api
from app.config import get_settings
from app.db.orders_repo import lookup_orders
from app.engines.multiagent.action import action_node
from app.models.chat import EngineReply


def _user_header(user_id: int, ts: int | None = None) -> dict:
    ts = int(time.time()) if ts is None else ts
    secret = get_settings().internal_api_secret
    sig = hmac.new(
        secret.encode(), f"user:{ts}:{user_id}".encode(), hashlib.sha256
    ).hexdigest()
    return {"X-User-Identity": f"{ts}:{user_id}:{sig}"}


class _RecordingEngine:
    """Captures the context handed to handle() so tests can inspect user_id."""

    def __init__(self):
        self.deps = type("D", (), {"conn_factory": staticmethod(lambda: None)})()
        self.captured = {}

    def handle(self, history, user_message, context):
        self.captured = dict(context)
        return EngineReply(type="text", content="ok", intent="unknown", confidence=1.0)


# --- _verify_user_identity ---------------------------------------------------

def test_verify_user_identity_accepts_fresh_signature():
    header = _user_header(7)["X-User-Identity"]
    assert chat_api._verify_user_identity(header) == 7


def test_verify_user_identity_rejects_stale_timestamp():
    stale = int(time.time()) - (chat_api.ADMIN_BYPASS_MAX_AGE + 5)
    header = _user_header(7, ts=stale)["X-User-Identity"]
    assert chat_api._verify_user_identity(header) is None


def test_verify_user_identity_rejects_forged_signature():
    ts = int(time.time())
    assert chat_api._verify_user_identity(f"{ts}:7:deadbeef") is None


def test_verify_user_identity_rejects_missing_header():
    assert chat_api._verify_user_identity(None) is None


# --- /chat/send trust boundary ----------------------------------------------

def test_chat_send_ignores_body_user_id_without_header():
    eng = _RecordingEngine()
    app.dependency_overrides[deps.get_engine] = lambda: eng
    try:
        client = TestClient(app)
        r = client.post("/chat/send", json={"message": "hi", "user_id": 999})
        assert r.status_code == 200
        assert eng.captured["user_id"] is None  # spoofed body id must be dropped
    finally:
        app.dependency_overrides.clear()


def test_chat_send_uses_signed_identity():
    eng = _RecordingEngine()
    app.dependency_overrides[deps.get_engine] = lambda: eng
    try:
        client = TestClient(app)
        r = client.post(
            "/chat/send", json={"message": "hi"}, headers=_user_header(42)
        )
        assert r.status_code == 200
        assert eng.captured["user_id"] == 42
    finally:
        app.dependency_overrides.clear()


def test_chat_send_rejects_forged_identity_as_guest():
    eng = _RecordingEngine()
    app.dependency_overrides[deps.get_engine] = lambda: eng
    try:
        client = TestClient(app)
        ts = int(time.time())
        r = client.post(
            "/chat/send",
            json={"message": "hi", "user_id": 5},
            headers={"X-User-Identity": f"{ts}:5:deadbeef"},
        )
        assert r.status_code == 200
        assert eng.captured["user_id"] is None
    finally:
        app.dependency_overrides.clear()


# --- order lookup access control --------------------------------------------

def test_lookup_orders_requires_user_id():
    # Phone-only / order-id-only lookups must return nothing (no DB touched).
    assert lookup_orders(None, phone="0900000000") == []
    assert lookup_orders(None, order_id=1) == []


def test_order_status_requires_login():
    deps_stub = type("D", (), {"conn_factory": staticmethod(lambda: None)})()
    state = {
        "intent": "order_status",
        "query": "cho mình xem đơn hàng số 0912345678",
        "context": {},  # no user_id -> guest
    }
    result = action_node(deps_stub, state)
    assert "đăng nhập" in result["response"].lower()
