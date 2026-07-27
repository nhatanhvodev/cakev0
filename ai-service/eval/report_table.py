"""Sinh bảng so sánh baseline vs multiagent (Markdown) từ 2 file kết quả
run_eval.py, dùng eval.metrics.compute(). Chèn thẳng vào chương thực nghiệm.

Cách chạy (sau khi đã có 2 file results):
    cd ai-service
    venv\\Scripts\\python -m eval.report_table \
        --baseline eval/results/baseline.jsonl \
        --multiagent eval/results/multiagent.jsonl \
        --dataset eval/dataset/samples.jsonl \
        --out eval/results/benchmark_table.md
"""
import argparse
import sys

from eval.metrics import compute

try:  # Windows console mặc định cp1252, ép UTF-8 để in tiếng Việt không lỗi
    sys.stdout.reconfigure(encoding="utf-8")
except Exception:
    pass

_ROWS = [
    ("Intent accuracy (M3)", "intent_accuracy", "pct"),
    ("Grounded rate (M2)", "grounded_rate", "pct"),
    ("Handoff precision (M4)", "handoff_precision", "pct"),
    ("Handoff recall (M4)", "handoff_recall", "pct"),
    ("Handoff F1 (M4)", "handoff_f1", "num"),
    ("Task completion (M6)", "task_completion_rate", "pct"),
    ("Latency trung bình lượt đầu (ms)", "avg_first_response_ms", "ms"),
    ("Latency p95 lượt đầu (ms)", "p95_first_response_ms", "ms"),
]


def _fmt(val, kind):
    if kind == "pct":
        return f"{val * 100:.1f}%"
    if kind == "ms":
        return f"{val:.0f}"
    return f"{val:.3f}"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--baseline", required=True)
    ap.add_argument("--multiagent", required=True)
    ap.add_argument("--dataset", default="eval/dataset/samples.jsonl")
    ap.add_argument("--out", default="eval/results/benchmark_table.md")
    args = ap.parse_args()

    base = compute(args.baseline, args.dataset)
    multi = compute(args.multiagent, args.dataset)

    lines = []
    n = multi.get("n_samples") or base.get("n_samples")
    lines.append(f"Bộ dữ liệu đánh giá: {n} mẫu hội thoại tiếng Việt.")
    lines.append("")
    lines.append("| Chỉ số | Baseline RAG | Multi-Agent RAG |")
    lines.append("| --- | --- | --- |")
    for label, key, kind in _ROWS:
        lines.append(f"| {label} | {_fmt(base[key], kind)} | {_fmt(multi[key], kind)} |")
    md = "\n".join(lines) + "\n"

    with open(args.out, "w", encoding="utf-8") as f:
        f.write(md)
    print(md)


if __name__ == "__main__":
    main()
