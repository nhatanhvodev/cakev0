import json, re
from app.llm import LLMClient

INTENTS = ["faq", "catalog_search", "product_recommend", "order_status", "order_create",
           "policy_shipping", "policy_payment", "policy_return", "complaint",
           "chitchat", "handoff_request"]

ROUTER_SYSTEM = """Bạn là router của hệ thống CSKH Gấu Bakery.
Phân loại câu của khách vào đúng 1 intent:
faq | catalog_search | product_recommend | order_status | order_create |
policy_shipping | policy_payment | policy_return | complaint | chitchat | handoff_request
Chỉ trả JSON: {"intent": "...", "confidence": 0.0-1.0}"""

_KEYWORDS = [
    ("handoff_request", ["người thật", "nhân viên", "gặp quản lý", "hỗ trợ viên"]),
    ("complaint", ["khiếu nại", "bực", "tệ", "hỏng", "sai đơn", "hoàn tiền"]),
    ("order_status", ["đơn", "kiểm tra đơn", "đến đâu", "mã đơn"]),
    ("order_create", ["đặt bánh", "mua bánh", "chốt đơn", "order"]),
    ("policy_shipping", ["giao hàng", "phí ship", "vận chuyển"]),
    ("policy_payment", ["thanh toán", "chuyển khoản", "vnpay", "thanh toán khi nhận hàng"]),
    ("policy_return", ["đổi trả", "đổi bánh", "trả hàng"]),
    ("catalog_search", ["có bánh", "tìm bánh", "menu", "giá bánh"]),
    ("chitchat", ["chào", "cảm ơn", "hello", "hi "]),
]

def keyword_fallback(text: str) -> tuple[str, float]:
    low = text.lower()
    for intent, kws in _KEYWORDS:
        if any(k in low for k in kws):
            return intent, 0.55
    return "faq", 0.4

def classify_intent(llm: LLMClient, text: str) -> tuple[str, float]:
    raw = llm.generate(ROUTER_SYSTEM, text)
    m = re.search(r"\{.*\}", raw, re.S)
    if m:
        try:
            d = json.loads(m.group(0))
            if d.get("intent") in INTENTS:
                return d["intent"], float(d.get("confidence", 0.5))
        except (json.JSONDecodeError, ValueError, TypeError):
            pass
    return keyword_fallback(text)
