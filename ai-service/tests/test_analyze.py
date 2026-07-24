from eval.analyze import format_comparison

BASELINE_METRICS = {
    "n_samples": 10,
    "intent_accuracy": 0.80,
    "grounded_rate": 0.70,
    "handoff_precision": 0.60,
    "handoff_recall": 0.50,
    "handoff_f1": 0.55,
    "avg_first_response_ms": 1200.0,
    "p95_first_response_ms": 2000.0,
    "task_completion_rate": 0.75,
}

MULTI_METRICS = {
    "n_samples": 10,
    "intent_accuracy": 0.90,
    "grounded_rate": 0.85,
    "handoff_precision": 0.70,
    "handoff_recall": 0.65,
    "handoff_f1": 0.67,
    "avg_first_response_ms": 1500.0,
    "p95_first_response_ms": 2500.0,
    "task_completion_rate": 0.88,
}


def test_format_comparison():
    out = format_comparison(BASELINE_METRICS, MULTI_METRICS, 0.032)

    assert "| Metric | System A (Baseline) | System B (Multi-Agent) | Delta |" in out
    for label in [
        "N Samples", "Intent Accuracy", "Grounded Rate", "Handoff Precision",
        "Handoff Recall", "Handoff F1", "Avg First Response (ms)",
        "P95 First Response (ms)", "Task Completion Rate",
    ]:
        assert label in out
    assert "0.0320" in out
