import glob, os
from app.db import catalog_repo
from app.knowledge import loaders

def reindex(store, conn, source: str = "all", data_dir: str = "data") -> int:
    n = 0
    if source in ("products", "all") and conn is not None:
        store.reset("products")
        for row in catalog_repo.list_products(conn):
            i, t, m = loaders.product_to_doc(row)
            store.add("products", [i], [t], [m]); n += 1
    if source in ("policies", "all"):
        store.reset("policies")
        for path in glob.glob(os.path.join(data_dir, "policies", "*.md")):
            i, t, m = loaders.load_policy_file(path)
            store.add("policies", [i], [t], [m]); n += 1
    if source in ("faq", "all"):
        store.reset("faq")
        for idx, e in enumerate(loaders.load_faq_seed(os.path.join(data_dir, "faq_seed.json"))):
            i, t, m = loaders.faq_to_doc(e, idx)
            store.add("faq", [i], [t], [m]); n += 1
    return n
