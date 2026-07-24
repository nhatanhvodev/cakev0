"""Xuất CSV cho annotator chấm tay M1 (answer accuracy), so sánh câu trả lời
cuối cùng của baseline (A) vs multiagent (B) trên cùng 1 sample.

Mỗi annotator điền cột correct_A/correct_B (1 = đúng/chấp nhận được, 0 = sai)
trên bản copy CSV riêng của mình; `eval.metrics.kappa()` sau đó đo độ đồng
thuận (Cohen's Kappa) giữa 2 file đã điền.

Cách chạy:
    cd ai-service
    venv\\Scripts\\python -m eval.annotate_template \
        --dataset eval/dataset/samples.jsonl \
        --baseline eval/results/baseline.jsonl \
        --multiagent eval/results/multiagent.jsonl \
        --out eval/annotate_template.csv
"""
import argparse
import csv
import json

from eval.dataset_schema import load_dataset

FIELDNAMES = ["sample_id", "question", "ground_truth", "response_A", "response_B",
              "correct_A", "correct_B"]


def _load_jsonl(path: str) -> list[dict]:
    rows = []
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def _last_response_by_sample(results: list[dict]) -> dict:
    by_sample: dict[str, list[dict]] = {}
    for r in results:
        by_sample.setdefault(r["sample_id"], []).append(r)
    out = {}
    for sid, turns in by_sample.items():
        turns.sort(key=lambda t: t["turn"])
        out[sid] = turns[-1].get("response", "")
    return out


def build_rows(dataset_path: str, baseline_path: str, multiagent_path: str) -> list[dict]:
    dataset = load_dataset(dataset_path)
    resp_a = _last_response_by_sample(_load_jsonl(baseline_path))
    resp_b = _last_response_by_sample(_load_jsonl(multiagent_path))

    rows = []
    for d in dataset:
        sid = d["id"]
        if sid not in resp_a and sid not in resp_b:
            continue
        rows.append({
            "sample_id": sid,
            "question": " | ".join(d.get("messages", [])),
            "ground_truth": d.get("ground_truth_answer", ""),
            "response_A": resp_a.get(sid, ""),
            "response_B": resp_b.get(sid, ""),
            "correct_A": "",
            "correct_B": "",
        })
    return rows


def write_csv(rows: list[dict], out_path: str) -> None:
    with open(out_path, "w", encoding="utf-8-sig", newline="") as f:
        w = csv.DictWriter(f, fieldnames=FIELDNAMES)
        w.writeheader()
        w.writerows(rows)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dataset", default="eval/dataset/samples.jsonl")
    ap.add_argument("--baseline", required=True)
    ap.add_argument("--multiagent", required=True)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()

    rows = build_rows(args.dataset, args.baseline, args.multiagent)
    write_csv(rows, args.out)
    print(f"Wrote {len(rows)} rows to {args.out}")


if __name__ == "__main__":
    main()
