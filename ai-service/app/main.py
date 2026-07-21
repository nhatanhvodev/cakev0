from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
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

@app.get("/health")
def health():
    return {"status": "ok", "engine": settings.engine}
