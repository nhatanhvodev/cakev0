"""Eval metrics M1-M6 cho A/B test baseline vs multiagent.

M1 (answer accuracy) là chấm tay: 2 annotator điền CSV xuất bởi
`annotate_template.py` (cột correct_A/correct_B), rồi dùng `kappa()` dưới
đây để đo độ đồng thuận (Cohen's Kappa) giữa 2 annotator.

M5 (retention) đo trên log production, KHÔNG tính được từ eval dataset —
chạy SQL sau trên bảng `chat_sessions` / `chat_messages` (tên bảng theo
Task 7 chat_repo schema):

    SELECT COUNT(*) AS retained_sessions
    FROM (
        SELECT session_id, COUNT(*) AS customer_msgs
        FROM chat_messages
        WHERE sender = 'customer'
        GROUP BY session_id
        HAVING COUNT(*) >= 3
    ) t;
    -- retention_rate = retained_sessions / (SELECT COUNT(DISTINCT session_id) FROM chat_messages)

M2-M4 và M6 tính từ file kết quả `run_eval.py` (JSONL) + dataset gốc, thuần
Python, không phụ thuộc pandas/numpy.
"""
import csv as csv_mod
import json


def grounded_turn(turn: dict) -> bool:
    """M2: turn có ít nhất 1 citation, và mọi citation đều nằm trong retrieved_docs."""
    docs = set(turn.get("retrieved_docs", []))
    cits = [c.get("source") for c in turn.get("citations", [])]
    return bool(cits) and all(c in docs for c in cits)


def handoff_prf(pred: dict, truth: dict) -> tuple[float, float, float]:
    """M4: precision/recall/f1 handoff, so sánh theo key (sample_id) giữa 2 dict bool."""
    tp = sum(1 for k in truth if truth[k] and pred.get(k))
    fp = sum(1 for k in truth if not truth[k] and pred.get(k))
    fn = sum(1 for k in truth if truth[k] and not pred.get(k))
    p = tp / (tp + fp) if tp + fp else 0.0
    r = tp / (tp + fn) if tp + fn else 0.0
    f1 = 2 * p * r / (p + r) if p + r else 0.0
    return p, r, f1


def cohen_kappa(a: list, b: list) -> float:
    """Cohen's Kappa cho 2 rater, giá trị 0/1 (đồng ý nhị phân)."""
    n = len(a)
    po = sum(1 for x, y in zip(a, b) if x == y) / n
    pa = (sum(a) / n) * (sum(b) / n) + ((n - sum(a)) / n) * ((n - sum(b)) / n)
    return (po - pa) / (1 - pa) if pa != 1 else 1.0


def _load_jsonl(path: str) -> list[dict]:
    rows = []
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def _percentile(values: list, pct: float) -> float:
    """Linear-interpolation percentile, không phụ thuộc numpy. pct in [0, 100]."""
    if not values:
        return 0.0
    s = sorted(values)
    if len(s) == 1:
        return float(s[0])
    k = (len(s) - 1) * (pct / 100)
    f = int(k)
    c = min(f + 1, len(s) - 1)
    if f == c:
        return float(s[f])
    return float(s[f] * (c - k) + s[c] * (k - f))


def kappa(csv1: str, csv2: str) -> float:
    """M1: Cohen's Kappa giữa 2 annotator, gộp cả cột correct_A và correct_B.

    csv1/csv2 là 2 file CSV cùng schema xuất bởi `annotate_template.py`,
    mỗi cái đã được MỘT annotator điền cột correct_A/correct_B (giá trị
    "1"/"0"; ô trống bị bỏ qua khi giao 2 file theo sample_id).
    """
    def read_ratings(path: str) -> dict:
        ratings = {}
        with open(path, encoding="utf-8-sig", newline="") as f:
            for row in csv_mod.DictReader(f):
                sid = row.get("sample_id")
                for col in ("correct_A", "correct_B"):
                    v = (row.get(col) or "").strip()
                    if v != "":
                        ratings[(sid, col)] = int(v)
        return ratings

    r1 = read_ratings(csv1)
    r2 = read_ratings(csv2)
    keys = sorted(set(r1) & set(r2))
    if not keys:
        return 0.0
    return cohen_kappa([r1[k] for k in keys], [r2[k] for k in keys])


def compute(results_path: str, dataset_path: str) -> dict:
    """Đọc results (JSONL từ run_eval.py) + dataset gốc, tính M2-M4 và M6.

    results record: {sample_id, turn, predicted_intent, confidence, response,
    citations, retrieved_docs, predicted_handoff, latency_ms}
    dataset record (dataset_schema.load_dataset): {id, messages,
    expected_intent, expected_handoff, ground_truth_answer, tags}
    """
    from eval.dataset_schema import load_dataset

    dataset = load_dataset(dataset_path)
    results = _load_jsonl(results_path)
    ds_by_id = {d["id"]: d for d in dataset}

    by_sample: dict[str, list[dict]] = {}
    for r in results:
        by_sample.setdefault(r["sample_id"], []).append(r)
    for turns in by_sample.values():
        turns.sort(key=lambda t: t["turn"])

    # M2: grounded rate, tính trên toàn bộ turn đã chạy
    all_turns = [t for turns in by_sample.values() for t in turns]
    grounded_rate = (sum(1 for t in all_turns if grounded_turn(t)) / len(all_turns)
                      if all_turns else 0.0)

    # M3: intent accuracy — predicted_intent turn cuối vs expected_intent
    correct, evaluated = 0, 0
    for sample_id, turns in by_sample.items():
        d = ds_by_id.get(sample_id)
        if not d or not turns:
            continue
        evaluated += 1
        if turns[-1].get("predicted_intent") == d.get("expected_intent"):
            correct += 1
    intent_accuracy = correct / evaluated if evaluated else 0.0

    # M4: handoff precision/recall/f1 — any-turn predicted vs expected, theo sample
    pred_handoff = {sid: any(t.get("predicted_handoff") for t in turns)
                     for sid, turns in by_sample.items()}
    truth_handoff = {sid: ds_by_id[sid].get("expected_handoff", False)
                      for sid in by_sample if sid in ds_by_id}
    handoff_precision, handoff_recall, handoff_f1 = handoff_prf(pred_handoff, truth_handoff)

    # Latency: độ trễ turn đầu tiên của mỗi sample
    first_latencies = [turns[0]["latency_ms"] for turns in by_sample.values() if turns]
    avg_first_response_ms = sum(first_latencies) / len(first_latencies) if first_latencies else 0.0
    p95_first_response_ms = _percentile(first_latencies, 95)

    # M6: task completion rate — sample expected_intent=order_create có turn
    # chứa "Mã đơn #" (xác nhận đặt hàng thành công)
    order_ids = [d["id"] for d in dataset if d.get("expected_intent") == "order_create"]
    completed, counted = 0, 0
    for sid in order_ids:
        turns = by_sample.get(sid)
        if not turns:
            continue
        counted += 1
        if any("Mã đơn #" in (t.get("response") or "") for t in turns):
            completed += 1
    task_completion_rate = completed / counted if counted else 0.0

    return {
        "n_samples": evaluated,
        "intent_accuracy": intent_accuracy,
        "grounded_rate": grounded_rate,
        "handoff_precision": handoff_precision,
        "handoff_recall": handoff_recall,
        "handoff_f1": handoff_f1,
        "avg_first_response_ms": avg_first_response_ms,
        "p95_first_response_ms": p95_first_response_ms,
        "task_completion_rate": task_completion_rate,
    }
