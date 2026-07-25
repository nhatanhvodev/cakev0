from dataclasses import dataclass
import re
import chromadb
from rank_bm25 import BM25Okapi

@dataclass
class RetrievedDoc:
    id: str
    text: str
    metadata: dict
    distance: float

class _EmbedAdapter:  # chroma EmbeddingFunction interface
    def __init__(self, fn): self._fn = fn
    def __call__(self, input): return self._fn(input)
    def embed_documents(self, input): return self._fn(input)
    def embed_query(self, input): return self._fn(input)
    def name(self): return "custom"

_TOKEN_RE = re.compile(r"[\wÀ-ỹ]+")

def _tokenize(text: str) -> list[str]:
    return _TOKEN_RE.findall(text.lower())


class _BM25Index:
    def __init__(self):
        self._ids: list[str] = []
        self._texts: list[str] = []
        self._metadatas: list[dict] = []
        self._bm25: BM25Okapi | None = None

    def build(self, ids, texts, metadatas):
        self._ids = list(ids)
        self._texts = list(texts)
        self._metadatas = list(metadatas)
        corpus = [_tokenize(t) for t in self._texts]
        self._bm25 = BM25Okapi(corpus) if corpus else None

    def query(self, text: str, top_k: int = 5) -> list[tuple[str, str, dict, float]]:
        if not self._bm25 or not self._ids:
            return []
        tokens = _tokenize(text)
        scores = self._bm25.get_scores(tokens)
        ranked = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:top_k]
        return [(self._ids[i], self._texts[i], self._metadatas[i], float(scores[i]))
                for i in ranked if scores[i] > 0]


def _rrf_fuse(vector_results: list[RetrievedDoc],
              bm25_results: list[tuple[str, str, dict, float]],
              top_k: int = 5, k: int = 60) -> list[RetrievedDoc]:
    scores: dict[str, float] = {}
    doc_map: dict[str, RetrievedDoc] = {}
    for rank, doc in enumerate(vector_results):
        scores[doc.id] = scores.get(doc.id, 0) + 1.0 / (k + rank + 1)
        doc_map[doc.id] = doc
    for rank, (did, text, meta, _bm25_score) in enumerate(bm25_results):
        scores[did] = scores.get(did, 0) + 1.0 / (k + rank + 1)
        if did not in doc_map:
            doc_map[did] = RetrievedDoc(did, text, meta, 0.0)
    ranked_ids = sorted(scores, key=lambda d: scores[d], reverse=True)[:top_k]
    return [doc_map[did] for did in ranked_ids]


class VectorStore:
    def __init__(self, persist_dir: str, embedding_fn):
        self._client = chromadb.PersistentClient(path=persist_dir)
        self._embed = _EmbedAdapter(embedding_fn)
        self._bm25: dict[str, _BM25Index] = {}

    def _col(self, name: str):
        return self._client.get_or_create_collection(name, embedding_function=self._embed)

    def add(self, collection, ids, texts, metadatas):
        metas = [m if m else None for m in metadatas]
        self._col(collection).upsert(ids=ids, documents=texts, metadatas=metas)
        idx = _BM25Index()
        idx.build(ids, texts, [m or {} for m in metas])
        self._bm25[collection] = idx

    def query(self, collection, text, top_k=5) -> list[RetrievedDoc]:
        res = self._col(collection).query(query_texts=[text], n_results=top_k)
        vector_docs = []
        for i, doc_id in enumerate(res["ids"][0]):
            vector_docs.append(RetrievedDoc(doc_id, res["documents"][0][i],
                                            res["metadatas"][0][i] or {}, res["distances"][0][i]))
        bm25_idx = self._bm25.get(collection)
        if bm25_idx:
            bm25_hits = bm25_idx.query(text, top_k=top_k)
            return _rrf_fuse(vector_docs, bm25_hits, top_k=top_k)
        return vector_docs

    def reset(self, collection):
        try: self._client.delete_collection(collection)
        except Exception: pass
        self._bm25.pop(collection, None)

    def count(self, collection) -> int:
        try: return self._col(collection).count()
        except Exception: return 0
