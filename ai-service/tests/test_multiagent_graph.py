from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def _eng(store, replies):
    return MultiAgentEngine(EngineDeps(llm=FakeLLM(replies), store=store,
                                       settings=get_settings(), conn_factory=lambda: None))

def test_chitchat_flow(fake_store):
    # "chào" matches keyword → router skipped (keyword-first cache), only chitchat LLM reply needed
    eng = _eng(fake_store, ["Chào bạn, Gấu Bakery nghe ạ!"])
    r = eng.handle([], "chào shop", {})
    assert r.intent == "chitchat"
    assert "Chào" in r.content
    assert r.handoff is False

def test_faq_flow_with_citation(fake_store):
    # "ship bao lâu" normalizes to "giao hàng bao lâu" → keyword matches policy_shipping (0.55)
    # Router skipped (keyword-first). Only retrieval LLM reply needed.
    fake_store.add("policies", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    eng = _eng(fake_store, ['{"answer": "Giao trong ngày ạ", "confidence": 0.88, "sources": ["faq-1"]}'])
    r = eng.handle([], "ship bao lâu", {})
    assert r.intent == "policy_shipping"
    assert r.citations[0]["source"] == "faq-1"


def test_history_passed_to_chitchat(fake_store):
    eng = _eng(fake_store, ["Dạ vâng ạ, Gấu Bakery xin phục vụ!"])
    history = [{"sender": "customer", "content": "có bánh kem không"},
               {"sender": "bot", "content": "Dạ shop có bánh kem Chocolate ạ"}]
    r = eng.handle(history, "cảm ơn shop", {})
    assert r.intent == "chitchat"
    llm = eng.deps.llm
    assert "có bánh kem không" in llm.calls[0][1]


def test_history_passed_to_retrieval(fake_store):
    fake_store.add("faq", ["faq-1"], ["HOI: giờ mở cửa\nDAP: 8h-21h"], [{}])
    # no keyword match → LLM router called, then retrieval LLM called
    replies = ['{"intent": "faq", "confidence": 0.9}',
               '{"answer": "8h-21h ạ", "confidence": 0.9, "sources": ["faq-1"]}']
    eng = _eng(fake_store, replies)
    history = [{"sender": "customer", "content": "mấy giờ đóng cửa"},
               {"sender": "bot", "content": "Dạ shop đóng 21h ạ"}]
    r = eng.handle(history, "thế ngày lễ thì sao", {})
    # retrieval LLM call (index 1) should contain history
    assert "mấy giờ đóng cửa" in eng.deps.llm.calls[1][1]


def test_normalizer_ablation_off(fake_store):
    # enable_normalizer=False → normalized_query == raw query (no teencode expansion)
    from unittest.mock import patch
    settings = get_settings()
    with patch.object(settings, "enable_normalizer", False):
        # "ship" won't become "giao hàng" → no keyword match → LLM router called
        eng = _eng(fake_store, ['{"intent": "faq", "confidence": 0.8}',
                                '{"answer": "ok", "confidence": 0.8, "sources": []}'])
        r = eng.handle([], "ship bao lâu", {})
        assert r.intent == "faq"  # LLM decides, not keyword "giao hàng"


def test_keyword_first_saves_llm_call(fake_store):
    # "đổi trả" → keyword policy_return, skips router LLM
    fake_store.add("policies", ["pol-1"], ["Đổi trả trong 2 giờ"], [{}])
    eng = _eng(fake_store, ['{"answer": "Đổi trong 2h ạ", "confidence": 0.8, "sources": ["pol-1"]}'])
    r = eng.handle([], "đổi trả bánh", {})
    assert r.intent == "policy_return"
    # only 1 LLM call (retrieval), no router call
    assert len(eng.deps.llm.calls) == 1
