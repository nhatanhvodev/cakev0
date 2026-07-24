from dataclasses import dataclass
from typing import Callable, Any
from app.config import Settings
from app.knowledge.vector_store import VectorStore
from app.llm import LLMClient

@dataclass
class EngineDeps:
    llm: LLMClient
    store: VectorStore
    settings: Settings
    conn_factory: Callable[[], Any]
