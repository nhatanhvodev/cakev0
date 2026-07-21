"""Eval dataset schema + validator.

Mỗi mẫu là 1 dòng JSONL:
{"id": "s001", "messages": ["câu khách 1", ...], "expected_intent": "<1 trong 11 intent>",
 "expected_handoff": bool, "ground_truth_answer": "...", "tags": ["teencode"|"no_diacritics"|"edge_case"|"common"]}
"""
import json

from app.engines.multiagent.router import INTENTS

VALID_TAGS = {"teencode", "no_diacritics", "edge_case", "common"}


def validate_sample(d: dict) -> list[str]:
    """Trả về list lỗi. Rỗng = hợp lệ."""
    errs = []
    if not d.get("id"):
        errs.append("missing id")
    if not d.get("messages") or not isinstance(d["messages"], list):
        errs.append("messages must be non-empty list")
    if d.get("expected_intent") not in INTENTS:
        errs.append(f"invalid intent: {d.get('expected_intent')}")
    if not isinstance(d.get("expected_handoff"), bool):
        errs.append("expected_handoff must be bool")
    if not d.get("ground_truth_answer"):
        errs.append("missing ground_truth_answer")
    if not set(d.get("tags", [])) <= VALID_TAGS:
        errs.append("invalid tags")
    return errs


def load_dataset(path: str) -> list[dict]:
    """Đọc + validate toàn bộ dataset. Raise ValueError nếu có dòng lỗi."""
    rows = []
    for n, line in enumerate(open(path, encoding="utf-8"), 1):
        line = line.strip()
        if not line:
            continue
        d = json.loads(line)
        errs = validate_sample(d)
        if errs:
            raise ValueError(f"line {n}: {errs}")
        rows.append(d)
    return rows
