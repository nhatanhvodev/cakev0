HANDOFF_KEYWORDS = ["khiếu nại", "hoàn tiền gấp", "gặp quản lý"]
HANDOFF_INTENTS = {"complaint", "handoff_request"}


def decide_handoff(state: dict, threshold: float) -> tuple[bool, list[str]]:
    """Multi-factor handoff policy: intent, confidence, keyword, retry_count."""
    reasons = []
    if state.get("intent") in HANDOFF_INTENTS:
        reasons.append("intent_triggers_handoff")
    if state.get("confidence", 1.0) < threshold:
        reasons.append(f"low_confidence_{state.get('confidence')}")
    low = state.get("query", "").lower()
    if any(k in low for k in HANDOFF_KEYWORDS):
        reasons.append("keyword_match")
    if state.get("retry_count", 0) >= 2:
        reasons.append(f"max_retries_{state.get('retry_count')}")
    return bool(reasons), reasons


def handoff_node(deps, state):
    ok, reasons = decide_handoff(state, deps.settings.handoff_confidence_threshold)
    intent = state.get("intent")
    draft = ""
    if intent != "handoff_request":
        draft = deps.llm.generate(
            "Bạn là trợ lý CSKH. Viết draft trả lời lịch sự cho nhân viên tham khảo (tiếng Việt, ngắn).",
            f"Khách nói: {state['query']}")
    session = state.get("context", {}).get("session") or {}
    priority = "high" if intent == "complaint" else "medium"
    conn = deps.conn_factory()
    if conn is not None:
        try:
            if session.get("id"):
                from app.db import ticket_repo
                ticket_repo.create_ticket(conn, session["id"],
                                          subject=state["query"][:200], priority=priority, draft_response=draft)
        finally:
            conn.close()
    from app.services.notify import notify_handoff
    notify_handoff(deps.settings, session.get("id", "?"), state["query"], reasons, priority)
    return {"should_handoff": ok, "handoff_reasons": reasons,
            "response": "Mình đã ghi nhận và chuyển cho nhân viên hỗ trợ. Bạn chờ trong ít phút nhé, "
                        "hoặc gọi hotline 0901 234 567."}
