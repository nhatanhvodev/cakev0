import pytest
from app.config import get_settings
from app.db import mysql, chat_repo


class _PageCursor:
    def __init__(self, rows):
        self.rows = rows
        self.sql = ""
        self.params = ()

    def __enter__(self):
        return self

    def __exit__(self, *_args):
        return False

    def execute(self, sql, params=None):
        self.sql = " ".join(sql.split())
        self.params = params or ()

    def fetchall(self):
        return self.rows


class _PageConn:
    def __init__(self, rows):
        self.cursor_instance = _PageCursor(rows)

    def cursor(self):
        return self.cursor_instance


def _messages(*ids):
    return [{"id": value, "content": f"message-{value}"} for value in ids]


def test_latest_message_page_returns_last_window_in_chronological_order():
    conn = _PageConn(_messages(205, 204, 203, 202))

    page = chat_repo.get_message_page(conn, 7, limit=3)

    assert [message["id"] for message in page["messages"]] == [203, 204, 205]
    assert page["has_more"] is True
    assert "ORDER BY id DESC" in conn.cursor_instance.sql
    assert conn.cursor_instance.params == (7, 4)


def test_before_message_page_keeps_scroll_order_and_reports_more():
    conn = _PageConn(_messages(152, 151, 150))

    page = chat_repo.get_message_page(conn, 7, limit=2, before_id=153)

    assert [message["id"] for message in page["messages"]] == [151, 152]
    assert page["has_more"] is True
    assert "id < %s" in conn.cursor_instance.sql
    assert conn.cursor_instance.params == (7, 153, 3)


def test_after_message_page_is_incremental_and_chronological():
    conn = _PageConn(_messages(206, 207))

    page = chat_repo.get_message_page(conn, 7, limit=50, after_id=205)

    assert [message["id"] for message in page["messages"]] == [206, 207]
    assert page["has_more"] is False
    assert "id > %s" in conn.cursor_instance.sql
    assert "ORDER BY id ASC" in conn.cursor_instance.sql


def test_message_page_rejects_two_cursors():
    with pytest.raises(ValueError, match="one cursor"):
        chat_repo.get_message_page(_PageConn([]), 7, before_id=10, after_id=20)


@pytest.mark.parametrize(
    ("stored", "public"),
    [("handoff", "waiting"), ("in_progress", "in_progress"), ("closed", "closed"), ("active", "active")],
)
def test_normalize_support_status(stored, public):
    assert chat_repo.normalize_support_status(stored) == public


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
