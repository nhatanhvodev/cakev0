"""Catalog repository for banh (product) data."""


def build_in_placeholders(ids: list) -> str:
    """Build SQL placeholders for IN clause: [1,2,3] -> '%s,%s,%s'"""
    return ",".join(["%s"] * len(ids))


def list_products(conn) -> list[dict]:
    """List visible products from banh table."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh WHERE is_hidden = 0"
        )
        return list(cur.fetchall())


def find_products_by_ids(conn, ids: list[int]) -> list[dict]:
    """Find products by list of IDs."""
    if not ids:
        return []
    ph = build_in_placeholders(ids)
    with conn.cursor() as cur:
        cur.execute(
            f"SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh WHERE id IN ({ph}) AND is_hidden = 0",
            ids,
        )
        return list(cur.fetchall())


def search_products_like(conn, keyword: str, limit: int = 5) -> list[dict]:
    """Search products by ten_banh or loai using LIKE."""
    like = f"%{keyword}%"
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh "
            "WHERE is_hidden = 0 AND (ten_banh LIKE %s OR loai LIKE %s) LIMIT %s",
            (like, like, limit),
        )
        return list(cur.fetchall())
