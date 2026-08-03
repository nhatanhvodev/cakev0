from decimal import Decimal

from app.engines.multiagent.action import (
    action_node,
    extract_order_id,
    extract_phone,
    fmt_vnd,
    format_order_summary,
)


def test_extract_phone():
    assert extract_phone("sdt 0901234567 nhé") == "0901234567"
    assert extract_phone("không có gì") is None


def test_extract_order_id():
    assert extract_order_id("đơn #123 đến đâu") == 123
    assert extract_order_id("don 45 den dau roi") == 45


def test_fmt_vnd():
    assert fmt_vnd(350000) == "350.000 VNĐ"
    assert fmt_vnd(Decimal("1000")) == "1.000 VNĐ"


def test_format_order_summary_empty():
    assert "không tìm thấy" in format_order_summary([])


def test_format_order_summary():
    text = format_order_summary([{"id": 7, "status": "pending", "total_amount": 350000,
                                  "created_at": "2026-07-19", "payment_method": "COD",
                                  "items": [{"ten_banh": "Bánh kem dâu", "quantity": 1, "price": 350000}]}])
    assert "#7" in text and "350.000" in text and ("pending" in text.lower() or "chờ" in text.lower())


# --- action_node integration (fake conn, no real MySQL needed) ---

class _FakeCursor:
    """Serves two result sets: the orders query, then per-order items queries."""

    def __init__(self, orders_rows, items_rows):
        self._orders_rows = orders_rows
        self._items_rows = items_rows
        self._mode = None

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False

    def execute(self, sql, params=None):
        self._mode = "items" if "order_items" in sql else "orders"

    def fetchall(self):
        return self._orders_rows if self._mode == "orders" else self._items_rows


class _FakeConn:
    def __init__(self, orders_rows, items_rows=None):
        self._orders_rows = orders_rows
        self._items_rows = items_rows or []
        self.closed = False

    def cursor(self):
        return _FakeCursor(self._orders_rows, self._items_rows)

    def close(self):
        self.closed = True


class _Deps:
    def __init__(self, conn_factory):
        self.conn_factory = conn_factory


def _state(query, user_id=None):
    return {"intent": "order_status", "query": query,
            "context": {"user_id": user_id} if user_id else {}}


def test_action_node_guest_asks_to_login():
    # A guest (no authenticated user_id) must be told to log in — never a lookup.
    deps = _Deps(conn_factory=lambda: None)
    out = action_node(deps, _state("đơn của tôi sao rồi"))
    assert "đăng nhập" in out["response"].lower()
    assert "action_result" not in out


def test_action_node_guest_never_opens_db():
    # The login guard must short-circuit before any DB connection is opened.
    conn = _FakeConn([{"id": 1, "status": "pending", "total_amount": Decimal("100000"),
                        "created_at": "2026-07-19", "payment_method": "COD"}])
    deps = _Deps(conn_factory=lambda: conn)
    out = action_node(deps, _state("đơn của tôi sao rồi"))
    assert "đăng nhập" in out["response"].lower()
    assert "action_result" not in out
    assert conn.closed is False  # connection was never opened


def test_action_node_guest_with_phone_is_refused():
    # IDOR guard: a guest typing someone's phone must NOT get their orders.
    orders_rows = [{"id": 7, "status": "shipping", "total_amount": Decimal("350000"),
                     "created_at": "2026-07-19", "payment_method": "COD"}]
    items_rows = [{"ten_banh": "Bánh kem dâu", "quantity": 1, "price": Decimal("350000")}]
    conn = _FakeConn(orders_rows, items_rows)
    deps = _Deps(conn_factory=lambda: conn)

    out = action_node(deps, _state("sdt 0901234567 đơn #7 sao rồi"))

    assert "đăng nhập" in out["response"].lower()
    assert "action_result" not in out
    assert conn.closed is False


def test_action_node_logged_in_user_looks_up_by_user_id():
    orders_rows = [{"id": 3, "status": "delivered", "total_amount": Decimal("50000"),
                     "created_at": "2026-07-18", "payment_method": "paid"}]
    conn = _FakeConn(orders_rows, [])
    deps = _Deps(conn_factory=lambda: conn)

    out = action_node(deps, _state("đơn của tôi sao rồi", user_id=42))

    assert out["action_result"]["orders"][0]["id"] == 3


def test_action_node_order_create_delegates_to_stub():
    deps = _Deps(conn_factory=lambda: None)
    out = action_node(deps, {"intent": "order_create", "query": "đặt bánh kem", "context": {}})
    assert "response" in out
