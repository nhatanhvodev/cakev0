from app.engines.multiagent.router import classify_intent, keyword_fallback
from app.llm import FakeLLM


def test_keyword_match_skips_llm():
    llm = FakeLLM([])  # no replies needed — keyword handles it
    assert classify_intent(llm, "đơn 123 đến đâu rồi") == ("order_status", 0.55)
    assert llm.calls == []  # LLM never called


def test_no_keyword_falls_through_to_llm():
    llm = FakeLLM(['{"intent": "product_recommend", "confidence": 0.88}'])
    assert classify_intent(llm, "gợi ý bánh sinh nhật cho bé") == ("product_recommend", 0.88)
    assert len(llm.calls) == 1


def test_classify_invalid_json_uses_keyword_fallback():
    llm = FakeLLM(["xin chao"])
    intent, conf = classify_intent(llm, "cho gặp người thật")
    # "người thật" keyword → handoff_request/0.55, LLM skipped
    assert intent == "handoff_request"
    assert conf == 0.55


def test_keyword_fallback_defaults_faq():
    assert keyword_fallback("bánh này ngon không")[0] == "faq"


def test_history_passed_to_llm():
    llm = FakeLLM(['{"intent": "catalog_search", "confidence": 0.85}'])
    history = [{"sender": "customer", "content": "có bánh kem không"},
               {"sender": "bot", "content": "Dạ shop có bánh kem Chocolate ạ"}]
    intent, conf = classify_intent(llm, "cái đó giá bao nhiêu", history)
    assert intent == "catalog_search"
    assert "LỊCH SỬ" in llm.calls[0][1]
    assert "có bánh kem không" in llm.calls[0][1]
