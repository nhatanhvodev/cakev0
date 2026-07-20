from app.knowledge.loaders import product_to_doc, load_policy_file, faq_to_doc


def test_product_to_doc():
    doc_id, text, meta = product_to_doc({"id": 5, "ten_banh": "Bánh kem dâu", "loai": "kem",
                                         "gia": 250000, "mo_ta": "Ngọt dịu", "hinh_anh": "x.jpg", "slug": "banh-kem-dau"})
    assert doc_id == "product-5"
    assert "Bánh kem dâu" in text and "250" in text
    assert meta["gia"] == 250000


def test_load_policy_file(tmp_path):
    p = tmp_path / "shipping.md"
    p.write_text("---\npolicy_type: shipping\nurl: /x\n---\n# Giao hàng\nNội dung", encoding="utf-8")
    doc_id, text, meta = load_policy_file(str(p))
    assert meta["policy_type"] == "shipping"
    assert "Nội dung" in text
