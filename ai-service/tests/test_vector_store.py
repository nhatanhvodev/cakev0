from app.knowledge.vector_store import VectorStore


def fake_embed(texts):  # deterministic, không gọi API
    return [[float(len(t) % 7), float(sum(map(ord, t)) % 11), 1.0] for t in texts]


def test_add_and_query(tmp_path):
    store = VectorStore(str(tmp_path), embedding_fn=fake_embed)
    store.add("faq", ["1"], ["giao hàng bao lâu"], [{"category": "shipping"}])
    docs = store.query("faq", "giao hàng bao lâu", top_k=1)
    assert docs[0].text == "giao hàng bao lâu"
    assert docs[0].metadata["category"] == "shipping"
