import re

from app.db.orders_repo import lookup_orders

STATUS_VI = {
    "pending": "Chờ xác nhận",
    "confirmed": "Đã xác nhận",
    "shipping": "Đang giao",
    "delivered": "Đã giao",
    "cancelled": "Đã hủy",
    "cod_not_deposited": "COD chờ cọc",
    "paid": "Đã thanh toán",
}

_PHONE_RE = re.compile(r"(?:\+84|0)\d{8,10}")
_ORDER_ID_RE = re.compile(r"(?:đơn|don|#|mã|ma)\s*#?(\d{1,8})", re.I)


def extract_phone(text: str):
    """Extract a Vietnamese phone number (e.g. 0901234567, +84901234567) from free text."""
    m = _PHONE_RE.search(text)
    return m.group(0) if m else None


def extract_order_id(text: str):
    """Extract an order id referenced in free text, e.g. 'đơn #123' or 'don 45'."""
    m = _ORDER_ID_RE.search(text)
    return int(m.group(1)) if m else None


def fmt_vnd(v) -> str:
    """Format a numeric amount as Vietnamese currency, e.g. 350000 -> '350.000 VNĐ'."""
    return f"{int(v):,}".replace(",", ".") + " VNĐ"


def format_order_summary(orders: list[dict]) -> str:
    """Render a list of order dicts into a Vietnamese summary message."""
    if not orders:
        return "Mình không tìm thấy đơn hàng nào khớp thông tin bạn cung cấp."
    lines = []
    for o in orders:
        status = STATUS_VI.get(str(o["status"]).lower(), o["status"])
        items = ", ".join(f"{i['ten_banh']} x{i['quantity']}" for i in o.get("items", []))
        lines.append(f"Đơn #{o['id']} — {status} — {fmt_vnd(o['total_amount'])} ({items})")
    return "Thông tin đơn hàng của bạn:\n" + "\n".join(lines)


def _serialize_order(o: dict) -> dict:
    """Make an order dict JSON-friendly (Decimal/datetime -> float/str)."""
    o = dict(o)
    o["created_at"] = str(o["created_at"])
    o["total_amount"] = float(o["total_amount"])
    o["items"] = [{**i, "price": float(i["price"])} for i in o.get("items", [])]
    return o


def action_node(deps, state):
    if state["intent"] == "order_create":
        from app.engines.multiagent.order_create import order_create_node  # Task 17
        return order_create_node(deps, state)

    # order_status
    user_id = state.get("context", {}).get("user_id")
    phone = extract_phone(state["query"])
    order_id = extract_order_id(state["query"])

    conn = deps.conn_factory()
    if conn is None or (not user_id and not phone):
        if conn is not None:
            conn.close()
        return {"response": "Bạn cho mình xin số điện thoại đặt hàng (hoặc đăng nhập) để tra cứu đơn nhé."}

    try:
        orders = lookup_orders(conn, phone=phone, order_id=order_id, user_id=user_id)
    finally:
        conn.close()

    return {
        "response": format_order_summary(orders),
        "action_result": {"type": "order_status", "orders": [_serialize_order(o) for o in orders]},
    }
