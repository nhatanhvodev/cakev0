from app.engines.baseline import parse_llm_json

_COLLECTION = {"catalog_search": "products", "product_recommend": "products",
               "policy_shipping": "policies", "policy_payment": "policies",
               "policy_return": "policies", "faq": "faq"}

RETRIEVAL_SYSTEM = """Bạn là trợ lý Gấu Bakery. Dựa vào TÀI LIỆU trả lời tiếng Việt, trích nguồn.
Trả JSON: {"answer": "...", "confidence": 0.0-1.0, "sources": ["doc-id"]}"""

def retrieval_node(deps, state):
    # Task 13 mở rộng: retry/rerank
    col = _COLLECTION.get(state["intent"], "faq")
    docs = deps.store.query(col, state["normalized_query"], top_k=5)
    block = "\n---\n".join(f"[{d.id}] {d.text}" for d in docs)
    parsed = parse_llm_json(deps.llm.generate(
        RETRIEVAL_SYSTEM, f"TÀI LIỆU:\n{block}\n\nKHÁCH: {state['query']}"))
    by_id = {d.id: d for d in docs}
    cits = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]
    products = [d.metadata | {"ten_banh": d.text.split("\n")[0].replace("SAN PHAM: ", "")}
                for d in docs if d.id.startswith("product-")] if col == "products" else []
    return {"response": parsed["answer"], "confidence": min(state.get("confidence", 1.0), parsed["confidence"]),
            "citations": cits, "retrieved_docs": [d.id for d in docs], "products": products[:5]}
