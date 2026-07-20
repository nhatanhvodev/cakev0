import pytest
from app.config import get_settings
from app.db import mysql, chat_repo


@pytest.fixture
def conn():
    try:
        c = mysql.get_conn(get_settings())
    except Exception:
        pytest.skip("MySQL not available")
    yield c
    c.close()


def test_session_message_roundtrip(conn):
    s = chat_repo.get_or_create_session(conn, guest_token="test-guest-1")
    mid = chat_repo.append_message(conn, s["id"], "customer", "xin chào")
    msgs = chat_repo.get_messages(conn, s["id"])
    assert any(m["id"] == mid and m["content"] == "xin chào" for m in msgs)
    chat_repo.update_session(conn, s["id"], metadata={"draft": {"step": "items"}})
    s2 = chat_repo.get_or_create_session(conn, session_id=s["id"])
    assert s2["id"] == s["id"]
