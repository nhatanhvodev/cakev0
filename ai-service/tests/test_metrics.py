from eval.metrics import grounded_turn, handoff_prf, cohen_kappa


def test_grounded_turn():
    assert grounded_turn({"citations": [{"source": "faq-1"}], "retrieved_docs": ["faq-1", "faq-2"]})
    assert not grounded_turn({"citations": [], "retrieved_docs": ["faq-1"]})
    assert not grounded_turn({"citations": [{"source": "x"}], "retrieved_docs": ["faq-1"]})


def test_handoff_prf():
    pred = {"a": True, "b": False, "c": True}
    truth = {"a": True, "b": False, "c": False}
    p, r, f1 = handoff_prf(pred, truth)
    assert p == 0.5 and r == 1.0


def test_cohen_kappa_perfect_agreement():
    assert cohen_kappa([1, 0, 1, 1], [1, 0, 1, 1]) == 1.0
