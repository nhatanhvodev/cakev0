import json, re
from app.llm import LLMClient

INTENTS = ["faq", "catalog_search", "product_recommend", "promotion", "bestseller",
           "order_status", "order_create",
           "policy_shipping", "policy_payment", "policy_return", "complaint",
           "chitchat", "handoff_request",
           "coupon_inquiry", "review_lookup", "product_compare",
           "favorite_add", "favorite_view", "dietary_inquiry", "custom_cake_quote"]

ROUTER_SYSTEM = """Bạn là router của hệ thống CSKH Gấu Bakery.
Phân loại câu của khách vào đúng 1 intent:
faq | catalog_search | product_recommend | promotion | bestseller | order_status | order_create |
policy_shipping | policy_payment | policy_return | complaint | chitchat | handoff_request |
coupon_inquiry | review_lookup | product_compare | favorite_add | favorite_view | dietary_inquiry | custom_cake_quote

Phân biệt:
- promotion = sản phẩm đang giảm giá; coupon_inquiry = mã/voucher giảm giá.
- order_create = đặt bánh có sẵn trong menu; custom_cake_quote = đặt bánh thiết kế riêng theo yêu cầu, cần báo giá.

Ví dụ:
- "có bánh kem dâu không" → catalog_search
- "gợi ý bánh sinh nhật cho bé" → product_recommend
- "có khuyến mãi gì không" → promotion
- "có mã giảm giá nào không" → coupon_inquiry
- "bánh nào bán chạy nhất" → bestseller
- "đơn 12 đến đâu rồi" → order_status
- "đặt 2 bánh croissant" → order_create
- "đặt bánh sinh nhật thiết kế riêng" → custom_cake_quote
- "bánh kem chocolate đánh giá thế nào" → review_lookup
- "so sánh bánh tiramisu và mousse" → product_compare
- "lưu bánh này vào yêu thích" → favorite_add
- "xem bánh yêu thích của tôi" → favorite_view
- "bánh nào không có trứng" → dietary_inquiry
- "ship bao lâu" → policy_shipping
- "thanh toán sepay được không" → policy_payment
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
    # custom_cake_quote before order_create ("đặt bánh riêng" contains "đặt bánh")
    ("custom_cake_quote", ["bánh theo yêu cầu", "banh theo yeu cau", "đặt bánh riêng", "dat banh rieng",
                            "thiết kế bánh", "thiet ke banh", "báo giá bánh", "bao gia banh",
                            "bánh đặt riêng", "bánh vẽ theo mẫu", "bánh thiết kế",
                            "thiết kế riêng", "thiet ke rieng", "thiết kế", "theo yêu cầu", "đặt riêng"]),
    # dietary before catalog_search ("bánh nào không trứng" would hit "có bánh")
    ("dietary_inquiry", ["không trứng", "khong trung", "không sữa", "khong sua", "không gluten",
                          "không bột mì", "thuần chay", "thuan chay", "vegan", "dị ứng", "di ung",
                          "không hạt", "khong hat"]),
    ("review_lookup", ["đánh giá", "danh gia", "review", "nhận xét", "nhan xet", "bao nhiêu sao",
                        "mấy sao", "may sao", "rating"]),
    ("product_compare", ["so sánh", "so sanh", "cái nào ngon hơn", "cai nao ngon hon",
                          "nên chọn cái nào", "khác nhau gì"]),
    ("favorite_view", ["xem yêu thích", "danh sách yêu thích", "bánh yêu thích của tôi",
                        "bánh tôi thích", "bánh đã thích"]),
    ("favorite_add", ["lưu bánh", "luu banh", "thêm vào yêu thích", "them vao yeu thich",
                       "lưu vào yêu thích", "yêu thích bánh này", "lưu yêu thích"]),
    ("order_status", ["kiểm tra đơn", "đến đâu", "mã đơn", "tình trạng đơn"]),
    ("order_create", ["đặt bánh", "mua bánh", "chốt đơn", "order"]),
    ("policy_shipping", ["giao hàng", "phí ship", "vận chuyển"]),
    ("policy_payment", ["thanh toán", "chuyển khoản", "sepay", "qr", "thanh toán khi nhận hàng"]),
    ("policy_return", ["đổi trả", "đổi bánh", "trả hàng"]),
    # coupon before promotion ("mã giảm giá" contains "giảm giá")
    ("coupon_inquiry", ["mã giảm", "ma giam", "voucher", "coupon", "mã ưu đãi", "ma uu dai",
                         "mã khuyến mãi", "code giảm", "mã code"]),
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
