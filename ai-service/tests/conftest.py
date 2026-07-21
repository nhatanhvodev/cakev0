import pytest
from app.knowledge.vector_store import VectorStore


def fake_embed(texts):
    return [[float(len(t) % 7), float(sum(map(ord, t)) % 11), 1.0] for t in texts]


@pytest.fixture
def fake_store(tmp_path):
    return VectorStore(str(tmp_path), embedding_fn=fake_embed)


@pytest.fixture(autouse=True)
def _reset_rate_limit_between_tests():
    from app.main import _reset_rate_limit
    _reset_rate_limit()
    yield
    _reset_rate_limit()
