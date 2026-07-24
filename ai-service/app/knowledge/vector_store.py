from dataclasses import dataclass
import chromadb

@dataclass
class RetrievedDoc:
    id: str
    text: str
    metadata: dict
    distance: float

class _EmbedAdapter:  # chroma EmbeddingFunction interface
    def __init__(self, fn): self._fn = fn
    def __call__(self, input): return self._fn(input)
    def name(self): return "custom"

class VectorStore:
    def __init__(self, persist_dir: str, embedding_fn):
        self._client = chromadb.PersistentClient(path=persist_dir)
        self._embed = _EmbedAdapter(embedding_fn)

    def _col(self, name: str):
        return self._client.get_or_create_collection(name, embedding_function=self._embed)

    def add(self, collection, ids, texts, metadatas):
        # chroma rejects empty-dict metadata; normalize to None (query() already
        # coalesces a missing metadata back to {} on the way out).
        metas = [m if m else None for m in metadatas]
        self._col(collection).upsert(ids=ids, documents=texts, metadatas=metas)

    def query(self, collection, text, top_k=5) -> list[RetrievedDoc]:
        res = self._col(collection).query(query_texts=[text], n_results=top_k)
        out = []
        for i, doc_id in enumerate(res["ids"][0]):
            out.append(RetrievedDoc(doc_id, res["documents"][0][i],
                                    res["metadatas"][0][i] or {}, res["distances"][0][i]))
        return out

    def reset(self, collection):
        try: self._client.delete_collection(collection)
        except Exception: pass

    def count(self, collection) -> int:
        try: return self._col(collection).count()
        except Exception: return 0
