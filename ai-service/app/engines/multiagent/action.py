import re

from app.db.orders_repo import lookup_orders

# Keep in sync with the web status labels in pages/order-detail.php.
STATUS_VI = {
    "pending": "Chờ xác nhận",
    "cho xac nhan": "Chờ xác nhận",
    "confirmed": "Đã xác nhận",
    "approved": "Đã xác nhận",
    "paid": "Đã thanh toán",
    "cod_not_deposited": "Chưa đặt cọc",
    "cod_deposited": "Đã đặt cọc",
    "delivering": "Đang giao hàng",
    "shipping": "Đang giao hàng",
    "delivered": "Đã giao hàng",
    "da giao": "Đã giao hàng",
    "completed": "Hoàn tất",
    "thanh cong": "Hoàn tất",
    "failed": "Thất bại",
    "cancelled": "Đã hủy",
    "huy": "Đã hủy",
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
            "WHERE b.is_hidden = 0 AND CURDATE() BETWEEN p.ngay_bat_dau AND p.ngay_ket_thuc "
            "ORDER BY (b.gia - p.gia_khuyen_mai) DESC LIMIT 10")
        return list(cur.fetchall())


def _query_bestsellers(conn, limit=5) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT b.id, b.ten_banh, b.gia, b.hinh_anh, b.slug, "
            "SUM(oi.quantity) AS total_sold "
            "FROM order_items oi JOIN banh b ON b.id = oi.banh_id "
            "JOIN orders o ON o.id = oi.order_id "
            "WHERE b.is_hidden = 0 AND LOWER(o.status) IN ('paid','approved','delivered','completed') "
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


_FEATURE_HANDLERS = {
    "coupon_inquiry": "coupon_inquiry_node",
    "review_lookup": "review_lookup_node",
    "product_compare": "product_compare_node",
    "favorite_add": "favorite_add_node",
    "favorite_view": "favorite_view_node",
    "dietary_inquiry": "dietary_inquiry_node",
}


def action_node(deps, state):
    if state["intent"] == "order_create":
        from app.engines.multiagent.order_create import order_create_node  # Task 17
        return order_create_node(deps, state)

    if state["intent"] == "custom_cake_quote":
        from app.engines.multiagent.custom_quote import custom_quote_node
        return custom_quote_node(deps, state)

    if state["intent"] in _FEATURE_HANDLERS:
        from app.engines.multiagent import features
        return getattr(features, _FEATURE_HANDLERS[state["intent"]])(deps, state)

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

    # order_status — only the authenticated owner may look up their own orders.
    # Phone-based lookup was removed: it let any guest read another customer's
    # order history by typing their phone number (IDOR / PII exposure).
    user_id = state.get("context", {}).get("user_id")
    order_id = extract_order_id(state["query"])

    if not user_id:
        return {"response": "Bạn vui lòng đăng nhập để mình tra cứu đơn hàng của bạn nhé, "
                            "hoặc mình chuyển bạn tới nhân viên hỗ trợ."}

    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, vui lòng thử lại sau."}

    try:
        # order_id is AND-combined with user_id, so it can only ever match the
        # caller's own order.
        orders = lookup_orders(conn, order_id=order_id, user_id=user_id)
    finally:
        conn.close()

    return {
        "response": format_order_summary(orders),
        "action_result": {"type": "order_status", "orders": [_serialize_order(o) for o in orders]},
    }
