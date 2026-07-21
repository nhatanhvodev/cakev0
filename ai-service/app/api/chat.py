from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel
from app import deps as deps_mod
from app.db import chat_repo

router = APIRouter()


class ChatSendRequest(BaseModel):
    session_id: int | None = None
    user_id: int | None = None
    guest_token: str | None = None
    message: str
    context: dict = {}


class HandoffRequest(BaseModel):
    session_id: int
    reason: str = ""
    priority: str = "medium"


@router.post("/chat/send")
def chat_send(req: ChatSendRequest, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    history = []
    session = {"id": 0}
    if conn is not None:
        session = chat_repo.get_or_create_session(
            conn, user_id=req.user_id, guest_token=req.guest_token, session_id=req.session_id)
        history = [{"sender": m["sender"], "content": m["content"]}
                   for m in chat_repo.get_messages(conn, session["id"])]
        chat_repo.append_message(conn, session["id"], "customer", req.message)
    ctx = dict(req.context); ctx["session"] = session; ctx["user_id"] = req.user_id
    reply = engine.handle(history, req.message, ctx)
    if conn is not None:
        chat_repo.append_message(conn, session["id"], "bot", reply.content,
                                 content_type=reply.type if reply.type in ("text", "product_card", "order_card") else "text",
                                 metadata={"intent": reply.intent, "confidence": reply.confidence})
        if reply.handoff:
            chat_repo.update_session(conn, session["id"], status="handoff")
        conn.close()
    return {"session_id": session["id"], "reply": reply.model_dump(), "handoff": reply.handoff}


def _verify_admin_bypass(header_val: str | None) -> bool:
    if not header_val:
        return False
    import hashlib, hmac as _hmac
    from app.config import get_settings
    secret = get_settings().internal_api_secret
    expected = _hmac.new(secret.encode(), b"admin", hashlib.sha256).hexdigest()
    return _hmac.compare_digest(expected, header_val)


@router.get("/chat/history")
def chat_history(session_id: int, user_id: int | None = None, guest_token: str | None = None,
                 x_admin_bypass: str | None = Header(default=None),
                 engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"session_id": session_id, "messages": []}
    is_admin = _verify_admin_bypass(x_admin_bypass)
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (session_id,))
        session_row = cur.fetchone()
    if session_row is None or (not is_admin and not chat_repo._session_owner_matches(
            session_row, user_id, guest_token, None)):
        conn.close()
        raise HTTPException(status_code=403, detail="session_not_owned")
    rows = chat_repo.get_messages(conn, session_id)
    conn.close()
    messages = [{"id": m["id"], "sender": m["sender"], "content": m["content"],
                 "content_type": m.get("content_type"),
                 "created_at": str(m["created_at"]) if m.get("created_at") is not None else None}
                for m in rows]
    return {"session_id": session_id, "messages": messages}


@router.post("/chat/handoff")
def chat_handoff(req: HandoffRequest, engine=Depends(deps_mod.get_engine)):
    from app.db import ticket_repo
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"ticket_id": None, "status": "open"}
    tid = ticket_repo.create_ticket(conn, req.session_id, subject=req.reason or "Yêu cầu hỗ trợ",
                                    priority=req.priority)
    chat_repo.update_session(conn, req.session_id, status="handoff")
    conn.close()
    return {"ticket_id": tid, "status": "open"}


class AdminReply(BaseModel):
    session_id: int
    admin_id: int
    content: str


@router.get("/admin/sessions")
def admin_sessions(engine=Depends(deps_mod.get_engine)):
    empty_stats = {"today_sessions": 0, "handoff_sessions": 0, "intent_counts": {}}
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"sessions": [], "stats": empty_stats}
    with conn.cursor() as cur:
        cur.execute("""
            SELECT s.id, s.source, s.status, s.user_id, s.updated_at,
                   (SELECT content FROM chat_messages m WHERE m.session_id = s.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM support_tickets t WHERE t.session_id = s.id AND t.status IN ('open','in_progress')) AS open_ticket_count
            FROM chat_sessions s ORDER BY s.updated_at DESC LIMIT 100""")
        rows = [dict(r, updated_at=str(r["updated_at"])) for r in cur.fetchall()]

        cur.execute("SELECT COUNT(*) AS c FROM chat_sessions WHERE DATE(created_at) = CURDATE()")
        today_sessions = cur.fetchone()["c"]

        cur.execute("SELECT COUNT(*) AS c FROM chat_sessions WHERE status = 'handoff'")
        handoff_sessions = cur.fetchone()["c"]

        cur.execute("""SELECT intent_label, COUNT(*) AS c FROM chat_sessions
                        WHERE intent_label IS NOT NULL GROUP BY intent_label""")
        intent_counts = {r["intent_label"]: r["c"] for r in cur.fetchall()}
    conn.close()
    return {"sessions": rows,
            "stats": {"today_sessions": today_sessions,
                      "handoff_sessions": handoff_sessions,
                      "intent_counts": intent_counts}}


@router.post("/admin/reply")
def admin_reply(req: AdminReply, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"message_id": None}
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO chat_messages (session_id, sender, content, admin_id) VALUES (%s, 'agent', %s, %s)",
            (req.session_id, req.content, req.admin_id))
        mid = cur.lastrowid
    conn.close()
    return {"message_id": mid}


@router.post("/knowledge/index")
def knowledge_index(source: str = "all", engine=Depends(deps_mod.get_engine)):
    from app.knowledge.indexer import reindex
    conn = engine.deps.conn_factory()
    n = reindex(engine.deps.store, conn, source)
    if conn: conn.close()
    return {"status": "ok", "indexed_count": n}
