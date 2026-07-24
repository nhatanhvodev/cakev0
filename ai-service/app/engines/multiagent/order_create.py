import json

from app.services import order_create_service as svc


def order_create_node(deps, state):
    ctx = state.get("context", {})
    session = ctx.get("session") or {}
    resp, order, new_draft = svc.advance_draft(deps, session, state["query"], ctx.get("user_id"))
    conn = deps.conn_factory()
    if conn is not None and session.get("id"):
        from app.db import chat_repo
        meta = session.get("metadata")
        meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
        meta["order_draft"] = new_draft
        chat_repo.update_session(conn, session["id"], metadata=meta)
        conn.close()
    out = {"response": resp}
    if order:
        out["action_result"] = {"type": "order_card", "order": order}
    return out
