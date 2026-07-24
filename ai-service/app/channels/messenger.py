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


def build_generic_template(products: list[dict], base_url: str) -> dict:
    def vnd(v): return f"{int(v):,}".replace(",", ".") + " VNĐ"
    elements = [{
        "title": p["ten_banh"][:80],
        "subtitle": vnd(p["gia"]),
        "image_url": p.get("hinh_anh") if str(p.get("hinh_anh", "")).startswith("http")
                     else f"{base_url}/{str(p.get('hinh_anh', '')).lstrip('/')}",
        "default_action": {"type": "web_url", "url": f"{base_url}/pages/product.php?id={p['id']}"},
    } for p in products[:10]]
    return {"attachment": {"type": "template",
                           "payload": {"template_type": "generic", "elements": elements}}}


def send_payload(page_token: str, psid: str, message: dict):
    httpx.post(GRAPH_URL, params={"access_token": page_token},
               json={"recipient": {"id": psid}, "message": message}, timeout=10)
