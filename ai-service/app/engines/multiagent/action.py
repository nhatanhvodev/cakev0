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


def _query_promotions(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT b.id, b.ten_banh, b.gia, b.hinh_anh, b.slug, p.gia_khuyen_mai "
            "FROM promotions p JOIN banh b ON b.id = p.banh_id "
            "WHERE CURDATE() BETWEEN p.ngay_bat_dau AND p.ngay_ket_thuc "
            "ORDER BY (b.gia - p.gia_khuyen_mai) DESC LIMIT 10")
        return list(cur.fetchall())


def _query_bestsellers(conn, limit=5) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT b.id, b.ten_banh, b.gia, b.hinh_anh, b.slug, "
            "SUM(oi.quantity) AS total_sold "
            "FROM order_items oi JOIN banh b ON b.id = oi.banh_id "
            "JOIN orders o ON o.id = oi.order_id "
            "WHERE o.status NOT IN ('cancelled') "
            "GROUP BY b.id ORDER BY total_sold DESC LIMIT %s", (limit,))
        return list(cur.fetchall())


def _format_promotion_response(rows: list[dict]) -> tuple[str, list[dict]]:
    if not rows:
        return "Hiện tại chưa có chương trình khuyến mãi nào đang diễn ra.", []
    lines = []
    for r in rows:
        old = fmt_vnd(r["gia"])
        new = fmt_vnd(r["gia_khuyen_mai"])
        lines.append(f"🔥 {r['ten_banh']} — {old} → {new}")
    products = [{"id": r["id"], "ten_banh": r["ten_banh"], "gia": float(r["gia_khuyen_mai"]),
                 "hinh_anh": r.get("hinh_anh"), "slug": r.get("slug")} for r in rows]
    return "Các sản phẩm đang khuyến mãi:\n" + "\n".join(lines), products


def _format_bestseller_response(rows: list[dict]) -> tuple[str, list[dict]]:
    if not rows:
        return "Mình chưa có dữ liệu bán chạy.", []
    lines = []
    for i, r in enumerate(rows, 1):
        sold = int(r["total_sold"])
        lines.append(f"#{i} {r['ten_banh']} — {fmt_vnd(r['gia'])} (đã bán {sold})")
    products = [{"id": r["id"], "ten_banh": r["ten_banh"], "gia": float(r["gia"]),
                 "hinh_anh": r.get("hinh_anh"), "slug": r.get("slug")} for r in rows]
    return "Top sản phẩm bán chạy nhất:\n" + "\n".join(lines), products


def action_node(deps, state):
    if state["intent"] == "order_create":
        from app.engines.multiagent.order_create import order_create_node  # Task 17
        return order_create_node(deps, state)

    if state["intent"] in ("promotion", "bestseller"):
        conn = deps.conn_factory()
        if conn is None:
            return {"response": "Không kết nối được cơ sở dữ liệu, vui lòng thử lại sau."}
        try:
            if state["intent"] == "promotion":
                rows = _query_promotions(conn)
                text, products = _format_promotion_response(rows)
            else:
                rows = _query_bestsellers(conn)
                text, products = _format_bestseller_response(rows)
        finally:
            conn.close()
        return {"response": text, "products": products[:5]}

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
