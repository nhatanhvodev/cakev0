from app.engines.multiagent.handoff import decide_handoff


def test_intent_trigger():
    ok, reasons = decide_handoff({"intent": "complaint", "confidence": 0.9,
                                  "query": "bánh hỏng", "retry_count": 0}, 0.6)
    assert ok and "intent_triggers_handoff" in reasons


def test_confidence_trigger():
    ok, reasons = decide_handoff({"intent": "faq", "confidence": 0.3,
                                  "query": "x", "retry_count": 0}, 0.6)
    assert ok and any(r.startswith("low_confidence") for r in reasons)


def test_keyword_trigger():
    ok, reasons = decide_handoff({"intent": "faq", "confidence": 0.9,
                                  "query": "tôi muốn gặp quản lý", "retry_count": 0}, 0.6)
    assert ok and "keyword_match" in reasons


def test_no_trigger():
    ok, _ = decide_handoff({"intent": "faq", "confidence": 0.9,
                            "query": "giao lâu không", "retry_count": 0}, 0.6)
    assert not ok
