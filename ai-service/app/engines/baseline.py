import json, re
from app.engines.base import EngineDeps
from app.models.chat import EngineReply

BASELINE_SYSTEM = """Bạn là trợ lý CSKH của Gấu Bakery, tiệm bánh online Việt Nam.
Dựa vào TÀI LIỆU, trả lời câu hỏi khách bằng tiếng Việt thân thiện.
Nhiệm vụ: FAQ, tư vấn sản phẩm, chính sách giao hàng/đổi trả/thanh toán, trạng thái đơn.
Nếu khách muốn đặt bánh, hướng dẫn vào trang sản phẩm/giỏ hàng.
Không bịa thông tin ngoài tài liệu. Giá format XXX.XXX VNĐ.
TRẢ VỀ JSON: {"answer": "...", "confidence": 0.0-1.0, "sources": ["doc-id trích dẫn"]}"""

def parse_llm_json(raw: str) -> dict:
    m = re.search(r"\{.*\}", raw, re.S)
    if m:
        try:
            d = json.loads(m.group(0))
            return {"answer": str(d.get("answer", "")),
                    "confidence": float(d.get("confidence", 0.5)),
                    "sources": list(d.get("sources", []))}
        except (json.JSONDecodeError, TypeError, ValueError):
            pass
    return {"answer": raw.strip(), "confidence": 0.5, "sources": []}

class BaselineEngine:
    def __init__(self, deps: EngineDeps):
        self.deps = deps

    def _retrieve(self, query: str):
        docs = []
        for col in ("faq", "policies", "products"):
            docs += self.deps.store.query(col, query, top_k=3)
        return sorted(docs, key=lambda d: d.distance)[:6]

    def handle(self, history, user_message, context) -> EngineReply:
        docs = self._retrieve(user_message)
        doc_block = "\n---\n".join(f"[{d.id}] {d.text}" for d in docs)
        hist_block = "\n".join(f"{m['sender']}: {m['content']}" for m in history[-6:])
        user = f"TÀI LIỆU:\n{doc_block}\n\nLỊCH SỬ:\n{hist_block}\n\nKHÁCH: {user_message}"
        parsed = parse_llm_json(self.deps.llm.generate(BASELINE_SYSTEM, user))
        by_id = {d.id: d for d in docs}
        citations = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]
        handoff = parsed["confidence"] < self.deps.settings.handoff_confidence_threshold
        return EngineReply(type="text", content=parsed["answer"], citations=citations,
                           intent="unknown", confidence=parsed["confidence"], handoff=handoff,
                           retrieved_docs=[d.id for d in docs])
