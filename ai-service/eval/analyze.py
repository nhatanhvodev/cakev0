"""Phân tích kết quả eval: so sánh baseline vs multiagent (M1-M6) + Wilcoxon
signed-rank trên latency ghép cặp theo sample_id, xuất bảng Markdown.

Đọc `results/baseline.jsonl` + `results/multiagent.jsonl` (từ `run_eval.py`)
và `dataset/samples.jsonl`, tính `eval.metrics.compute()` cho mỗi hệ, rồi ghép
cặp latency turn đầu tiên của từng sample có mặt ở cả 2 file để chạy
`scipy.stats.wilcoxon`.

Cách chạy (sau khi đã có đủ 2 file kết quả — xem Task 23 Steps 1-2):
    cd ai-service
    venv\\Scripts\\python -m eval.analyze --baseline eval/results/baseline.jsonl \
        --multiagent eval/results/multiagent.jsonl --dataset eval/dataset/samples.jsonl \
        --out eval/results/comparison.md
"""
import argparse
import json
import os

from scipy.stats import wilcoxon

from eval.metrics import compute

# (metrics key, cột hiển thị, kiểu format)
METRIC_LABELS = [
    ("n_samples", "N Samples", "int"),
    ("intent_accuracy", "Intent Accuracy", "pct"),
    ("grounded_rate", "Grounded Rate", "pct"),
    ("handoff_precision", "Handoff Precision", "pct"),
    ("handoff_recall", "Handoff Recall", "pct"),
    ("handoff_f1", "Handoff F1", "pct"),
    ("avg_first_response_ms", "Avg First Response (ms)", "ms"),
    ("p95_first_response_ms", "P95 First Response (ms)", "ms"),
    ("task_completion_rate", "Task Completion Rate", "pct"),
]


def _load_jsonl(path: str) -> list[dict]:
    rows = []
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def _first_latencies_by_sample(results_path: str) -> dict:
    """sample_id -> latency_ms của turn đầu tiên."""
    by_sample: dict[str, list[dict]] = {}
    for r in _load_jsonl(results_path):
        by_sample.setdefault(r["sample_id"], []).append(r)
    out = {}
    for sid, turns in by_sample.items():
        turns.sort(key=lambda t: t["turn"])
        out[sid] = turns[0]["latency_ms"]
    return out


def compute_wilcoxon(baseline_path: str, multiagent_path: str):
    """Wilcoxon signed-rank trên latency turn đầu, ghép cặp theo sample_id
    có mặt ở cả 2 file. Trả về p_value, hoặc None nếu không đủ mẫu (số cặp
    quá ít, hoặc tất cả hiệu số bằng 0 khiến scipy raise ValueError)."""
    b_lat = _first_latencies_by_sample(baseline_path)
    m_lat = _first_latencies_by_sample(multiagent_path)
    common = sorted(set(b_lat) & set(m_lat))
    if len(common) < 1:
        return None
    b_vals = [b_lat[s] for s in common]
    m_vals = [m_lat[s] for s in common]
    try:
        _, p_value = wilcoxon(b_vals, m_vals)
    except ValueError:
        return None
    return float(p_value)


def _fmt_value(value, kind: str) -> str:
    if kind == "pct":
        return f"{value * 100:.1f}%"
    if kind == "ms":
        return f"{value:.1f}"
    if kind == "int":
        return str(int(value))
    return str(value)


def _fmt_delta(baseline_value, multi_value, kind: str) -> str:
    if kind == "int":
        return "—"
    delta = multi_value - baseline_value
    if kind == "pct":
        return f"{delta * 100:+.1f}%"
    if kind == "ms":
        return f"{delta:+.1f}"
    return f"{delta:+.3f}"


def _fmt_p_value(p_value) -> str:
    if p_value is None:
        return "n/a (insufficient samples)"
    return f"{p_value:.4f}"


def format_comparison(baseline_metrics: dict, multi_metrics: dict, p_value) -> str:
    """Xuất bảng Markdown so sánh baseline vs multiagent, kèm dòng p-value
    Wilcoxon cho latency."""
    lines = [
        "| Metric | System A (Baseline) | System B (Multi-Agent) | Delta |",
        "|--------|---------------------|-------------------------|-------|",
    ]
    for key, label, kind in METRIC_LABELS:
        b = baseline_metrics.get(key, 0)
        m = multi_metrics.get(key, 0)
        lines.append(
            f"| {label} | {_fmt_value(b, kind)} | {_fmt_value(m, kind)} | "
            f"{_fmt_delta(b, m, kind)} |"
        )
    lines.append(f"| Latency Wilcoxon p-value | — | — | {_fmt_p_value(p_value)} |")
    return "\n".join(lines) + "\n"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--baseline", required=True)
    ap.add_argument("--multiagent", required=True)
    ap.add_argument("--dataset", required=True)
    ap.add_argument("--out", default="eval/results/comparison.md")
    args = ap.parse_args()

    baseline_metrics = compute(args.baseline, args.dataset)
    multi_metrics = compute(args.multiagent, args.dataset)
    p_value = compute_wilcoxon(args.baseline, args.multiagent)

    table = format_comparison(baseline_metrics, multi_metrics, p_value)

    out_dir = os.path.dirname(args.out)
    if out_dir:
        os.makedirs(out_dir, exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as f:
        f.write(table)

    print(table)


if __name__ == "__main__":
    main()
