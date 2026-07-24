"""Eval runner: chạy 1 engine (baseline|multiagent) qua toàn bộ dataset, ghi
kết quả từng turn ra file JSONL để `eval.metrics.compute()` tính M1-M6.

Checkpoint-resume: nếu `--out` đã tồn tại, các sample_id đã có trong file sẽ
bị bỏ qua (an toàn để chạy lại sau khi bị gián đoạn giữa chừng).

`--sleep` throttle giữa các lượt gọi LLM để tránh vượt rate limit free tier
của Gemini (mặc định 4s).

Cách chạy:
    cd ai-service
    venv\\Scripts\\python -m eval.run_eval --engine baseline --out eval/results/baseline.jsonl
    venv\\Scripts\\python -m eval.run_eval --engine multiagent --out eval/results/multiagent.jsonl
"""
import argparse
import json
import os
import time

from app.deps import build_deps
from app.engines.baseline import BaselineEngine
from app.engines.multiagent.graph import MultiAgentEngine
from eval.dataset_schema import load_dataset


def build_engine(engine_name: str, deps):
    if engine_name == "baseline":
        return BaselineEngine(deps)
    return MultiAgentEngine(deps)


def load_done_ids(out_path: str) -> set:
    if not os.path.exists(out_path):
        return set()
    done = set()
    with open(out_path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                done.add(json.loads(line)["sample_id"])
    return done


def run_sample(engine, sample: dict, sleep_s: float, out_f) -> None:
    history: list[dict] = []
    for turn_i, msg in enumerate(sample["messages"]):
        t0 = time.perf_counter()
        reply = engine.handle(history, msg, {"session": {"id": 0}, "user_id": None})
        latency_ms = int((time.perf_counter() - t0) * 1000)
        record = {
            "sample_id": sample["id"],
            "turn": turn_i,
            "predicted_intent": reply.intent,
            "confidence": reply.confidence,
            "response": reply.content,
            "citations": reply.citations,
            "retrieved_docs": getattr(reply, "retrieved_docs", []) or
                              [c["source"] for c in reply.citations],
            "predicted_handoff": reply.handoff,
            "latency_ms": latency_ms,
        }
        out_f.write(json.dumps(record, ensure_ascii=False) + "\n")
        out_f.flush()
        history += [{"sender": "customer", "content": msg},
                    {"sender": "bot", "content": reply.content}]
        time.sleep(sleep_s)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--engine", choices=["baseline", "multiagent"], required=True)
    ap.add_argument("--dataset", default="eval/dataset/samples.jsonl")
    ap.add_argument("--out", required=True)
    ap.add_argument("--sleep", type=float, default=4.0)  # throttle free tier
    args = ap.parse_args()

    out_dir = os.path.dirname(args.out)
    if out_dir:
        os.makedirs(out_dir, exist_ok=True)

    deps = build_deps()
    engine = build_engine(args.engine, deps)

    done = load_done_ids(args.out)
    dataset = load_dataset(args.dataset)

    with open(args.out, "a", encoding="utf-8") as f:
        for sample in dataset:
            if sample["id"] in done:
                continue
            run_sample(engine, sample, args.sleep, f)


if __name__ == "__main__":
    main()
