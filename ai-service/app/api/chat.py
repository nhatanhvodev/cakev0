import hashlib
import hmac as _hmac
import time

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel
from app import deps as deps_mod
from app.config import get_settings
from app.db import chat_repo

router = APIRouter()

ADMIN_BYPASS_MAX_AGE = 300  # seconds — reject stale tokens (replay protection)


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
    try:
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
            update_fields = {}
            if reply.intent and reply.intent != "unknown":
                update_fields["intent_label"] = reply.intent
            if reply.handoff:
                update_fields["status"] = "handoff"
            if update_fields:
                chat_repo.update_session(conn, session["id"], **update_fields)
        return {"session_id": session["id"], "reply": reply.model_dump(), "handoff": reply.handoff}
    finally:
        if conn is not None:
            conn.close()


def _verify_admin_bypass(header_val: str | None) -> bool:
    """Verify HMAC(secret, "admin:<unix_ts>") from X-Admin-Bypass header.
    Timestamp binding stops replay of leaked tokens after ADMIN_BYPASS_MAX_AGE."""
    if not header_val or ":" not in header_val:
        return False
    ts_str, sig = header_val.split(":", 1)
    try:
        ts = int(ts_str)
    except ValueError:
        return False
    if abs(time.time() - ts) > ADMIN_BYPASS_MAX_AGE:
        return False
    secret = get_settings().internal_api_secret
    expected = _hmac.new(secret.encode(), f"admin:{ts}".encode(), hashlib.sha256).hexdigest()
    return _hmac.compare_digest(expected, sig)


def _require_admin(x_admin_bypass: str | None):
    if not _verify_admin_bypass(x_admin_bypass):
        raise HTTPException(status_code=403, detail="admin_auth_required")


@router.get("/chat/history")
def chat_history(session_id: int, user_id: int | None = None, guest_token: str | None = None,
                 x_admin_bypass: str | None = Header(default=None),
                 engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"session_id": session_id, "messages": []}
    try:
        is_admin = _verify_admin_bypass(x_admin_bypass)
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (session_id,))
            session_row = cur.fetchone()
        if session_row is None or (not is_admin and not chat_repo._session_owner_matches(
                session_row, user_id, guest_token, None)):
            raise HTTPException(status_code=403, detail="session_not_owned")
        rows = chat_repo.get_messages(conn, session_id)
    finally:
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
    try:
        tid = ticket_repo.create_ticket(conn, req.session_id, subject=req.reason or "Yêu cầu hỗ trợ",
                                        priority=req.priority)
        chat_repo.update_session(conn, req.session_id, status="handoff")
    finally:
        conn.close()
    return {"ticket_id": tid, "status": "open"}


class AdminReply(BaseModel):
    session_id: int
    admin_id: int
    content: str


@router.get("/admin/sessions")
def admin_sessions(x_admin_bypass: str | None = Header(default=None),
                   engine=Depends(deps_mod.get_engine)):
    _require_admin(x_admin_bypass)
    empty_stats = {"today_sessions": 0, "handoff_sessions": 0, "intent_counts": {}}
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"sessions": [], "stats": empty_stats}
    try:
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
    finally:
        conn.close()
    return {"sessions": rows,
            "stats": {"today_sessions": today_sessions,
                      "handoff_sessions": handoff_sessions,
                      "intent_counts": intent_counts}}


@router.post("/admin/reply")
def admin_reply(req: AdminReply,
                x_admin_bypass: str | None = Header(default=None),
                engine=Depends(deps_mod.get_engine)):
    _require_admin(x_admin_bypass)
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"message_id": None}
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO chat_messages (session_id, sender, content, admin_id) VALUES (%s, 'agent', %s, %s)",
                (req.session_id, req.content, req.admin_id))
            mid = cur.lastrowid
    finally:
        conn.close()
    return {"message_id": mid}


@router.post("/knowledge/index")
def knowledge_index(source: str = "all",
                    x_admin_bypass: str | None = Header(default=None),
                    engine=Depends(deps_mod.get_engine)):
    _require_admin(x_admin_bypass)
    from app.knowledge.indexer import reindex
    conn = None
    try:
        conn = engine.deps.conn_factory()
        n = reindex(engine.deps.store, conn, source)
    except Exception as e:
        import traceback
        raise HTTPException(status_code=500, detail={
            "error": type(e).__name__, "message": str(e)[:500],
            "trace": traceback.format_exc()[-800:]})
    finally:
        if conn:
            conn.close()
    return {"status": "ok", "indexed_count": n}


@router.get("/debug/db")
def debug_db(x_admin_bypass: str | None = Header(default=None),
             engine=Depends(deps_mod.get_engine)):
    """Admin-only: attempt a MySQL connection and report the exact error."""
    _require_admin(x_admin_bypass)
    from app.config import get_settings
    s = get_settings()
    conn = None
    try:
        conn = engine.deps.conn_factory()
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS c FROM banh")
            n = cur.fetchone()["c"]
        return {"db": "ok", "host": s.mysql_host, "port": s.mysql_port,
                "ssl": s.mysql_ssl, "banh_count": n}
    except Exception as e:
        # Connection to the configured DB failed — connect without a database and
        # list what actually exists so the operator can set the right MYSQL_DATABASE.
        dbs, banh_in = [], []
        try:
            import pymysql
            kw = dict(host=s.mysql_host, port=s.mysql_port, user=s.mysql_user,
                      password=s.mysql_password, charset="utf8mb4",
                      cursorclass=pymysql.cursors.DictCursor, autocommit=True)
            if s.mysql_ssl_ca:
                kw["ssl"] = {"ca": s.mysql_ssl_ca}
            elif s.mysql_ssl:
                kw["ssl"] = {}
            c2 = pymysql.connect(**kw)
            with c2.cursor() as cur:
                cur.execute("SHOW DATABASES")
                dbs = [list(r.values())[0] for r in cur.fetchall()]
                for d in dbs:
                    if d in ("information_schema", "performance_schema", "mysql", "sys"):
                        continue
                    try:
                        cur.execute(f"SELECT COUNT(*) c FROM `{d}`.banh")
                        banh_in.append({d: cur.fetchone()["c"]})
                    except Exception:
                        pass
            c2.close()
        except Exception as e2:
            dbs = [f"list_failed: {type(e2).__name__}: {str(e2)[:200]}"]
        return {"db": "fail", "host": s.mysql_host, "port": s.mysql_port,
                "ssl": s.mysql_ssl, "error": type(e).__name__, "message": str(e)[:500],
                "databases": dbs, "banh_table_found_in": banh_in}
    finally:
        if conn:
            conn.close()
