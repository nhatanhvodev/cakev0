from app.engines.baseline import parse_llm_json

_COLLECTION = {"catalog_search": "products", "product_recommend": "products",
               "policy_shipping": "policies", "policy_payment": "policies",
               "policy_return": "policies", "faq": "faq"}

RETRIEVAL_SYSTEM = """Bạn là trợ lý Gấu Bakery. Dựa vào TÀI LIỆU trả lời tiếng Việt, trích nguồn.
Trả JSON: {"answer": "...", "confidence": 0.0-1.0, "sources": ["doc-id"]}"""

MAX_RETRIES = 2


def _promoted_ids(conn) -> set:
    """Return the set of banh_id (product id) with an active promotion.

    promotions(id, banh_id, gia_khuyen_mai, ngay_bat_dau, ngay_ket_thuc) — verified schema.
    Wrapped defensively so a missing/renamed table never breaks retrieval.
    """
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT DISTINCT banh_id FROM promotions "
                "WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc")
            return {row["banh_id"] for row in cur.fetchall()}
    except Exception:
        return set()


def _rerank_by_promotion(products: list, promoted: set) -> list:
    if not promoted:
        return products
    # stable sort: promoted products first, original order preserved within each group
    return sorted(products, key=lambda p: p.get("id") not in promoted)


def retrieval_node(deps, state):
    col = _COLLECTION.get(state["intent"], "faq")
    docs = deps.store.query(col, state["normalized_query"], top_k=5)
    block = "\n---\n".join(f"[{d.id}] {d.text}" for d in docs)
    parsed = parse_llm_json(deps.llm.generate(
        RETRIEVAL_SYSTEM, f"TÀI LIỆU:\n{block}\n\nKHÁCH: {state['query']}"))
    by_id = {d.id: d for d in docs}
    cits = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]
    products = [(d.metadata or {}) | {"ten_banh": d.text.split("\n")[0].replace("SAN PHAM: ", "")}
                for d in docs if d.id.startswith("product-")] if col == "products" else []

    if state["intent"] == "product_recommend" and products:
        conn = deps.conn_factory()
        if conn is not None:
            try:
                promoted = _promoted_ids(conn)
                products = _rerank_by_promotion(products, promoted)
            finally:
                conn.close()

    retry_count = state.get("retry_count", 0)
    needs_retry = parsed["confidence"] < 0.5
    out = {"response": parsed["answer"],
           "confidence": min(state.get("confidence", 1.0), parsed["confidence"]),
           "citations": cits, "retrieved_docs": [d.id for d in docs],
           "products": products[:5], "needs_retry": needs_retry}
    if needs_retry and retry_count >= MAX_RETRIES:
        out["should_handoff"] = True
        out["handoff_reasons"] = state.get("handoff_reasons", []) + ["max_retries"]
        out["needs_retry"] = False
    return out
