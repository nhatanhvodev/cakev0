from fastapi import APIRouter, Depends
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


@router.get("/chat/history")
def chat_history(session_id: int, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"session_id": session_id, "messages": []}
    rows = chat_repo.get_messages(conn, session_id)
    conn.close()
    messages = [{"sender": m["sender"], "content": m["content"],
                 "content_type": m.get("content_type"),
                 "created_at": str(m["created_at"]) if m.get("created_at") is not None else None}
                for m in rows]
    return {"session_id": session_id, "messages": messages}


@router.post("/knowledge/index")
def knowledge_index(source: str = "all", engine=Depends(deps_mod.get_engine)):
    from app.knowledge.indexer import reindex
    conn = engine.deps.conn_factory()
    n = reindex(engine.deps.store, conn, source)
    if conn: conn.close()
    return {"status": "ok", "indexed_count": n}
