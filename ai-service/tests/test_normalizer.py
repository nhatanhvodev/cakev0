from app.nlp.normalizer import normalize


def test_teencode():
    assert normalize("shop oi co banh sn ko") == "shop ơi có bánh sinh nhật không"


def test_keeps_numbers_and_phone():
    assert "0901234567" in normalize("don cua sdt 0901234567 den dau r")


def test_tmdt_glossary():
    out = normalize("ship COD dc ko, co freeship ko")
    assert "giao hàng" in out and "thanh toán khi nhận hàng" in out


def test_plain_vietnamese_unchanged():
    s = "Bánh kem dâu giá bao nhiêu?"
    assert normalize(s) == s
