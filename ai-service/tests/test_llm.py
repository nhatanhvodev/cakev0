from app.llm import FakeLLM


def test_fake_llm_pops_in_order():
    llm = FakeLLM(["a", "b"])
    assert llm.generate("sys", "u1") == "a"
    assert llm.generate("sys", "u2") == "b"
    assert llm.calls[0] == ("sys", "u1")
