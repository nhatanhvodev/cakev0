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
app.add_middleware(
    CORSMiddleware,
    allow_origins=[o.strip() for o in settings.cors_origins.split(",")],
    allow_methods=["*"], allow_headers=["*"],
)
app.include_router(chat_router)
app.include_router(messenger_router)

# --- Rate limiting (spec §12): 20 req/min per key on /chat/send -------------
RATE_LIMIT_MAX_REQUESTS = 20
RATE_LIMIT_WINDOW_SECONDS = 60.0
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
        timestamps = [t for t in _rate_limit_state.get(key, []) if now - t < RATE_LIMIT_WINDOW_SECONDS]

        if len(timestamps) >= RATE_LIMIT_MAX_REQUESTS:
            _rate_limit_state[key] = timestamps
            return JSONResponse(status_code=429, content={"error": "rate_limited"})

        timestamps.append(now)
        _rate_limit_state[key] = timestamps

    return await call_next(request)


@app.get("/health")
def health():
    return {"status": "ok", "engine": settings.engine}
