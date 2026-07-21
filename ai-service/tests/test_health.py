from fastapi.testclient import TestClient
from app.main import app, _reset_rate_limit
from app import deps
from app.engines.baseline import BaselineEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def test_health():
    client = TestClient(app)
    r = client.get("/health")
    assert r.status_code == 200
    assert r.json()["status"] == "ok"
    assert r.json()["engine"] in ("baseline", "multiagent")


def test_rate_limit_429_after_20_requests(fake_store):
    _reset_rate_limit()
    d = EngineDeps(llm=FakeLLM(['{"answer": "OK", "confidence": 0.9, "sources": []}'] * 20),
                   store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    responses = []
    for _ in range(21):
        r = client.post("/chat/send", json={"message": "hi", "guest_token": "rl-guest"})
        responses.append(r)
    app.dependency_overrides.clear()
    _reset_rate_limit()

    assert [r.status_code for r in responses[:20]] == [200] * 20
    assert responses[20].status_code == 429
    assert responses[20].json() == {"error": "rate_limited"}
