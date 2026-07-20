from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def _eng(store, replies):
    return MultiAgentEngine(EngineDeps(llm=FakeLLM(replies), store=store,
                                       settings=get_settings(), conn_factory=lambda: None))

def test_chitchat_flow(fake_store):
    eng = _eng(fake_store, ['{"intent": "chitchat", "confidence": 0.95}', "Chào bạn, Gấu Bakery nghe ạ!"])
    r = eng.handle([], "chào shop", {})
    assert r.intent == "chitchat"
    assert "Chào" in r.content
    assert r.handoff is False

def test_faq_flow_with_citation(fake_store):
    fake_store.add("faq", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    eng = _eng(fake_store, ['{"intent": "faq", "confidence": 0.9}',
                            '{"answer": "Giao trong ngày ạ", "confidence": 0.88, "sources": ["faq-1"]}'])
    r = eng.handle([], "ship bao lâu", {})
    assert r.intent == "faq"
    assert r.citations[0]["source"] == "faq-1"
