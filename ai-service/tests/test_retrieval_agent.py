from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.multiagent.retrieval import _promoted_ids
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings


def _eng(store, replies, conn_factory=lambda: None):
    return MultiAgentEngine(EngineDeps(llm=FakeLLM(replies), store=store,
                                       settings=get_settings(), conn_factory=conn_factory))


def test_low_confidence_retries_then_handoff(fake_store):
    replies = ['{"intent": "faq", "confidence": 0.9}',
               '{"answer": "?", "confidence": 0.2, "sources": []}',   # lần 1 kém
               "câu hỏi giao hàng viết rõ",                            # rewrite
               '{"answer": "?", "confidence": 0.2, "sources": []}',   # lần 2 kém
               "câu hỏi giao hàng viết rõ hơn",                        # rewrite 2
               '{"answer": "?", "confidence": 0.2, "sources": []}']   # lần 3 kém → handoff
    eng = _eng(fake_store, replies)
    r = eng.handle([], "abcxyz", {})
    assert r.handoff is True


def test_high_confidence_no_retry(fake_store):
    # "ship bao lâu" normalizes to "giao hàng bao lâu" → keyword policy_shipping (0.55), router skipped
    fake_store.add("policies", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    replies = ['{"answer": "Giao trong ngày ạ", "confidence": 0.88, "sources": ["faq-1"]}']
    eng = _eng(fake_store, replies)
    r = eng.handle([], "ship bao lâu", {})
    assert r.handoff is False
    assert r.citations[0]["source"] == "faq-1"


def test_retry_recovers_before_max(fake_store):
    replies = ['{"intent": "faq", "confidence": 0.9}',
               '{"answer": "?", "confidence": 0.2, "sources": []}',       # lần 1 kém
               "câu hỏi viết rõ hơn",                                      # rewrite
               '{"answer": "Ok ạ", "confidence": 0.8, "sources": []}']    # lần 2 tốt
    eng = _eng(fake_store, replies)
    r = eng.handle([], "abcxyz", {})
    assert r.handoff is False
    assert r.content == "Ok ạ"


class _FakeCursor:
    def __init__(self, rows):
        self._rows = rows

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False

    def execute(self, sql, params=None):
        pass

    def fetchall(self):
        return self._rows


class _FakeConn:
    def __init__(self, rows):
        self._rows = rows
        self.closed = False

    def cursor(self):
        return _FakeCursor(self._rows)

    def close(self):
        self.closed = True


class _BrokenConn:
    def cursor(self):
        raise RuntimeError("table `promotions` doesn't exist")

    def close(self):
        pass


def test_promoted_ids_returns_set_of_banh_ids():
    conn = _FakeConn([{"banh_id": 3}, {"banh_id": 7}])
    assert _promoted_ids(conn) == {3, 7}


def test_promoted_ids_returns_empty_set_on_error():
    assert _promoted_ids(_BrokenConn()) == set()


def test_product_recommend_reranks_promoted_products_first(fake_store):
    fake_store.add("products", ["product-1", "product-2", "product-3"],
                    ["SAN PHAM: Banh A\nLOAI: kem\nGIA: 100000 VND",
                     "SAN PHAM: Banh B\nLOAI: kem\nGIA: 120000 VND",
                     "SAN PHAM: Banh C\nLOAI: kem\nGIA: 150000 VND"],
                    [{"id": 1}, {"id": 2}, {"id": 3}])
    replies = ['{"intent": "product_recommend", "confidence": 0.9}',
               '{"answer": "Đây ạ", "confidence": 0.9, "sources": []}']
    conn = _FakeConn([{"banh_id": 3}])
    eng = _eng(fake_store, replies, conn_factory=lambda: conn)
    r = eng.handle([], "gợi ý bánh kem", {})
    assert r.products
    assert r.products[0]["id"] == 3
    assert conn.closed is True


def test_product_recommend_without_conn_does_not_crash(fake_store):
    fake_store.add("products", ["product-1"],
                    ["SAN PHAM: Banh A\nLOAI: kem\nGIA: 100000 VND"], [{"id": 1}])
    replies = ['{"intent": "product_recommend", "confidence": 0.9}',
               '{"answer": "Đây ạ", "confidence": 0.9, "sources": []}']
    eng = _eng(fake_store, replies, conn_factory=lambda: None)
    r = eng.handle([], "gợi ý bánh kem", {})
    assert r.handoff is False
    assert len(r.products) == 1
