import json
import time

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from app.config import get_settings
from app.api.chat import router as chat_router
from app.api.messenger import router as messenger_router

app = FastAPI(title="Gau Bakery AI Service")
settings = get_settings()

# --- Rate limiting (spec §12): 20 req/min per key on /chat/send -------------
RATE_LIMIT_MAX_REQUESTS = 20
RATE_LIMIT_WINDOW_SECONDS = 60.0
RATE_LIMIT_MAX_KEYS = 10000  # cap dict size to prevent unbounded growth
_rate_limit_state: dict[str, list[float]] = {}


def _reset_rate_limit() -> None:
    """Clear all rate-limit counters. Used by tests to isolate runs."""
    _rate_limit_state.clear()


def _rate_limit_key(request: Request, body: dict) -> str:
    session_id = body.get("session_id")
    if session_id:
        return f"session:{session_id}"
    guest_token = body.get("guest_token")
    if guest_token:
        return f"guest:{guest_token}"
    forwarded = request.headers.get("x-forwarded-for")
    if forwarded:
        return f"ip:{forwarded.split(',')[0].strip()}"
    client = request.client
    return f"ip:{client.host if client else 'unknown'}"


def _sweep_rate_limit(now: float) -> None:
    """Drop keys whose window has fully expired. Bounds memory growth."""
    stale = [k for k, ts in _rate_limit_state.items()
             if not ts or now - ts[-1] >= RATE_LIMIT_WINDOW_SECONDS]
    for k in stale:
        _rate_limit_state.pop(k, None)


@app.middleware("http")
async def rate_limit_middleware(request: Request, call_next):
    if request.url.path == "/chat/send" and request.method == "POST":
        body_bytes = await request.body()

        async def _receive():
            return {"type": "http.request", "body": body_bytes, "more_body": False}

        request._receive = _receive  # allow downstream handlers to re-read the body

        try:
            body = json.loads(body_bytes) if body_bytes else {}
        except (json.JSONDecodeError, UnicodeDecodeError):
            body = {}

        key = _rate_limit_key(request, body)
        now = time.time()
        if len(_rate_limit_state) > RATE_LIMIT_MAX_KEYS:
            _sweep_rate_limit(now)
        timestamps = [t for t in _rate_limit_state.get(key, []) if now - t < RATE_LIMIT_WINDOW_SECONDS]

        if len(timestamps) >= RATE_LIMIT_MAX_REQUESTS:
            _rate_limit_state[key] = timestamps
            resp = JSONResponse(status_code=429, content={"error": "rate_limited"})
            # Add CORS header manually — rate-limit runs before CORSMiddleware
            origin = request.headers.get("origin")
            allowed = [o.strip() for o in settings.cors_origins.split(",") if o.strip()]
            if origin and (origin in allowed or "*" in allowed):
                resp.headers["Access-Control-Allow-Origin"] = origin
            return resp

        timestamps.append(now)
        _rate_limit_state[key] = timestamps

    return await call_next(request)


# CORS after rate limit so preflight is still rate-limit exempt (not POST /chat/send)
_allowed_origins = [o.strip() for o in settings.cors_origins.split(",") if o.strip()]
if not _allowed_origins:
    # Empty config = block browsers; server-to-server callers unaffected.
    _allowed_origins = []
app.add_middleware(
    CORSMiddleware,
    allow_origins=_allowed_origins,
    allow_methods=["*"], allow_headers=["*"],
)
app.include_router(chat_router)
app.include_router(messenger_router)


@app.on_event("startup")
def _auto_reindex_if_empty():
    """Free-tier Render has no persistent disk, so the Chroma index is wiped on
    every container start (redeploy / sleep-wake). Rebuild it once at startup if
    the products collection is empty. Best-effort: never block the service boot."""
    try:
        from app import deps as deps_mod
        from app.knowledge.indexer import reindex
        d = deps_mod.build_deps()
        if d.store.count("products") > 0:
            return
        conn = d.conn_factory()
        try:
            n = reindex(d.store, conn, "all")
            print(f"[startup] auto-reindex complete: {n} docs")
        finally:
            if conn:
                conn.close()
    except Exception as e:
        print(f"[startup] auto-reindex skipped: {type(e).__name__}: {e}")


@app.get("/health")
def health():
    return {"status": "ok", "engine": settings.engine,
            "products_indexed": _safe_count()}


@app.get("/debug/config")
def debug_config():
    return {
        "llm_provider": settings.llm_provider,
        "llm_model": settings.llm_model,
        "deepseek_key_set": bool(settings.deepseek_api_key),
        "deepseek_key_len": len(settings.deepseek_api_key),
        "gemini_key_set": bool(settings.gemini_api_key),
    }


@app.get("/debug/llm-test")
def debug_llm_test():
    import traceback
    try:
        from app.llm import build_llm_client
        client = build_llm_client(settings)
        result = client.generate("Reply with exactly: OK", "test")
        return {"status": "ok", "response": result[:200]}
    except Exception as e:
        return {"status": "error", "type": type(e).__name__,
                "message": str(e)[:500], "trace": traceback.format_exc()[-1000:]}


def _safe_count() -> int:
    try:
        from app import deps as deps_mod
        return deps_mod.build_deps().store.count("products")
    except Exception:
        return -1
