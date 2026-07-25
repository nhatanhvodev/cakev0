import json
import re

from langgraph.graph import StateGraph, END
from app.engines.base import EngineDeps
from app.engines.multiagent.state import AgentState
from app.engines.multiagent import router as router_mod
from app.models.chat import EngineReply
from app.nlp.normalizer import normalize

RETRIEVAL_INTENTS = {"faq", "catalog_search", "product_recommend",
                     "policy_shipping", "policy_payment", "policy_return"}
ACTION_INTENTS = {"order_status", "order_create"}
HANDOFF_INTENTS = {"complaint", "handoff_request"}
EXIT_DRAFT_WORDS = {"thôi", "thoi", "hủy", "huy", "hủy đơn", "huy don", "dừng", "dung",
                    "quên", "quen", "bỏ", "bo", "cancel"}
_EXIT_TOKEN_RE = re.compile(r"[\wÀ-ỹ]+")


def _has_exit_word(msg: str) -> bool:
    low = msg.lower().strip()
    if low in EXIT_DRAFT_WORDS:
        return True
    tokens = _EXIT_TOKEN_RE.findall(low)
    if any(t in EXIT_DRAFT_WORDS for t in tokens):
        return True
    return any(w in low for w in EXIT_DRAFT_WORDS if " " in w)


def _open_draft(session) -> bool:
    meta = session.get("metadata")
    meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
    d = meta.get("order_draft") or {}
    return bool(d.get("items")) or d.get("step") not in (None, "items")

def build_graph(deps: EngineDeps):
    from app.engines.multiagent.retrieval import retrieval_node
    from app.engines.multiagent.action import action_node
    from app.engines.multiagent.handoff import handoff_node
    from app.engines.multiagent.aggregate import aggregate_node

    def normalize_node(state: AgentState):
        # settings.enable_normalizer=False → ablation B′ (spec §9): thêm field
        # `enable_normalizer: bool = True` vào Settings (Task 2)
        if not getattr(deps.settings, "enable_normalizer", True):
            return {"normalized_query": state["query"]}
        return {"normalized_query": normalize(state["query"])}

    def router_node(state: AgentState):
        if state.get("intent") == "order_create":
            return {"intent": "order_create", "confidence": 1.0}
        history = state.get("history") or []
        intent, conf = router_mod.classify_intent(deps.llm, state["normalized_query"], history)
        return {"intent": intent, "confidence": conf}

    def _history_block(state: AgentState) -> str:
        history = state.get("history") or []
        if not history:
            return ""
        recent = history[-6:]
        return "LỊCH SỬ:\n" + "\n".join(f"{m['sender']}: {m['content']}" for m in recent) + "\n\n"

    def chitchat_node(state: AgentState):
        hist = _history_block(state)
        resp = deps.llm.generate(
            "Bạn là trợ lý Gấu Bakery, đáp ngắn gọn thân thiện tiếng Việt.",
            f"{hist}KHÁCH: {state['query']}")
        return {"response": resp}

    def rewrite_node(state: AgentState):
        new_q = deps.llm.generate(
            "Viết lại câu truy vấn tiếng Việt rõ nghĩa hơn, chỉ trả câu viết lại.",
            state["normalized_query"])
        return {"normalized_query": new_q.strip(), "retry_count": state.get("retry_count", 0) + 1}

    def after_retrieval(state: AgentState) -> str:
        if state.get("needs_retry") and state.get("retry_count", 0) < 2:
            return "rewrite"
        return "aggregate"

    def route(state: AgentState) -> str:
        i = state["intent"]
        if i in HANDOFF_INTENTS:
            return "handoff"
        if i in ACTION_INTENTS:
            return "action"
        if i == "chitchat":
            return "chitchat"
        return "retrieval"

    g = StateGraph(AgentState)
    g.add_node("normalize", normalize_node)
    g.add_node("router", router_node)
    g.add_node("retrieval", lambda s: retrieval_node(deps, s))
    g.add_node("rewrite", rewrite_node)
    g.add_node("action", lambda s: action_node(deps, s))
    g.add_node("chitchat", chitchat_node)
    g.add_node("handoff", lambda s: handoff_node(deps, s))
    g.add_node("aggregate", lambda s: aggregate_node(deps, s))
    g.set_entry_point("normalize")
    g.add_edge("normalize", "router")
    g.add_conditional_edges("router", route,
        {"retrieval": "retrieval", "action": "action", "chitchat": "chitchat", "handoff": "handoff"})
    g.add_conditional_edges("retrieval", after_retrieval,
        {"rewrite": "rewrite", "aggregate": "aggregate"})
    g.add_edge("rewrite", "retrieval")
    g.add_edge("action", "aggregate")
    g.add_edge("chitchat", "aggregate")
    g.add_edge("handoff", "aggregate")
    g.add_edge("aggregate", END)
    return g.compile()

class MultiAgentEngine:
    def __init__(self, deps: EngineDeps):
        self.deps = deps
        self._graph = build_graph(deps)

    def handle(self, history, user_message, context) -> EngineReply:
        state: AgentState = {"query": user_message, "history": history, "context": context,
                             "retry_count": 0, "citations": [], "products": [],
                             "should_handoff": False, "handoff_reasons": []}
        session = (context or {}).get("session") or {}
        if _open_draft(session) and not _has_exit_word(user_message):
            state["intent"] = "order_create"
            state["confidence"] = 1.0
        out = self._graph.invoke(state)
        return EngineReply(
            type=out.get("action_result", {}).get("type", "text"),
            content=out.get("response", ""),
            citations=out.get("citations", []),
            products=out.get("products", []),
            intent=out.get("intent", "unknown"),
            confidence=out.get("confidence", 0.0),
            handoff=out.get("should_handoff", False),
            order=out.get("action_result", {}).get("order"),
            retrieved_docs=out.get("retrieved_docs", []))
