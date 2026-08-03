"""Orders repository for order lookup queries."""


def lookup_orders(conn, phone=None, order_id=None, user_id=None, limit=5) -> list[dict]:
    """Look up orders by order_id, phone, and/or user_id (filters are AND-combined).

    Returns a list of order dicts (id, status, total_amount, created_at,
    payment_method) each with a nested `items` list of
    `{ten_banh, quantity, price}`. Returns [] when no filter is given,
    to avoid ever returning the whole orders table.

    A valid `user_id` is REQUIRED: order lookups must always be scoped to the
    authenticated owner. Without it (e.g. phone-only), returns [] so no caller
    can read another customer's orders. Defense in depth behind the API layer.
    """
    if not user_id:
        return []

    where, params = [], []
    if order_id:
        where.append("o.id = %s")
        params.append(order_id)
    if phone:
        where.append("o.phone = %s")
        params.append(phone)
    if user_id:
        where.append("o.user_id = %s")
        params.append(user_id)
    if not where:
        return []

    with conn.cursor() as cur:
        cur.execute(
            "SELECT o.id, o.status, o.total_amount, o.created_at, o.payment_method "
            f"FROM orders o WHERE {' AND '.join(where)} ORDER BY o.created_at DESC LIMIT %s",
            (*params, limit),
        )
        orders = list(cur.fetchall())
        for o in orders:
            cur.execute(
                "SELECT b.ten_banh, oi.quantity, oi.price FROM order_items oi "
                "JOIN banh b ON b.id = oi.banh_id WHERE oi.order_id = %s",
                (o["id"],),
            )
            o["items"] = list(cur.fetchall())
    return orders
