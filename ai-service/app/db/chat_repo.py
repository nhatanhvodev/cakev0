import json

_ALLOWED = {"status", "intent_label", "summary", "metadata", "closed_at"}


def get_or_create_session(conn, user_id=None, guest_token=None, source="widget",
                          external_user_id=None, session_id=None) -> dict:
    with conn.cursor() as cur:
        if session_id:
            cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (session_id,))
            row = cur.fetchone()
            if row:
                return row
        if external_user_id and not session_id:
            cur.execute(
                "SELECT * FROM chat_sessions WHERE external_user_id = %s AND status = 'active' "
                "ORDER BY id DESC LIMIT 1", (external_user_id,))
            row = cur.fetchone()
            if row:
                return row
        cur.execute(
            "INSERT INTO chat_sessions (user_id, guest_token, source, external_user_id) "
            "VALUES (%s, %s, %s, %s)", (user_id, guest_token, source, external_user_id))
        cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (cur.lastrowid,))
        return cur.fetchone()


def append_message(conn, session_id, sender, content, content_type="text", metadata=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO chat_messages (session_id, sender, content, content_type, metadata) "
            "VALUES (%s, %s, %s, %s, %s)",
            (session_id, sender, content, content_type,
             json.dumps(metadata, ensure_ascii=False) if metadata else None))
        return cur.lastrowid


def get_messages(conn, session_id, limit=50) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM chat_messages WHERE session_id = %s ORDER BY id ASC LIMIT %s",
                    (session_id, limit))
        return list(cur.fetchall())


def update_session(conn, session_id, **fields):
    sets, vals = [], []
    for k, v in fields.items():
        if k not in _ALLOWED:
            raise ValueError(f"field not allowed: {k}")
        if k == "metadata" and isinstance(v, dict):
            v = json.dumps(v, ensure_ascii=False)
        sets.append(f"{k} = %s"); vals.append(v)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE chat_sessions SET {', '.join(sets)} WHERE id = %s", (*vals, session_id))
