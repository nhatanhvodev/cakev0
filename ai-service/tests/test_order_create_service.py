import json
from app.services import order_create_service as svc

def test_sign_payload_matches_php_hmac():
    body = json.dumps({"user_id": 1}, ensure_ascii=False, separators=(",", ":"))
    sig = svc.sign_payload(body, "test-secret")
    import hmac, hashlib
    assert sig == hmac.new(b"test-secret", body.encode(), hashlib.sha256).hexdigest()

def test_parse_quantity():
    assert svc.parse_quantity("2 cái") == 2
    assert svc.parse_quantity("lấy 1 nhé") == 1
    assert svc.parse_quantity("bánh kem dâu") == 1  # default

def test_is_confirmation():
    assert svc.is_confirmation("ok chốt đơn")
    assert svc.is_confirmation("đồng ý")
    assert not svc.is_confirmation("khoan đã")

def test_flow_requires_login():
    resp, order, draft = svc.advance_draft(None, {"metadata": None}, "đặt bánh kem", user_id=None)
    assert "đăng nhập" in resp
    assert order is None
