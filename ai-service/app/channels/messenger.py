import hashlib, hmac
import httpx

GRAPH_URL = "https://graph.facebook.com/v21.0/me/messages"


def verify_signature(app_secret: str, body: bytes, header) -> bool:
    if not header or not header.startswith("sha256="):
        return False
    expected = hmac.new(app_secret.encode(), body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, header[len("sha256="):])


def extract_events(payload: dict) -> list[dict]:
    out = []
    for entry in payload.get("entry", []):
        for m in entry.get("messaging", []):
            text = (m.get("message") or {}).get("text")
            psid = (m.get("sender") or {}).get("id")
            if text and psid:
                out.append({"psid": psid, "text": text})
    return out


def send_text(page_token: str, psid: str, text: str):
    httpx.post(GRAPH_URL, params={"access_token": page_token},
               json={"recipient": {"id": psid}, "message": {"text": text[:2000]}}, timeout=10)
