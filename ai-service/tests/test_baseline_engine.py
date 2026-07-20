from app.engines.baseline import BaselineEngine, parse_llm_json
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def _deps(store, replies):
    return EngineDeps(llm=FakeLLM(replies), store=store, settings=get_settings(), conn_factory=lambda: None)

def test_parse_llm_json_extracts_fields():
    out = parse_llm_json('{"answer": "Giao trong ngày", "confidence": 0.9, "sources": ["faq-1"]}')
    assert out == {"answer": "Giao trong ngày", "confidence": 0.9, "sources": ["faq-1"]}

def test_parse_llm_json_fallback_plain_text():
    out = parse_llm_json("Giao trong ngày nhé")
    assert out["answer"] == "Giao trong ngày nhé"
    assert out["confidence"] == 0.5

def test_baseline_answers_with_citation(fake_store):
    fake_store.add("faq", ["faq-1"], ["HOI: giao bao lâu\nDAP: trong ngày"], [{"category": "shipping"}])
    eng = BaselineEngine(_deps(fake_store, ['{"answer": "Trong ngày ạ", "confidence": 0.9, "sources": ["faq-1"]}']))
    reply = eng.handle([], "giao hàng bao lâu", {})
    assert reply.content == "Trong ngày ạ"
    assert reply.citations[0]["source"] == "faq-1"
    assert reply.handoff is False

def test_baseline_low_confidence_triggers_handoff(fake_store):
    eng = BaselineEngine(_deps(fake_store, ['{"answer": "Không rõ", "confidence": 0.3, "sources": []}']))
    reply = eng.handle([], "hỏi khó", {})
    assert reply.handoff is True
