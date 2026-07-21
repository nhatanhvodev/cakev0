import hashlib, hmac, json
from app.channels.messenger import verify_signature, extract_events, build_generic_template


def test_verify_signature():
    body = b'{"a": 1}'
    sig = "sha256=" + hmac.new(b"secret", body, hashlib.sha256).hexdigest()
    assert verify_signature("secret", body, sig)
    assert not verify_signature("secret", body, "sha256=wrong")
    assert not verify_signature("secret", body, None)


def test_extract_events():
    payload = {"entry": [{"messaging": [
        {"sender": {"id": "psid-1"}, "message": {"text": "hi"}},
        {"sender": {"id": "psid-2"}, "postback": {"payload": "x"}}]}]}
    events = extract_events(payload)
    assert events == [{"psid": "psid-1", "text": "hi"}]


def test_build_generic_template():
    tpl = build_generic_template(
        [{"id": 1, "ten_banh": "Bánh kem dâu", "gia": 250000, "hinh_anh": "a.jpg"}],
        "https://cake-i8l0.onrender.com/cakev0")
    el = tpl["attachment"]["payload"]["elements"][0]
    assert el["title"] == "Bánh kem dâu"
    assert "250.000" in el["subtitle"]
    assert el["default_action"]["url"].startswith("https://")
