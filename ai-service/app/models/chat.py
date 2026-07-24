from pydantic import BaseModel

class EngineReply(BaseModel):
    type: str = "text"
    content: str = ""
    citations: list[dict] = []
    products: list[dict] = []
    intent: str = "unknown"
    confidence: float = 0.0
    handoff: bool = False
    order: dict | None = None
    retrieved_docs: list[str] = []
