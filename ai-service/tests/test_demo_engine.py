from app.engines.demo import DemoEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings


class _BrokenLLM:
    def generate(self, system, prompt):
        raise RuntimeError("Gemini quota exhausted")


def test_demo_normal_path(fake_store):
    deps = EngineDeps(llm=FakeLLM(["Chào bạn!"]), store=fake_store,
                      settings=get_settings(), conn_factory=lambda: None)
    eng = DemoEngine(deps)
    r = eng.handle([], "chào shop", {})
    assert r.intent == "chitchat"
    assert "Chào" in r.content


def test_demo_fallback_on_error(fake_store):
    deps = EngineDeps(llm=_BrokenLLM(), store=fake_store,
                      settings=get_settings(), conn_factory=lambda: None)
    eng = DemoEngine(deps)
    r = eng.handle([], "chào shop", {})
    assert r.intent == "chitchat"
    assert "Gấu Bakery" in r.content
    assert r.handoff is False


def test_demo_fallback_handoff_intent(fake_store):
    deps = EngineDeps(llm=_BrokenLLM(), store=fake_store,
                      settings=get_settings(), conn_factory=lambda: None)
    eng = DemoEngine(deps)
    r = eng.handle([], "tôi muốn khiếu nại", {})
    assert r.intent == "complaint"
    assert r.handoff is True
