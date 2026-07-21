import hashlib, hmac, json
from app.channels.messenger import verify_signature, extract_events


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
