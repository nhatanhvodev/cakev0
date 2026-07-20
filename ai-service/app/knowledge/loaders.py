import json, re

def product_to_doc(row: dict):
    text = (f"SAN PHAM: {row['ten_banh']}\nLOAI: {row['loai']}\n"
            f"GIA: {int(row['gia'])} VND\nMO TA: {row.get('mo_ta') or ''}")
    meta = {k: row.get(k) for k in ("id", "gia", "loai", "hinh_anh", "slug") if row.get(k) is not None}
    return f"product-{row['id']}", text, meta

def load_policy_file(path: str):
    raw = open(path, encoding="utf-8").read()
    m = re.match(r"---\n(.*?)\n---\n(.*)", raw, re.S)
    meta = dict(line.split(":", 1) for line in m.group(1).splitlines())
    meta = {k.strip(): v.strip() for k, v in meta.items()}
    name = path.replace("\\", "/").rsplit("/", 1)[-1].removesuffix(".md")
    return f"policy-{name}", m.group(2).strip(), meta

def faq_to_doc(entry: dict, idx: int):
    return f"faq-{idx}", f"HOI: {entry['question']}\nDAP: {entry['answer']}", {"category": entry.get("category", "")}

def load_faq_seed(path: str) -> list[dict]:
    return json.load(open(path, encoding="utf-8"))
