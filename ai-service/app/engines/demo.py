"""Demo-safe engine: wraps multiagent, falls back to canned responses on LLM failure.

Use ENGINE=demo when demoing before a thesis committee to avoid 500 errors
from Gemini free-tier quota exhaustion. Falls back gracefully with a
pre-scripted Vietnamese response instead of crashing.
"""
from app.engines.base import EngineDeps
from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.multiagent.router import keyword_fallback
from app.models.chat import EngineReply

_FALLBACK = {
    "faq": "Dạ shop mở cửa từ 8h đến 21h, anh/chị gọi hotline 0901 234 567 để được tư vấn ạ.",
    "catalog_search": "Dạ anh/chị xem đầy đủ sản phẩm trong trang catalog của shop nhé.",
    "product_recommend": "Dạ shop gợi ý bánh kem Chocolate hoặc Tiramisu, rất được yêu thích ạ.",
    "promotion": "Dạ anh/chị xem mục khuyến mãi trên website để cập nhật ưu đãi mới nhất ạ.",
    "bestseller": "Dạ các món bán chạy nhất là Bánh kem Chocolate và Tiramisu ạ.",
    "order_status": "Dạ anh/chị cho shop xin mã đơn hoặc số điện thoại để tra cứu giúp ạ.",
    "order_create": "Dạ anh/chị cho shop xin tên bánh, số lượng, họ tên, SĐT và địa chỉ giao ạ.",
    "policy_shipping": "Dạ đơn đặt trước 15h shop giao trong ngày, sau 15h giao sáng hôm sau ạ.",
    "policy_payment": "Dạ shop hỗ trợ VNPAY, chuyển khoản và COD ạ.",
    "policy_return": "Dạ bánh có vấn đề anh/chị chụp ảnh gửi shop trong 2h để đổi/hoàn tiền ạ.",
    "complaint": "Dạ shop ghi nhận và chuyển nhân viên xử lý ngay cho anh/chị ạ.",
    "handoff_request": "Dạ shop kết nối anh/chị với nhân viên hỗ trợ ngay ạ.",
    "chitchat": "Dạ Gấu Bakery chào anh/chị ạ!",
}


class DemoEngine:
    def __init__(self, deps: EngineDeps):
        self.deps = deps
        self._real = MultiAgentEngine(deps)

    def handle(self, history, user_message, context) -> EngineReply:
        try:
            return self._real.handle(history, user_message, context)
        except Exception:
            intent, conf = keyword_fallback(user_message)
            return EngineReply(
                type="text",
                content=_FALLBACK.get(intent, _FALLBACK["faq"]),
                citations=[],
                intent=intent,
                confidence=conf,
                handoff=intent in ("complaint", "handoff_request"),
            )
