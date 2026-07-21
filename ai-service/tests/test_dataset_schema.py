from eval.dataset_schema import validate_sample

GOOD = {"id": "s001", "messages": ["ship bao lau"], "expected_intent": "policy_shipping",
        "expected_handoff": False, "ground_truth_answer": "Giao trong ngày...", "tags": ["no_diacritics"]}


def test_valid_sample():
    assert validate_sample(GOOD) == []


def test_bad_intent():
    bad = dict(GOOD, expected_intent="nonsense")
    assert any("intent" in e for e in validate_sample(bad))


def test_missing_messages():
    bad = dict(GOOD, messages=[])
    assert validate_sample(bad)
