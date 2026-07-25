"""Sinh dữ liệu eval synthetic bằng Gemini.

Loop qua 11 intent x 2 style (formal / teencode-no-diacritic), yêu cầu LLM
sinh 10 câu hỏi khách hàng tiệm bánh online tiếng Việt cho mỗi cặp, in JSONL
ra stdout theo đúng schema của `eval.dataset_schema`.

KHÔNG tự động append vào `eval/dataset/samples.jsonl` — người làm eval cần
review tay từng dòng (đúng schema, đúng intent, câu tự nhiên, ground_truth_answer
hợp lý) rồi mới copy/paste các dòng đạt vào samples.jsonl.

Cách chạy:
    cd ai-service
    venv\\Scripts\\python -m eval.generate_synthetic > eval/dataset/_synthetic_raw.jsonl
    # rồi review tay _synthetic_raw.jsonl trước khi append vào samples.jsonl
"""
import json
import re
import sys

from app.config import get_settings
from app.engines.multiagent.router import INTENTS
from app.llm import build_llm_client

STYLES = [
    ("formal", "viết đầy đủ dấu, câu chuẩn mực, lịch sự bình thường"),
    ("teencode", "viết tắt kiểu teencode, thiếu dấu tiếng Việt, giống chat thật của giới trẻ"),
]

GEN_SYSTEM = """Bạn là bộ sinh dữ liệu huấn luyện cho hệ thống CSKH tiệm bánh online \
tên Gấu Bakery. Nhiệm vụ: đóng vai khách hàng thật, sinh câu hỏi/tin nhắn nhắn cho shop.

Yêu cầu:
- Sinh đúng 10 câu, mỗi câu là một khách hàng khác nhau, KHÔNG trùng lặp ý.
- Mỗi câu phải thuộc đúng intent được yêu cầu.
- Văn phong theo đúng style được yêu cầu (formal hoặc teencode/thiếu dấu).
- Chỉ trả JSONL (mỗi dòng 1 JSON object, không có text thừa, không markdown fence),
  đúng format sau cho mỗi dòng:
  {"messages": ["<câu khách>"], "ground_truth_answer": "<câu trả lời mẫu ngắn gọn cho khách>"}
- Không thêm field nào khác ngoài "messages" và "ground_truth_answer".
"""


def _build_user_prompt(intent: str, style_desc: str) -> str:
    return (
        f"Sinh 10 câu hỏi khách hàng tiệm bánh online tiếng Việt cho intent \"{intent}\", "
        f"phong cách: {style_desc}. Trả JSONL đúng schema đã mô tả."
    )


def _parse_jsonl_lines(raw: str):
    for line in raw.splitlines():
        line = line.strip()
        line = re.sub(r"^```[a-zA-Z]*$", "", line).strip()
        if not line or line in ("```", "```json"):
            continue
        try:
            yield json.loads(line)
        except json.JSONDecodeError:
            continue


def generate_for(llm, intent: str, style_key: str, style_desc: str, start_idx: int):
    raw = llm.generate(GEN_SYSTEM, _build_user_prompt(intent, style_desc))
    tags = ["common"] if style_key == "formal" else ["teencode", "no_diacritics"]
    n = start_idx
    for item in _parse_jsonl_lines(raw):
        messages = item.get("messages")
        answer = item.get("ground_truth_answer")
        if not messages or not answer:
            continue
        n += 1
        yield {
            "id": f"syn-{intent}-{style_key}-{n:03d}",
            "messages": messages,
            "expected_intent": intent,
            "expected_handoff": intent in ("complaint", "handoff_request"),
            "ground_truth_answer": answer,
            "tags": tags,
        }


def main():
    settings = get_settings()
    llm = build_llm_client(settings)
    for intent in INTENTS:
        for style_key, style_desc in STYLES:
            for sample in generate_for(llm, intent, style_key, style_desc, 0):
                sys.stdout.write(json.dumps(sample, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    main()
