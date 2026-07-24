import glob, os
from app.db import catalog_repo
from app.knowledge import loaders


def _batch_add(store, collection, triples) -> int:
    """Add all docs of a collection in ONE store.add call so the embedding
    backend batches them into a single API request (avoids per-doc rate limits)."""
    if not triples:
        return 0
    ids = [t[0] for t in triples]
    texts = [t[1] for t in triples]
    metas = [t[2] for t in triples]
    store.add(collection, ids, texts, metas)
    return len(triples)


def reindex(store, conn, source: str = "all", data_dir: str = "data") -> int:
    n = 0
    if source in ("products", "all") and conn is not None:
        store.reset("products")
        triples = [loaders.product_to_doc(row) for row in catalog_repo.list_products(conn)]
        n += _batch_add(store, "products", triples)
    if source in ("policies", "all"):
        store.reset("policies")
        triples = [loaders.load_policy_file(path)
                   for path in glob.glob(os.path.join(data_dir, "policies", "*.md"))]
        n += _batch_add(store, "policies", triples)
    if source in ("faq", "all"):
        store.reset("faq")
        triples = [loaders.faq_to_doc(e, idx)
                   for idx, e in enumerate(loaders.load_faq_seed(os.path.join(data_dir, "faq_seed.json")))]
        n += _batch_add(store, "faq", triples)
    return n
