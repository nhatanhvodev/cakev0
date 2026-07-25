def test_add_and_query(fake_store):
    store = fake_store
    store.add("faq", ["1"], ["giao hàng bao lâu"], [{"category": "shipping"}])
    docs = store.query("faq", "giao hàng bao lâu", top_k=1)
    assert docs[0].text == "giao hàng bao lâu"
    assert docs[0].metadata["category"] == "shipping"


def test_bm25_index_built_on_add(fake_store):
    fake_store.add("products", ["p1", "p2"],
                   ["Bánh kem Chocolate giá 250k", "Bánh mì bơ tỏi giá 50k"],
                   [{"id": 1}, {"id": 2}])
    assert "products" in fake_store._bm25
    assert fake_store._bm25["products"]._bm25 is not None


def test_hybrid_query_returns_results(fake_store):
    fake_store.add("products", ["p1", "p2", "p3"],
                   ["Bánh kem Chocolate", "Tiramisu Matcha", "Bánh su kem"],
                   [{}, {}, {}])
    docs = fake_store.query("products", "Chocolate", top_k=3)
    assert len(docs) >= 1
    ids = [d.id for d in docs]
    assert "p1" in ids


def test_reset_clears_bm25(fake_store):
    fake_store.add("faq", ["f1"], ["test"], [{}])
    assert "faq" in fake_store._bm25
    fake_store.reset("faq")
    assert "faq" not in fake_store._bm25


def test_rrf_fusion_combines_both_sources():
    from app.knowledge.vector_store import _rrf_fuse, RetrievedDoc, _BM25Index
    vec = [RetrievedDoc("a", "text a", {}, 0.1),
           RetrievedDoc("b", "text b", {}, 0.2)]
    bm25 = [("b", "text b", {}, 5.0),
            ("c", "text c", {}, 3.0)]
    fused = _rrf_fuse(vec, bm25, top_k=3)
    ids = [d.id for d in fused]
    assert "b" in ids  # b appears in both → highest RRF score
    assert "a" in ids
    assert "c" in ids
