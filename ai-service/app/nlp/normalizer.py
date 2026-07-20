import json
import os
import re
from functools import lru_cache


@lru_cache
def load_teencode() -> dict:
    path = os.path.join(os.path.dirname(__file__), "teencode.json")
    return json.load(open(path, encoding="utf-8"))


def normalize(text: str) -> str:
    mapping = load_teencode()
    def repl(m):
        w = m.group(0)
        return mapping.get(w.lower(), w)
    return re.sub(r"[\wÀ-ỹ]+", repl, text)
