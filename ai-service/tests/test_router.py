from app.engines.multiagent.router import classify_intent, keyword_fallback
from app.llm import FakeLLM

def test_classify_via_llm_json():
    llm = FakeLLM(['{"intent": "order_status", "confidence": 0.92}'])
    assert classify_intent(llm, "đơn 123 đến đâu rồi") == ("order_status", 0.92)

def test_classify_invalid_json_uses_keyword_fallback():
    llm = FakeLLM(["xin chao"])
    intent, conf = classify_intent(llm, "cho gặp người thật")
    assert intent == "handoff_request"

def test_keyword_fallback_defaults_faq():
    assert keyword_fallback("bánh này ngon không")[0] == "faq"
