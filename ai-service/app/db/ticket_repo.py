"""Support tickets repository — handoff escalations to human agents."""


def create_ticket(conn, session_id, subject, priority="medium", draft_response=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO support_tickets (session_id, subject, priority, draft_response) "
            "VALUES (%s, %s, %s, %s)", (session_id, subject, priority, draft_response))
        return cur.lastrowid


def list_open_tickets(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM support_tickets WHERE status IN ('open','in_progress') ORDER BY created_at DESC")
        return list(cur.fetchall())
