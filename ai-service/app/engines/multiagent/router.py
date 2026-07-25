import json, re
from app.llm import LLMClient

INTENTS = ["faq", "catalog_search", "product_recommend", "promotion", "bestseller",
           "order_status", "order_create",
           "policy_shipping", "policy_payment", "policy_return", "complaint",
           "chitchat", "handoff_request"]

ROUTER_SYSTEM = """Bạn là router của hệ thống CSKH Gấu Bakery.
Phân loại câu của khách vào đúng 1 intent:
faq | catalog_search | product_recommend | promotion | bestseller | order_status | order_create |
policy_shipping | policy_payment | policy_return | complaint | chitchat | handoff_request

Ví dụ:
- "có bánh kem dâu không" → catalog_search
- "gợi ý bánh sinh nhật cho bé" → product_recommend
- "có khuyến mãi gì không" → promotion
- "bánh nào bán chạy nhất" → bestseller
- "đơn 12 đến đâu rồi" → order_status
- "đặt 2 bánh croissant" → order_create
- "ship bao lâu" → policy_shipping
- "thanh toán vnpay được không" → policy_payment
- "đổi bánh bị hỏng" → policy_return
- "shop mở mấy giờ" → faq
- "bánh giao bị móp, bực quá" → complaint
- "cho gặp nhân viên" → handoff_request
- "cảm ơn shop" → chitchat

Nếu có LỊCH SỬ hội thoại, dùng nó để hiểu ngữ cảnh câu hiện tại (ví dụ "cái đó giá bao nhiêu" sau khi hỏi về 1 bánh → catalog_search).
Chỉ trả JSON: {"intent": "...", "confidence": 0.0-1.0}"""

_KEYWORDS = [
    ("handoff_request", ["người thật", "nhân viên", "gặp quản lý", "hỗ trợ viên"]),
    ("complaint", ["khiếu nại", "bực", "tệ", "hỏng", "sai đơn", "hoàn tiền"]),
    ("order_status", ["đơn", "kiểm tra đơn", "đến đâu", "mã đơn"]),
    ("order_create", ["đặt bánh", "mua bánh", "chốt đơn", "order"]),
    ("policy_shipping", ["giao hàng", "phí ship", "vận chuyển"]),
    ("policy_payment", ["thanh toán", "chuyển khoản", "vnpay", "thanh toán khi nhận hàng"]),
    ("policy_return", ["đổi trả", "đổi bánh", "trả hàng"]),
    ("promotion", ["khuyến mãi", "khuyen mai", "giảm giá", "giam gia", "sale", "ưu đãi", "uu dai", "đang giảm"]),
    ("bestseller", ["bán chạy", "ban chay", "best seller", "bestseller", "must try", "phổ biến", "nên thử", "nen thu", "nhiều người mua", "hot nhất"]),
    ("catalog_search", ["có bánh", "tìm bánh", "menu", "giá bánh"]),
    ("chitchat", ["chào", "cảm ơn", "hello", "hi "]),
]

def keyword_fallback(text: str) -> tuple[str, float]:
    low = text.lower()
    for intent, kws in _KEYWORDS:
        if any(k in low for k in kws):
            return intent, 0.55
    return "faq", 0.4

def _format_history(history: list) -> str:
    if not history:
        return ""
    recent = history[-6:]
    lines = [f"{m['sender']}: {m['content']}" for m in recent]
    return "LỊCH SỬ:\n" + "\n".join(lines) + "\n\n"

def classify_intent(llm: LLMClient, text: str, history: list | None = None) -> tuple[str, float]:
    kw_intent, kw_conf = keyword_fallback(text)
    if kw_conf >= 0.55:
        return kw_intent, kw_conf
    hist_block = _format_history(history or [])
    raw = llm.generate(ROUTER_SYSTEM, f"{hist_block}KHÁCH: {text}")
    m = re.search(r"\{.*\}", raw, re.S)
    if m:
        try:
            d = json.loads(m.group(0))
            if d.get("intent") in INTENTS:
                return d["intent"], float(d.get("confidence", 0.5))
        except (json.JSONDecodeError, ValueError, TypeError):
            pass
    return kw_intent, kw_conf
