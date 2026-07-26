"""Repository for AI CSKH feature queries: coupons, reviews, favorites, dietary."""

_PRODUCT_COLS = "id, ten_banh, loai, gia, mo_ta, hinh_anh, slug"


def query_public_coupons(conn) -> list[dict]:
    """Active, publicly-disclosable coupons only.

    Private loyalty coupons (is_public=0) are NEVER returned — the AI must not
    leak codes reserved for individual customers.
    """
    with conn.cursor() as cur:
        cur.execute(
            "SELECT code, discount_percent, min_subtotal, ends_at "
            "FROM cart_coupons "
            "WHERE is_public = 1 AND is_active = 1 "
            "AND (starts_at IS NULL OR starts_at <= CURDATE()) "
            "AND (ends_at IS NULL OR ends_at >= CURDATE()) "
            "AND (usage_limit IS NULL OR used_count < usage_limit) "
            "ORDER BY discount_percent DESC")
        return list(cur.fetchall())


def product_review_summary(conn, product_id: int, sample_limit: int = 2) -> dict:
    """Average rating, count and a few sample reviews for one product."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT AVG(rating) AS avg_rating, COUNT(*) AS n "
            "FROM product_reviews WHERE product_id = %s", (product_id,))
        agg = cur.fetchone() or {}
        n = int(agg.get("n") or 0)
        samples = []
        if n:
            cur.execute(
                "SELECT name, rating, content FROM product_reviews "
                "WHERE product_id = %s ORDER BY rating DESC, created_at DESC LIMIT %s",
                (product_id, sample_limit))
            samples = list(cur.fetchall())
    avg = float(agg["avg_rating"]) if agg.get("avg_rating") is not None else 0.0
    return {"avg_rating": avg, "count": n, "samples": samples}


def has_favorite(conn, user_id: int, banh_id: int) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT 1 FROM favorites WHERE user_id = %s AND banh_id = %s LIMIT 1",
            (user_id, banh_id))
        return cur.fetchone() is not None


def add_favorite(conn, user_id: int, banh_id: int) -> bool:
    """Add product to a user's favorites. Returns True if newly added,
    False if it was already there (idempotent)."""
    if has_favorite(conn, user_id, banh_id):
        return False
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO favorites (user_id, banh_id) VALUES (%s, %s)",
            (user_id, banh_id))
    conn.commit()
    return True


def list_favorites(conn, user_id: int, limit: int = 8) -> list[dict]:
    cols = ", ".join(f"b.{c.strip()}" for c in _PRODUCT_COLS.split(","))
    with conn.cursor() as cur:
        cur.execute(
            f"SELECT {cols} "
            "FROM favorites f JOIN banh b ON b.id = f.banh_id "
            "WHERE f.user_id = %s ORDER BY f.created_at DESC LIMIT %s",
            (user_id, limit))
        return list(cur.fetchall())


# Allergen flag column per dietary keyword. Excluding an allergen means WHERE <col> = 0.
DIETARY_COLS = {"trung": "co_trung", "sua": "co_sua", "gluten": "co_gluten", "hat": "co_hat"}


def create_custom_quote_lead(conn, name: str, phone: str, message: str) -> int:
    """Insert a custom-cake quote request into contact_requests (status=pending)
    so staff can follow up. email is stored empty (chat lead). Returns new id."""
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO contact_requests (name, email, phone, message, status) "
            "VALUES (%s, '', %s, %s, 'pending')", (name[:255], phone[:30], message))
        new_id = cur.lastrowid
    conn.commit()
    return new_id


def filter_by_dietary(conn, exclude: list[str], limit: int = 8) -> list[dict]:
    """Return products that EXCLUDE the given allergens (e.g. exclude=['trung']
    → products with co_trung = 0)."""
    cols = [DIETARY_COLS[e] for e in exclude if e in DIETARY_COLS]
    if not cols:
        return []
    where = " AND ".join(f"{c} = 0" for c in cols)
    with conn.cursor() as cur:
        cur.execute(
            f"SELECT {_PRODUCT_COLS} FROM banh WHERE {where} LIMIT %s", (limit,))
        return list(cur.fetchall())
