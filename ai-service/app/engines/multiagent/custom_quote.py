import json

from app.services import custom_quote_service as svc


def custom_quote_node(deps, state):
    ctx = state.get("context", {})
    session = ctx.get("session") or {}
    resp, created, new_draft = svc.advance_quote(deps, session, state["query"])
    conn = deps.conn_factory()
    if conn is not None and session.get("id"):
        from app.db import chat_repo
        meta = session.get("metadata")
        meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
        meta["custom_quote"] = new_draft
        chat_repo.update_session(conn, session["id"], metadata=meta)
        conn.close()
    return {"response": resp}
