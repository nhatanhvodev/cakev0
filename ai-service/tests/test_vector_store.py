def test_add_and_query(fake_store):
    store = fake_store
    store.add("faq", ["1"], ["giao hàng bao lâu"], [{"category": "shipping"}])
    docs = store.query("faq", "giao hàng bao lâu", top_k=1)
    assert docs[0].text == "giao hàng bao lâu"
    assert docs[0].metadata["category"] == "shipping"
