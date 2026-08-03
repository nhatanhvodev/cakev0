# Chat and admin support workflow API.
import hashlib
import hmac as _hmac
import json
import time
from contextlib import contextmanager

from fastapi import APIRouter, Depends, Header, HTTPException, Query
from pydantic import BaseModel, Field

from app import deps as deps_mod
from app.config import get_settings
from app.db import chat_repo

router = APIRouter()

ADMIN_BYPASS_MAX_AGE = 300
ADMIN_SESSION_VIEWS = {"waiting", "mine", "in_progress", "closed", "all"}
ADMIN_SESSION_ACTIONS = {"claim", "close", "reopen"}


@contextmanager
def _admin_transaction(conn):
    """Make workflow state, messages, and audit events commit atomically."""
    conn.begin()
    try:
        yield
    except Exception:
        conn.rollback()
        raise
    else:
        conn.commit()


class ChatSendRequest(BaseModel):
    session_id: int | None = None
    user_id: int | None = None
    guest_token: str | None = None
    message: str
    context: dict = Field(default_factory=dict)


class HandoffRequest(BaseModel):
    session_id: int
    reason: str = ""
    priority: str = "medium"
    guest_token: str | None = None


class AdminReply(BaseModel):
    session_id: int
    admin_id: int
    content: str


class AdminSessionAction(BaseModel):
    session_id: int
    admin_id: int
    action: str


def _workflow_ready(conn) -> bool:
    try:
        return chat_repo.workflow_schema_ready(conn)
    except Exception:
        return False


def _decode_metadata(value):
    if isinstance(value, dict) or value is None:
        return value
    if not isinstance(value, str):
        return None
    try:
        decoded = json.loads(value)
    except (TypeError, ValueError, json.JSONDecodeError):
        return None
    return decoded if isinstance(decoded, dict) else None


def _verify_admin_bypass(header_val: str | None) -> bool:
    """Verify HMAC(secret, "admin:<unix_ts>") and reject stale tokens."""
    if not header_val or ":" not in header_val:
        return False
    ts_str, signature = header_val.split(":", 1)
    try:
        timestamp = int(ts_str)
    except ValueError:
        return False
    if abs(time.time() - timestamp) > ADMIN_BYPASS_MAX_AGE:
        return False
    secret = get_settings().internal_api_secret
    expected = _hmac.new(
        secret.encode(),
        f"admin:{timestamp}".encode(),
        hashlib.sha256,
    ).hexdigest()
    return _hmac.compare_digest(expected, signature)


def _require_admin(x_admin_bypass: str | None) -> None:
    if not _verify_admin_bypass(x_admin_bypass):
        raise HTTPException(status_code=403, detail="admin_auth_required")


def _verify_user_identity(header_val: str | None) -> int | None:
    """Verify HMAC(secret, "user:<ts>:<user_id>") and return the signed user_id.

    Mirrors _verify_admin_bypass. Returns None on any failure (missing/malformed
    header, stale timestamp, bad signature) so the caller is treated as a guest
    (fail-closed). The PHP proxy signs this header from the server-side session,
    so a direct caller cannot forge another user's identity.
    """
    if not header_val or header_val.count(":") < 2:
        return None
    ts_str, uid_str, signature = header_val.split(":", 2)
    try:
        timestamp = int(ts_str)
        user_id = int(uid_str)
    except ValueError:
        return None
    if user_id <= 0:
        return None
    if abs(time.time() - timestamp) > ADMIN_BYPASS_MAX_AGE:
        return None
    secret = get_settings().internal_api_secret
    expected = _hmac.new(
        secret.encode(),
        f"user:{timestamp}:{user_id}".encode(),
        hashlib.sha256,
    ).hexdigest()
    if not _hmac.compare_digest(expected, signature):
        return None
    return user_id


def _serialize_message(message: dict) -> dict:
    return {
        "id": message["id"],
        "sender": message["sender"],
        "content": message["content"],
        "content_type": message.get("content_type"),
        "metadata": _decode_metadata(message.get("metadata")),
        "admin_id": message.get("admin_id"),
        "created_at": (
            str(message["created_at"])
            if message.get("created_at") is not None
            else None
        ),
    }


def _serialize_admin_session(row: dict) -> dict:
    item = dict(row)
    item["stored_status"] = item.get("status")
    item["status"] = chat_repo.normalize_support_status(item.get("status"))
    for key in (
        "created_at",
        "updated_at",
        "assigned_at",
        "closed_at",
        "reopened_at",
    ):
        if item.get(key) is not None:
            item[key] = str(item[key])
    return item


@router.post("/chat/send")
def chat_send(
    req: ChatSendRequest,
    x_user_identity: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    # Trust only the signed identity from the PHP proxy, never req.user_id (which
    # a direct caller could spoof). Guests (no valid header) are scoped by guest_token.
    trusted_uid = _verify_user_identity(x_user_identity)
    conn = engine.deps.conn_factory()
    history = []
    session = {"id": 0}
    try:
        if conn is not None:
            session = chat_repo.get_or_create_session(
                conn,
                user_id=trusted_uid,
                guest_token=req.guest_token,
                session_id=req.session_id,
            )
            history = [
                {"sender": message["sender"], "content": message["content"]}
                for message in chat_repo.get_messages(conn, session["id"])
            ]
            chat_repo.append_message(
                conn, session["id"], "customer", req.message
            )

        context = dict(req.context)
        context["session"] = session
        context["user_id"] = trusted_uid
        reply = engine.handle(history, req.message, context)

        if conn is not None:
            message_metadata = {
                "intent": reply.intent,
                "confidence": reply.confidence,
            }
            if reply.products:
                message_metadata["products"] = reply.products[:5]
            if reply.citations:
                message_metadata["citations"] = reply.citations
            chat_repo.append_message(
                conn,
                session["id"],
                "bot",
                reply.content,
                content_type=(
                    reply.type
                    if reply.type in ("text", "product_card", "order_card")
                    else "text"
                ),
                metadata=message_metadata,
            )

            update_fields = {}
            if reply.intent and reply.intent != "unknown":
                update_fields["intent_label"] = reply.intent
            if reply.handoff:
                update_fields["status"] = (
                    "open" if _workflow_ready(conn) else "handoff"
                )
            if update_fields:
                chat_repo.update_session(conn, session["id"], **update_fields)

        return {
            "session_id": session["id"],
            "reply": reply.model_dump(),
            "handoff": reply.handoff,
        }
    finally:
        if conn is not None:
            conn.close()


@router.get("/chat/history")
def chat_history(
    session_id: int,
    user_id: int | None = None,
    guest_token: str | None = None,
    before_id: int | None = Query(default=None, gt=0),
    after_id: int | None = Query(default=None, gt=0),
    limit: int = Query(default=50, ge=1, le=100),
    x_admin_bypass: str | None = Header(default=None),
    x_user_identity: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    if before_id is not None and after_id is not None:
        raise HTTPException(status_code=422, detail="use_only_one_cursor")

    # Ownership is decided by the signed identity (or guest_token), not the
    # spoofable user_id query param.
    trusted_uid = _verify_user_identity(x_user_identity)

    conn = engine.deps.conn_factory()
    if conn is None:
        return {
            "session_id": session_id,
            "messages": [],
            "has_more": False,
            "oldest_id": None,
            "latest_id": None,
        }

    try:
        is_admin = _verify_admin_bypass(x_admin_bypass)
        with conn.cursor() as cursor:
            cursor.execute(
                "SELECT * FROM chat_sessions WHERE id = %s",
                (session_id,),
            )
            session_row = cursor.fetchone()
        if session_row is None or (
            not is_admin
            and not chat_repo._session_owner_matches(
                session_row, trusted_uid, guest_token, None
            )
        ):
            raise HTTPException(status_code=403, detail="session_not_owned")

        page = chat_repo.get_message_page(
            conn,
            session_id,
            limit=limit,
            before_id=before_id,
            after_id=after_id,
        )
    finally:
        conn.close()

    return {
        "session_id": session_id,
        "messages": [
            _serialize_message(message) for message in page["messages"]
        ],
        "has_more": page["has_more"],
        "oldest_id": page["oldest_id"],
        "latest_id": page["latest_id"],
    }


@router.post("/chat/handoff")
def chat_handoff(
    req: HandoffRequest,
    x_admin_bypass: str | None = Header(default=None),
    x_user_identity: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    from app.db import ticket_repo

    conn = engine.deps.conn_factory()
    if conn is None:
        return {"ticket_id": None, "status": "open"}
    try:
        # Only the session owner (or an admin) may open a handoff/ticket for it.
        is_admin = _verify_admin_bypass(x_admin_bypass)
        if not is_admin:
            trusted_uid = _verify_user_identity(x_user_identity)
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT * FROM chat_sessions WHERE id = %s",
                    (req.session_id,),
                )
                session_row = cursor.fetchone()
            if session_row is None or not chat_repo._session_owner_matches(
                session_row, trusted_uid, req.guest_token, None
            ):
                raise HTTPException(status_code=403, detail="session_not_owned")

        ticket_id = ticket_repo.create_ticket(
            conn,
            req.session_id,
            subject=req.reason or "Yêu cầu hỗ trợ",
            priority=req.priority,
        )
        stored_status = "open" if _workflow_ready(conn) else "handoff"
        chat_repo.update_session(
            conn, req.session_id, status=stored_status
        )
    finally:
        conn.close()
    return {"ticket_id": ticket_id, "status": "open"}


def _admin_sessions_response(
    conn,
    view: str,
    admin_id: int | None,
    empty_stats: dict,
) -> dict:
    workflow_ready = _workflow_ready(conn)
    if workflow_ready:
        assignment_columns = """
            s.assigned_admin_id, s.assigned_at, s.closed_at, s.reopened_at,
            a.username AS assigned_admin_name,
        """
        assignment_join = (
            "LEFT JOIN admins a ON a.id = s.assigned_admin_id"
        )
    else:
        assignment_columns = """
            NULL AS assigned_admin_id, NULL AS assigned_at,
            s.closed_at, NULL AS reopened_at,
            NULL AS assigned_admin_name,
        """
        assignment_join = ""

    params = []
    if view == "waiting":
        where = (
            "s.status IN ('open', 'handoff')"
            if workflow_ready
            else "s.status = 'handoff'"
        )
    elif view == "mine":
        where = (
            "s.assigned_admin_id = %s AND s.status = 'in_progress'"
            if workflow_ready
            else "1 = 0"
        )
        if workflow_ready:
            params.append(admin_id)
    elif view == "in_progress":
        where = "s.status = 'in_progress'" if workflow_ready else "1 = 0"
    elif view == "closed":
        where = "s.status = 'closed'"
    else:
        where = "1 = 1"

    with conn.cursor() as cursor:
        cursor.execute(
            f"""
            SELECT s.id, s.source, s.status, s.user_id, s.intent_label,
                   s.created_at, s.updated_at,
                   {assignment_columns}
                   (SELECT content FROM chat_messages m
                    WHERE m.session_id = s.id
                    ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT sender FROM chat_messages m
                    WHERE m.session_id = s.id
                    ORDER BY m.id DESC LIMIT 1) AS last_sender,
                   (SELECT COUNT(*) FROM support_tickets t
                    WHERE t.session_id = s.id
                      AND t.status IN ('open', 'in_progress'))
                    AS open_ticket_count
            FROM chat_sessions s
            {assignment_join}
            WHERE {where}
            ORDER BY s.updated_at DESC
            LIMIT 100
            """,
            tuple(params),
        )
        sessions = [
            _serialize_admin_session(row) for row in cursor.fetchall()
        ]

        cursor.execute(
            "SELECT COUNT(*) AS c FROM chat_sessions "
            "WHERE DATE(created_at) = CURDATE()"
        )
        today_sessions = int(cursor.fetchone()["c"])

        if workflow_ready:
            cursor.execute(
                """
                SELECT
                    COALESCE(SUM(status IN ('open', 'handoff')), 0)
                        AS waiting_sessions,
                    COALESCE(SUM(status = 'in_progress'), 0)
                        AS in_progress_sessions,
                    COALESCE(SUM(status = 'closed'), 0)
                        AS closed_sessions,
                    COALESCE(
                        SUM(
                            assigned_admin_id = %s
                            AND status = 'in_progress'
                        ),
                        0
                    ) AS mine_sessions
                FROM chat_sessions
                """,
                (admin_id or 0,),
            )
            counts = cursor.fetchone()
        else:
            cursor.execute(
                """
                SELECT
                    COALESCE(SUM(status = 'handoff'), 0)
                        AS waiting_sessions,
                    0 AS in_progress_sessions,
                    COALESCE(SUM(status = 'closed'), 0)
                        AS closed_sessions,
                    0 AS mine_sessions
                FROM chat_sessions
                """
            )
            counts = cursor.fetchone()

        cursor.execute(
            """
            SELECT intent_label, COUNT(*) AS c
            FROM chat_sessions
            WHERE intent_label IS NOT NULL
            GROUP BY intent_label
            """
        )
        intent_counts = {
            row["intent_label"]: row["c"] for row in cursor.fetchall()
        }

    waiting_sessions = int(counts["waiting_sessions"])
    stats = dict(empty_stats)
    stats.update(
        {
            "today_sessions": today_sessions,
            "waiting_sessions": waiting_sessions,
            "handoff_sessions": waiting_sessions,
            "in_progress_sessions": int(
                counts["in_progress_sessions"]
            ),
            "mine_sessions": int(counts["mine_sessions"]),
            "closed_sessions": int(counts["closed_sessions"]),
            "intent_counts": intent_counts,
        }
    )
    return {
        "sessions": sessions,
        "stats": stats,
        "workflow_ready": workflow_ready,
    }


@router.get("/admin/sessions")
def admin_sessions(
    view: str = "waiting",
    admin_id: int | None = None,
    x_admin_bypass: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    _require_admin(x_admin_bypass)
    if view not in ADMIN_SESSION_VIEWS:
        raise HTTPException(status_code=422, detail="invalid_session_view")
    if view == "mine" and admin_id is None:
        raise HTTPException(status_code=422, detail="admin_id_required")

    empty_stats = {
        "today_sessions": 0,
        "waiting_sessions": 0,
        "handoff_sessions": 0,
        "in_progress_sessions": 0,
        "mine_sessions": 0,
        "closed_sessions": 0,
        "intent_counts": {},
    }
    conn = engine.deps.conn_factory()
    if conn is None:
        return {
            "sessions": [],
            "stats": empty_stats,
            "workflow_ready": False,
        }
    try:
        return _admin_sessions_response(
            conn, view, admin_id, empty_stats
        )
    finally:
        conn.close()


@router.post("/admin/session-action")
def admin_session_action(
    req: AdminSessionAction,
    x_admin_bypass: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    _require_admin(x_admin_bypass)
    if req.action not in ADMIN_SESSION_ACTIONS:
        raise HTTPException(
            status_code=422, detail="invalid_session_action"
        )

    conn = engine.deps.conn_factory()
    if conn is None:
        return {"session": None, "workflow_ready": False}
    try:
        if not _workflow_ready(conn):
            raise HTTPException(
                status_code=503,
                detail="chat_workflow_migration_required",
            )

        with _admin_transaction(conn):
            action = {
                "claim": chat_repo.claim_session,
                "close": chat_repo.close_session,
                "reopen": chat_repo.reopen_session,
            }[req.action]
            session = action(conn, req.session_id, req.admin_id)
            if session is None:
                existing = chat_repo.get_session(conn, req.session_id)
                if existing is None:
                    raise HTTPException(
                        status_code=404, detail="session_not_found"
                    )
                raise HTTPException(
                    status_code=409,
                    detail="session_transition_conflict",
                )
        return {
            "session": _serialize_admin_session(session),
            "workflow_ready": True,
        }
    finally:
        conn.close()


@router.post("/admin/reply")
def admin_reply(
    req: AdminReply,
    x_admin_bypass: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    _require_admin(x_admin_bypass)
    content = req.content.strip()
    if not content:
        raise HTTPException(status_code=422, detail="content_required")

    conn = engine.deps.conn_factory()
    if conn is None:
        return {
            "message_id": None,
            "session": None,
            "workflow_ready": False,
        }

    try:
        workflow_ready = _workflow_ready(conn)
        with _admin_transaction(conn):
            session = chat_repo.get_session(conn, req.session_id)
            if session is None:
                raise HTTPException(
                    status_code=404, detail="session_not_found"
                )
            if session.get("status") == "closed":
                raise HTTPException(
                    status_code=409, detail="session_closed"
                )

            if workflow_ready:
                claimed = chat_repo.claim_session(
                    conn,
                    req.session_id,
                    req.admin_id,
                    "reply_auto_assigned",
                )
                if claimed is None:
                    raise HTTPException(
                        status_code=409,
                        detail="session_assigned_elsewhere",
                    )

            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    INSERT INTO chat_messages
                        (session_id, sender, content, admin_id)
                    VALUES (%s, 'agent', %s, %s)
                    """,
                    (req.session_id, content, req.admin_id),
                )
                message_id = cursor.lastrowid

            if workflow_ready:
                chat_repo.record_session_event(
                    conn,
                    req.session_id,
                    req.admin_id,
                    "replied",
                    "in_progress",
                    "in_progress",
                    {"message_id": message_id},
                )

            updated_session = chat_repo.get_session(
                conn, req.session_id
            )
        return {
            "message_id": message_id,
            "session": (
                _serialize_admin_session(updated_session)
                if updated_session
                else None
            ),
            "workflow_ready": workflow_ready,
        }
    finally:
        conn.close()


@router.post("/knowledge/index")
def knowledge_index(
    source: str = "all",
    x_admin_bypass: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    _require_admin(x_admin_bypass)
    from app.knowledge.indexer import reindex

    conn = None
    try:
        conn = engine.deps.conn_factory()
        indexed_count = reindex(engine.deps.store, conn, source)
    except Exception as error:
        import traceback

        raise HTTPException(
            status_code=500,
            detail={
                "error": type(error).__name__,
                "message": str(error)[:500],
                "trace": traceback.format_exc()[-800:],
            },
        )
    finally:
        if conn:
            conn.close()
    return {"status": "ok", "indexed_count": indexed_count}


@router.get("/debug/db")
def debug_db(
    x_admin_bypass: str | None = Header(default=None),
    engine=Depends(deps_mod.get_engine),
):
    """Admin-only: attempt a MySQL connection and report the exact error."""
    _require_admin(x_admin_bypass)
    settings = get_settings()
    conn = None
    try:
        conn = engine.deps.conn_factory()
        with conn.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) AS c FROM banh")
            product_count = cursor.fetchone()["c"]
        return {
            "db": "ok",
            "host": settings.mysql_host,
            "port": settings.mysql_port,
            "ssl": settings.mysql_ssl,
            "banh_count": product_count,
        }
    except Exception as error:
        databases = []
        product_tables = []
        try:
            import pymysql

            connection_kwargs = {
                "host": settings.mysql_host,
                "port": settings.mysql_port,
                "user": settings.mysql_user,
                "password": settings.mysql_password,
                "charset": "utf8mb4",
                "cursorclass": pymysql.cursors.DictCursor,
                "autocommit": True,
            }
            if settings.mysql_ssl_ca:
                connection_kwargs["ssl"] = {
                    "ca": settings.mysql_ssl_ca
                }
            elif settings.mysql_ssl:
                connection_kwargs["ssl"] = {}
            fallback_conn = pymysql.connect(**connection_kwargs)
            with fallback_conn.cursor() as cursor:
                cursor.execute("SHOW DATABASES")
                databases = [
                    list(row.values())[0] for row in cursor.fetchall()
                ]
                for database in databases:
                    if database in {
                        "information_schema",
                        "performance_schema",
                        "mysql",
                        "sys",
                    }:
                        continue
                    try:
                        cursor.execute(
                            f"SELECT COUNT(*) c FROM `{database}`.banh"
                        )
                        product_tables.append(
                            {database: cursor.fetchone()["c"]}
                        )
                    except Exception:
                        pass
            fallback_conn.close()
        except Exception as fallback_error:
            databases = [
                "list_failed: "
                f"{type(fallback_error).__name__}: "
                f"{str(fallback_error)[:200]}"
            ]
        return {
            "db": "fail",
            "host": settings.mysql_host,
            "port": settings.mysql_port,
            "ssl": settings.mysql_ssl,
            "error": type(error).__name__,
            "message": str(error)[:500],
            "databases": databases,
            "banh_table_found_in": product_tables,
        }
    finally:
        if conn:
            conn.close()


@router.get("/debug/models")
def debug_models(x_admin_bypass: str | None = Header(default=None)):
    """Admin-only: list configured Gemini models."""
    _require_admin(x_admin_bypass)
    settings = get_settings()
    try:
        import google.generativeai as genai

        genai.configure(api_key=settings.gemini_api_key)
        embedding_models = []
        generation_models = []
        for model in genai.list_models():
            methods = getattr(model, "supported_generation_methods", [])
            if "embedContent" in methods:
                embedding_models.append(model.name)
            if "generateContent" in methods:
                generation_models.append(model.name)
        return {
            "configured_embedding": settings.embedding_model,
            "configured_llm": settings.llm_model,
            "embedContent_models": embedding_models,
            "generateContent_models": generation_models[:20],
        }
    except Exception as error:
        return {
            "error": type(error).__name__,
            "message": str(error)[:500],
        }
