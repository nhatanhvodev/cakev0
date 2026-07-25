from functools import lru_cache
from app.config import get_settings
from app.engines.base import EngineDeps
from app.knowledge.vector_store import VectorStore
from app.llm import build_llm_client, gemini_embed
from app.db import mysql


@lru_cache
def build_deps() -> EngineDeps:
    s = get_settings()
    return EngineDeps(
        llm=build_llm_client(s),
        store=VectorStore(s.chroma_persist_dir, embedding_fn=gemini_embed(s)),
        settings=s,
        conn_factory=lambda: mysql.get_conn(s))


def get_engine():
    s = get_settings()
    d = build_deps()
    if s.engine == "baseline":
        from app.engines.baseline import BaselineEngine
        return BaselineEngine(d)
    if s.engine == "demo":
        from app.engines.demo import DemoEngine
        return DemoEngine(d)
    from app.engines.multiagent.graph import MultiAgentEngine
    return MultiAgentEngine(d)
