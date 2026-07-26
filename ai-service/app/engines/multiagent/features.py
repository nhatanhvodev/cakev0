"""ACTION handlers for AI CSKH features: coupon, review, compare, favorite, dietary."""

from app.db import features_repo, catalog_repo


def fmt_vnd(v) -> str:
    return f"{int(v):,}".replace(",", ".") + " VNĐ"


def _card(row: dict) -> dict:
    """Product row -> widget card dict."""
    return {"id": row["id"], "ten_banh": row["ten_banh"], "gia": float(row["gia"]),
            "hinh_anh": row.get("hinh_anh"), "slug": row.get("slug")}


def _resolve_product_ids(deps, query: str, top_k: int = 5) -> list[int]:
    """Semantic-search the product collection, return matched product ids in order."""
    docs = deps.store.query("products", query, top_k=top_k)
    ids = []
    for d in docs:
        if d.id.startswith("product-"):
            try:
                ids.append(int(d.id.split("-", 1)[1]))
            except (ValueError, IndexError):
                continue
    return ids


def _ordered_rows(conn, ids: list[int]) -> list[dict]:
    """Load products by id, preserving the id order from semantic search."""
    rows = catalog_repo.find_products_by_ids(conn, ids)
    by_id = {r["id"]: r for r in rows}
    return [by_id[i] for i in ids if i in by_id]


# ── coupon_inquiry ──────────────────────────────────────────────────────────

def coupon_inquiry_node(deps, state):
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        coupons = features_repo.query_public_coupons(conn)
    finally:
        conn.close()
    if not coupons:
        return {"response": "Hiện chưa có mã giảm giá công khai nào bạn ạ. Bạn theo dõi trang chủ "
                            "và fanpage để cập nhật ưu đãi mới nhất nhé!"}
    lines = []
    for c in coupons:
        disc = f"{int(c['discount_percent'])}%"
        cond = f" cho đơn từ {fmt_vnd(c['min_subtotal'])}" if c.get("min_subtotal") else ""
        exp = f", dùng đến {c['ends_at']}" if c.get("ends_at") else ""
        lines.append(f"- Mã {c['code']}: giảm {disc}{cond}{exp}")
    return {"response": "Gấu Bakery đang có ưu đãi cho bạn:\n" + "\n".join(lines) +
                        "\nNhập mã ở bước thanh toán để được giảm nhé!"}


# ── review_lookup ───────────────────────────────────────────────────────────

def review_lookup_node(deps, state):
    ids = _resolve_product_ids(deps, state.get("normalized_query", state["query"]), top_k=1)
    if not ids:
        return {"response": "Bạn cho mình biết tên bánh muốn xem đánh giá nhé?"}
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        rows = _ordered_rows(conn, ids)
        if not rows:
            return {"response": "Bạn cho mình biết tên bánh muốn xem đánh giá nhé?"}
        prod = rows[0]
        summary = features_repo.product_review_summary(conn, prod["id"])
    finally:
        conn.close()
    if summary["count"] == 0:
        return {"response": f"Bánh {prod['ten_banh']} chưa có đánh giá nào — bạn có thể là "
                            f"người đầu tiên nhận xét sau khi mua đó! 🧁",
                "products": [_card(prod)]}
    stars = "⭐" * round(summary["avg_rating"])
    lines = [f"Đánh giá {prod['ten_banh']}: {stars} {summary['avg_rating']:.1f}/5 "
             f"({summary['count']} lượt)"]
    for s in summary["samples"]:
        lines.append(f"- \"{s['content']}\" — {s['name']} ({int(s['rating'])}★)")
    return {"response": "\n".join(lines), "products": [_card(prod)]}


# ── product_compare ─────────────────────────────────────────────────────────

def _size_hint(mo_ta: str) -> str:
    import re
    m = re.search(r"(\d{1,2}\s*cm|\d\s*-\s*\d\s*người)", mo_ta or "", re.I)
    return m.group(1) if m else "—"


def product_compare_node(deps, state):
    ids = _resolve_product_ids(deps, state.get("normalized_query", state["query"]), top_k=6)
    # de-dup preserving order
    seen, uniq = set(), []
    for i in ids:
        if i not in seen:
            seen.add(i)
            uniq.append(i)
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        rows = _ordered_rows(conn, uniq)
    finally:
        conn.close()
    if len(rows) < 2:
        name = rows[0]["ten_banh"] if rows else "bánh này"
        return {"response": f"Bạn muốn so sánh {name} với bánh nào ạ? Nhắn giúp mình tên "
                            f"cả hai loại bánh nhé."}
    a, b = rows[0], rows[1]
    lines = [f"So sánh {a['ten_banh']} và {b['ten_banh']}:",
             f"- Giá: {fmt_vnd(a['gia'])} / {fmt_vnd(b['gia'])}",
             f"- Loại: {a['loai']} / {b['loai']}",
             f"- Cỡ: {_size_hint(a.get('mo_ta'))} / {_size_hint(b.get('mo_ta'))}"]
    return {"response": "\n".join(lines), "products": [_card(a), _card(b)]}


# ── favorite_add / favorite_view ────────────────────────────────────────────

def _require_login():
    return {"response": "Bạn đăng nhập tài khoản để lưu và xem bánh yêu thích nhé 💛"}


def favorite_add_node(deps, state):
    user_id = state.get("context", {}).get("user_id")
    if not user_id:
        return _require_login()
    ids = _resolve_product_ids(deps, state.get("normalized_query", state["query"]), top_k=1)
    if not ids:
        return {"response": "Bạn cho mình biết tên bánh muốn lưu vào yêu thích nhé?"}
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        rows = _ordered_rows(conn, ids)
        if not rows:
            return {"response": "Bạn cho mình biết tên bánh muốn lưu vào yêu thích nhé?"}
        prod = rows[0]
        added = features_repo.add_favorite(conn, user_id, prod["id"])
    finally:
        conn.close()
    msg = (f"Đã thêm {prod['ten_banh']} vào danh sách yêu thích của bạn! 💛" if added
           else f"{prod['ten_banh']} đã có trong danh sách yêu thích của bạn rồi nhé 💛")
    return {"response": msg, "products": [_card(prod)]}


def favorite_view_node(deps, state):
    user_id = state.get("context", {}).get("user_id")
    if not user_id:
        return _require_login()
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        rows = features_repo.list_favorites(conn, user_id)
    finally:
        conn.close()
    if not rows:
        return {"response": "Bạn chưa lưu bánh yêu thích nào. Nhắn 'lưu bánh [tên]' để thêm nhé!"}
    return {"response": "Danh sách bánh yêu thích của bạn:",
            "products": [_card(r) for r in rows]}


# ── dietary_inquiry ─────────────────────────────────────────────────────────

_DIETARY_DISCLAIMER = ("\nLưu ý: thông tin mang tính tham khảo. Nếu bạn dị ứng nặng, "
                       "vui lòng gọi hotline 0901 234 567 để được xác nhận thành phần trước khi đặt.")


def _detect_exclusions(text: str) -> list[str]:
    low = text.lower()
    ex = []
    if "thuần chay" in low or "thuan chay" in low or "vegan" in low:
        ex += ["trung", "sua"]
    if "trứng" in low or "trung" in low:
        ex.append("trung")
    if "sữa" in low or "sua" in low or "lactose" in low:
        ex.append("sua")
    if "gluten" in low or "bột mì" in low or "bot mi" in low:
        ex.append("gluten")
    if "hạt" in low or "hat" in low:
        ex.append("hat")
    # de-dup preserve order
    seen, out = set(), []
    for e in ex:
        if e not in seen:
            seen.add(e)
            out.append(e)
    return out


def dietary_inquiry_node(deps, state):
    exclude = _detect_exclusions(state["query"])
    if not exclude:
        return {"response": "Bạn cần tránh thành phần nào ạ? Ví dụ: không trứng, không sữa, "
                            "không gluten hoặc không hạt. Mình sẽ lọc bánh phù hợp cho bạn."}
    conn = deps.conn_factory()
    if conn is None:
        return {"response": "Không kết nối được cơ sở dữ liệu, bạn thử lại sau nhé."}
    try:
        rows = features_repo.filter_by_dietary(conn, exclude)
    finally:
        conn.close()
    label = {"trung": "trứng", "sua": "sữa", "gluten": "gluten", "hat": "hạt"}
    tag = ", ".join(label[e] for e in exclude)
    if not rows:
        return {"response": f"Hiện Gấu Bakery chưa có bánh nào đảm bảo không chứa {tag}. "
                            f"Bạn gọi hotline 0901 234 567 để được tư vấn thêm nhé." + _DIETARY_DISCLAIMER}
    return {"response": f"Các bánh không chứa {tag} bạn có thể tham khảo:" + _DIETARY_DISCLAIMER,
            "products": [_card(r) for r in rows]}
