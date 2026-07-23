import hashlib
import hmac
import json
import re

import httpx

from app.engines.multiagent.action import extract_phone

CONFIRM_WORDS = {"đồng ý", "dong y", "ok", "oke", "okay", "xác nhận", "xac nhan",
                  "chốt", "chot", "chốt đơn", "chot don", "đúng rồi", "dung roi", "yes", "y"}
_CONFIRM_TOKEN_RE = re.compile(r"[\wÀ-ỹ]+")
LOGIN_MSG = ("Để đặt bánh trong chat, bạn vui lòng đăng nhập tài khoản Gấu Bakery trước nhé: "
             "/cakev0/pages/login.php")


def sign_payload(body: str, secret: str) -> str:
    return hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()


def parse_quantity(text: str) -> int:
    """First standalone integer 1-99. Larger numbers cap to max 20."""
    m = re.search(r"\b(\d{1,3})\b", text)
    return max(1, min(20, int(m.group(1)))) if m else 1


_ORDER_STOPWORDS = {
    "đặt", "dat", "mua", "cho", "mình", "minh", "em", "anh", "chị", "chi",
    "lấy", "lay", "muốn", "muon", "giúp", "giup", "làm", "lam", "nhé", "nhe",
    "ạ", "a", "với", "voi", "cái", "cai", "chiếc", "chiec", "hộp", "hop",
    "phần", "phan", "ổ", "o", "bánh", "banh",
}


def extract_product_keyword(text: str) -> str:
    """Strip digits + common order verbs/classifiers so LIKE can match a product name."""
    no_digits = re.sub(r"\d+", " ", text)
    tokens = [t for t in re.findall(r"[\wÀ-ỹ]+", no_digits) if t.lower() not in _ORDER_STOPWORDS]
    return " ".join(tokens).strip()


def is_confirmation(text: str) -> bool:
    """Token/phrase match — avoids 'ok' inside 'lookbook' false-positive."""
    low = text.lower().strip()
    if low in CONFIRM_WORDS:
        return True
    tokens = _CONFIRM_TOKEN_RE.findall(low)
    if any(t in CONFIRM_WORDS for t in tokens):
        return True
    # multi-word confirms (e.g. "đồng ý", "xác nhận")
    return any(w in low for w in CONFIRM_WORDS if " " in w)


def fmt_vnd(v) -> str:
    return f"{int(v):,}".replace(",", ".") + " VNĐ"


def _summary(draft: dict) -> str:
    lines = [f"- {i['ten_banh']} x{i['quantity']} = {fmt_vnd(i['gia'] * i['quantity'])}"
             for i in draft["items"]]
    total = sum(i["gia"] * i["quantity"] for i in draft["items"])
    return ("Xác nhận đơn hàng (COD):\n" + "\n".join(lines) +
            f"\nTổng: {fmt_vnd(total)}\nNgười nhận: {draft['recipient_name']}"
            f"\nSĐT: {draft['phone']}\nĐịa chỉ: {draft['address']}"
            "\n\nBạn trả lời \"đồng ý\" để chốt đơn, hoặc nhắn nội dung cần sửa.")


def submit_order(settings, payload: dict) -> dict:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
    r = httpx.post(settings.internal_order_api_url, content=body,
                    headers={"Content-Type": "application/json",
                             "X-Internal-Signature": sign_payload(body, settings.internal_api_secret)},
                    timeout=15)
    return r.json()


def advance_draft(deps, session: dict, message: str, user_id) -> tuple[str, dict | None, dict]:
    if not user_id:
        return LOGIN_MSG, None, {}
    meta = session.get("metadata")
    meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
    draft = meta.get("order_draft") or {"step": "items", "items": []}

    step = draft["step"]
    if step == "items":
        conn = deps.conn_factory()
        from app.db import catalog_repo
        keyword = extract_product_keyword(message)
        found = catalog_repo.search_products_like(conn, keyword) if conn and keyword else []
        if conn:
            conn.close()
        if not found:
            return ("Mình chưa tìm thấy bánh đó. Bạn cho mình tên bánh cụ thể hơn nhé "
                     "(ví dụ: bánh kem dâu)."), None, draft
        p = found[0]
        draft["items"] = [{"banh_id": p["id"], "ten_banh": p["ten_banh"],
                            "gia": float(p["gia"]), "quantity": parse_quantity(message)}]
        draft["step"] = "recipient"
        return (f"Bạn chọn {p['ten_banh']} x{draft['items'][0]['quantity']} "
                f"({fmt_vnd(p['gia'])}/cái). Cho mình xin tên người nhận nhé."), None, draft
    if step == "recipient":
        draft["recipient_name"] = message.strip()[:255]
        draft["step"] = "phone"
        return "Số điện thoại người nhận là gì ạ?", None, draft
    if step == "phone":
        phone = extract_phone(message)
        if not phone:
            return "Số điện thoại chưa hợp lệ, bạn nhập lại giúp mình (vd 0901234567).", None, draft
        draft["phone"] = phone
        draft["step"] = "address"
        return "Địa chỉ giao bánh ở đâu ạ?", None, draft
    if step == "address":
        draft["address"] = message.strip()
        draft["step"] = "confirm"
        return _summary(draft), None, draft
    if step == "confirm":
        if not is_confirmation(message):
            draft["step"] = "items"
            return "Ok mình làm lại nhé. Bạn muốn đặt bánh nào ạ?", None, draft
        payload = {"user_id": user_id, "recipient_name": draft["recipient_name"],
                   "phone": draft["phone"], "address": draft["address"],
                   "items": [{"banh_id": i["banh_id"], "quantity": i["quantity"]} for i in draft["items"]]}
        try:
            result = submit_order(deps.settings, payload)
        except Exception:
            return ("Hệ thống đặt hàng đang bận, mình đã lưu đơn nháp và chuyển nhân viên hỗ trợ xử lý "
                     "giúp bạn."), None, draft
        if "order_id" in result:
            return (f"Đặt bánh thành công! Mã đơn #{result['order_id']}, "
                    f"tổng {fmt_vnd(result['total_amount'])}, thanh toán khi nhận hàng (COD). "
                    "Cảm ơn bạn đã ủng hộ Gấu Bakery! 🎂"), result, {}
        return f"Chưa tạo được đơn ({result.get('reason', 'lỗi')}). Bạn kiểm tra lại thông tin giúp mình nhé.", None, draft
    return "Bạn muốn đặt bánh nào ạ?", None, {"step": "items", "items": []}
