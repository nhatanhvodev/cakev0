from typing import TypedDict, Any

class AgentState(TypedDict, total=False):
    query: str
    normalized_query: str
    intent: str
    confidence: float
    retrieved_docs: list
    products: list
    action_result: dict
    response: str
    citations: list
    should_handoff: bool
    handoff_reasons: list
    retry_count: int
    history: list
    context: dict
