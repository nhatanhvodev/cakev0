# AI CSKH & Chốt đơn Đa kênh — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây AI Agent CSKH đa kênh (widget + admin inbox + Messenger) cho Gấu Bakery với 2 engine so sánh được (System A RAG baseline, System B multi-agent LangGraph), chốt đơn COD trong chat, kèm eval harness 6 metrics cho khóa luận.

**Architecture:** 1 FastAPI service (Python 3.12) với flag `ENGINE=baseline|multiagent`; chung Normalizer, ChromaDB, MySQL layer, API contract. Chốt đơn qua PHP internal API (HMAC) tái dùng logic checkout. Spec: `docs/superpowers/specs/2026-07-19-ai-cskh-agent-design.md`.

**Tech Stack:** FastAPI, LangGraph, langchain-google-genai (Gemini 2.0 Flash + text-embedding-004), ChromaDB, PyMySQL, pytest; PHP 8.2 thuần + mysqli prepared statements; JS/CSS thuần cho widget.

## Global Constraints

- Python 3.12; PHP 8.2 không framework; MySQL 8.0 database `banh_store`, charset `utf8mb4`.
- LLM cố định `gemini-2.0-flash`, embedding `text-embedding-004`, temperature 0.3 — dùng chung cho cả 2 engine (biến độc lập duy nhất là kiến trúc).
- Mọi SQL PHP: mysqli prepared statements. Mọi SQL Python: PyMySQL parameterized.
- Không đổi schema bảng hiện có. 4 bảng mới: `chat_sessions`, `chat_messages`, `faq_entries`, `support_tickets`.
- Test Python: pytest, mock LLM (không gọi API thật trong test). Test PHP: script thuần theo pattern `tests/bootstrap.php` (`assert_same`, chạy `php tests/<file>.php`, exit 0 = pass).
- Internal order API: HMAC-SHA256 header `X-Internal-Signature`, secret env `INTERNAL_API_SECRET`. Payment trong chat: COD only. Đặt đơn yêu cầu user đăng nhập (`orders.user_id` NOT NULL).
- Tiền tệ format `XXX.XXX VNĐ`. Response tiếng Việt.
- Commit sau mỗi task, message theo Conventional Commits.

**Thứ tự phase:** P1 nền tảng + System A end-to-end → P2 System B multi-agent → P3 chốt đơn → P4 admin inbox → P5 Messenger → P6 eval + deploy. Mỗi phase kết thúc có software chạy được.

---

## Phase 1 — Nền tảng + System A end-to-end

### Task 1: Migration 4 bảng chat

**Files:**
- Create: `database/migrations/2026_07_19_create_chat_tables.sql`

**Interfaces:**
- Produces: bảng `chat_sessions`, `chat_messages`, `faq_entries`, `support_tickets` cho mọi task sau.

- [ ] **Step 1: Viết migration**

```sql
-- database/migrations/2026_07_19_create_chat_tables.sql
CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_token VARCHAR(64) NULL,
    external_user_id VARCHAR(64) NULL,
    status ENUM('active','closed','handoff') DEFAULT 'active',
    source ENUM('widget','messenger') DEFAULT 'widget',
    intent_label VARCHAR(50) NULL,
    summary TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_external_user (external_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender ENUM('customer','bot','agent') NOT NULL,
    content TEXT NOT NULL,
    content_type ENUM('text','image','product_card','order_card') DEFAULT 'text',
    metadata JSON NULL,
    admin_id INT NULL,
    created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_session (session_id),
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NULL,
    priority INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    admin_id INT NULL,
    subject VARCHAR(255) NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','waiting_customer','resolved','closed') DEFAULT 'open',
    resolution_note TEXT NULL,
    draft_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_admin (admin_id),
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Apply + verify**

Run: `mysql -u root banh_store < database/migrations/2026_07_19_create_chat_tables.sql` rồi `mysql -u root banh_store -e "SHOW TABLES LIKE 'chat_%'"`
Expected: liệt kê `chat_messages`, `chat_sessions`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_19_create_chat_tables.sql
git commit -m "feat: add chat/faq/ticket tables migration"
```

### Task 2: ai-service skeleton (config + health)

**Files:**
- Create: `ai-service/requirements.txt`, `ai-service/app/__init__.py`, `ai-service/app/config.py`, `ai-service/app/main.py`, `ai-service/.env.example`, `ai-service/tests/__init__.py`, `ai-service/tests/test_health.py`
- Create: `ai-service/.gitignore` (nội dung: `.env`, `__pycache__/`, `data/chroma_db/`, `.pytest_cache/`, `venv/`)

**Interfaces:**
- Produces: `app.config.Settings` + `get_settings()` (cached), FastAPI app `app.main:app`. Mọi task Python sau import từ đây.

- [ ] **Step 1: requirements.txt**

```txt
fastapi==0.115.*
uvicorn[standard]==0.30.*
langchain==0.3.*
langchain-google-genai==2.0.*
langgraph==0.2.*
chromadb==0.5.*
pymysql==1.1.*
pydantic==2.*
pydantic-settings==2.*
python-dotenv==1.0.*
httpx==0.27.*
pytest==8.*
```

Setup: `cd ai-service && python -m venv venv && venv\Scripts\pip install -r requirements.txt`

- [ ] **Step 2: Failing test**

```python
# ai-service/tests/test_health.py
from fastapi.testclient import TestClient
from app.main import app

def test_health():
    client = TestClient(app)
    r = client.get("/health")
    assert r.status_code == 200
    assert r.json()["status"] == "ok"
    assert r.json()["engine"] in ("baseline", "multiagent")
```

Run (từ `ai-service/`): `venv\Scripts\python -m pytest tests/test_health.py -v`
Expected: FAIL `ModuleNotFoundError: No module named 'app.main'`

- [ ] **Step 3: Implement**

```python
# ai-service/app/config.py
from functools import lru_cache
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    engine: str = "multiagent"                 # baseline | multiagent
    gemini_api_key: str = ""
    llm_model: str = "gemini-2.0-flash"
    embedding_model: str = "text-embedding-004"
    llm_temperature: float = 0.3
    chroma_persist_dir: str = "./data/chroma_db"
    mysql_host: str = "127.0.0.1"
    mysql_port: int = 3306
    mysql_user: str = "root"
    mysql_password: str = ""
    mysql_database: str = "banh_store"
    internal_api_secret: str = "change-me"
    internal_order_api_url: str = "http://localhost/cakev0/api/internal/orders/create.php"
    handoff_confidence_threshold: float = 0.6
    fb_page_token: str = ""
    fb_verify_token: str = ""
    fb_app_secret: str = ""
    cors_origins: str = "*"
    site_base_url: str = "https://cake-i8l0.onrender.com/cakev0"

    model_config = {"env_file": ".env", "extra": "ignore"}

@lru_cache
def get_settings() -> Settings:
    return Settings()
```

```python
# ai-service/app/main.py
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.config import get_settings

app = FastAPI(title="Gau Bakery AI Service")
settings = get_settings()
app.add_middleware(
    CORSMiddleware,
    allow_origins=[o.strip() for o in settings.cors_origins.split(",")],
    allow_methods=["*"], allow_headers=["*"],
)

@app.get("/health")
def health():
    return {"status": "ok", "engine": settings.engine}
```

`.env.example`: liệt kê mọi field của `Settings` dạng `KEY=` (pydantic-settings đọc env không phân biệt hoa thường).

- [ ] **Step 4: Run test**

Run: `venv\Scripts\python -m pytest tests/test_health.py -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add ai-service/
git commit -m "feat: scaffold ai-service with config and health endpoint"
```

### Task 3: MySQL layer + catalog repo

**Files:**
- Create: `ai-service/app/db/__init__.py`, `ai-service/app/db/mysql.py`, `ai-service/app/db/catalog_repo.py`, `ai-service/tests/test_catalog_repo.py`

**Interfaces:**
- Produces: `mysql.get_conn(settings) -> pymysql.Connection` (DictCursor, autocommit). `catalog_repo.list_products(conn) -> list[dict]` (keys: id, ten_banh, loai, gia, mo_ta, hinh_anh, slug); `catalog_repo.find_products_by_ids(conn, ids: list[int]) -> list[dict]`; `catalog_repo.search_products_like(conn, keyword: str, limit: int = 5) -> list[dict]`.

- [ ] **Step 1: Failing test** (integration, skip nếu DB không chạy)

```python
# ai-service/tests/test_catalog_repo.py
import pytest
from app.config import get_settings
from app.db import mysql, catalog_repo

@pytest.fixture
def conn():
    try:
        c = mysql.get_conn(get_settings())
    except Exception:
        pytest.skip("MySQL not available")
    yield c
    c.close()

def test_list_products_returns_rows(conn):
    rows = catalog_repo.list_products(conn)
    assert len(rows) > 0
    assert {"id", "ten_banh", "gia"} <= set(rows[0].keys())

def test_find_by_ids_empty():
    assert catalog_repo.build_in_placeholders([]) == ""
    assert catalog_repo.build_in_placeholders([1, 2, 3]) == "%s,%s,%s"
```

Run: `venv\Scripts\python -m pytest tests/test_catalog_repo.py -v` — Expected: FAIL import error.

- [ ] **Step 2: Implement**

```python
# ai-service/app/db/mysql.py
import pymysql
from app.config import Settings

def get_conn(settings: Settings) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=settings.mysql_host, port=settings.mysql_port,
        user=settings.mysql_user, password=settings.mysql_password,
        database=settings.mysql_database, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor, autocommit=True,
    )
```

```python
# ai-service/app/db/catalog_repo.py
def build_in_placeholders(ids: list) -> str:
    return ",".join(["%s"] * len(ids))

def list_products(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh")
        return list(cur.fetchall())

def find_products_by_ids(conn, ids: list[int]) -> list[dict]:
    if not ids:
        return []
    ph = build_in_placeholders(ids)
    with conn.cursor() as cur:
        cur.execute(f"SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh WHERE id IN ({ph})", ids)
        return list(cur.fetchall())

def search_products_like(conn, keyword: str, limit: int = 5) -> list[dict]:
    like = f"%{keyword}%"
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, ten_banh, loai, gia, mo_ta, hinh_anh, slug FROM banh "
            "WHERE ten_banh LIKE %s OR loai LIKE %s LIMIT %s", (like, like, limit))
        return list(cur.fetchall())
```

Chú ý: nếu bảng `banh` không có cột `slug` (kiểm tra `DESCRIBE banh`), bỏ cột đó khỏi SELECT và khỏi interface — cập nhật test tương ứng.

- [ ] **Step 3: Run test** — Expected: PASS (hoặc skip integration nếu DB tắt, unit `build_in_placeholders` vẫn PASS).

- [ ] **Step 4: Commit** — `git commit -m "feat: mysql layer and catalog repo"`

### Task 4: Vector store + knowledge indexing

**Files:**
- Create: `ai-service/app/knowledge/__init__.py`, `ai-service/app/knowledge/vector_store.py`, `ai-service/app/knowledge/loaders.py`, `ai-service/app/knowledge/indexer.py`
- Create: `ai-service/data/faq_seed.json`, `ai-service/data/policies/shipping.md`, `payment.md`, `exchanges.md`, `privacy.md`
- Test: `ai-service/tests/test_vector_store.py`, `ai-service/tests/test_loaders.py`

**Interfaces:**
- Produces: `RetrievedDoc` dataclass `{id: str, text: str, metadata: dict, distance: float}`. `VectorStore(persist_dir, embedding_fn)` với `.add(collection, ids, texts, metadatas)`, `.query(collection, text, top_k=5) -> list[RetrievedDoc]`, `.reset(collection)`. Collections: `products`, `policies`, `faq`. `loaders.product_to_doc(row) -> tuple[str, str, dict]` (id, text, metadata). `indexer.reindex(store, conn, source: str) -> int`.
- Embedding: production dùng `chromadb.utils.embedding_functions` Google; test dùng fake deterministic.

- [ ] **Step 1: Chuẩn bị data files**

`data/policies/*.md`: mở lần lượt `pages/shipping.php`, `pages/payment-policy.php`, `pages/exchanges-policy.php`, `pages/privacy.php`, copy phần text hiển thị (bỏ HTML/PHP) vào markdown, mỗi section giữ heading. Frontmatter đầu file:

```markdown
---
policy_type: shipping
url: /cakev0/pages/shipping.php
---
# Chính sách vận chuyển
...
```

`data/faq_seed.json` — tối thiểu 12 mục, 3 mẫu bắt buộc:

```json
[
  {"question": "Thời gian giao bánh là bao lâu?", "answer": "Gấu Bakery giao bánh trong ngày với các đơn đặt trước 15h. Với đơn sau 15h, chúng tôi sẽ giao vào sáng hôm sau.", "category": "shipping"},
  {"question": "Tôi có thể thanh toán bằng những cách nào?", "answer": "Gấu Bakery hỗ trợ: (1) VNPAY online, (2) Thanh toán khi nhận hàng (COD), (3) Chuyển khoản ngân hàng.", "category": "payment"},
  {"question": "Tôi muốn đổi bánh thì phải làm sao?", "answer": "Bạn có thể đổi bánh trong 2 giờ sau khi nhận nếu bánh có vấn đề chất lượng. Liên hệ hotline 0901 234 567.", "category": "returns"}
]
```

Viết thêm ≥9 câu từ nội dung policy pages (hỏi phí ship, khu vực giao, hủy đơn COD, bill/hóa đơn, đặt bánh sinh nhật trước bao lâu, v.v.).

- [ ] **Step 2: Failing tests**

```python
# ai-service/tests/test_vector_store.py
from app.knowledge.vector_store import VectorStore

def fake_embed(texts):  # deterministic, không gọi API
    return [[float(len(t) % 7), float(sum(map(ord, t)) % 11), 1.0] for t in texts]

def test_add_and_query(tmp_path):
    store = VectorStore(str(tmp_path), embedding_fn=fake_embed)
    store.add("faq", ["1"], ["giao hàng bao lâu"], [{"category": "shipping"}])
    docs = store.query("faq", "giao hàng bao lâu", top_k=1)
    assert docs[0].text == "giao hàng bao lâu"
    assert docs[0].metadata["category"] == "shipping"
```

```python
# ai-service/tests/test_loaders.py
from app.knowledge.loaders import product_to_doc, load_policy_file, faq_to_doc

def test_product_to_doc():
    doc_id, text, meta = product_to_doc({"id": 5, "ten_banh": "Bánh kem dâu", "loai": "kem",
                                         "gia": 250000, "mo_ta": "Ngọt dịu", "hinh_anh": "x.jpg", "slug": "banh-kem-dau"})
    assert doc_id == "product-5"
    assert "Bánh kem dâu" in text and "250" in text
    assert meta["gia"] == 250000

def test_load_policy_file(tmp_path):
    p = tmp_path / "shipping.md"
    p.write_text("---\npolicy_type: shipping\nurl: /x\n---\n# Giao hàng\nNội dung", encoding="utf-8")
    doc_id, text, meta = load_policy_file(str(p))
    assert meta["policy_type"] == "shipping"
    assert "Nội dung" in text
```

Run: `venv\Scripts\python -m pytest tests/test_vector_store.py tests/test_loaders.py -v` — Expected: FAIL import.

- [ ] **Step 3: Implement**

```python
# ai-service/app/knowledge/vector_store.py
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
        self._col(collection).upsert(ids=ids, documents=texts, metadatas=metadatas)

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
```

```python
# ai-service/app/knowledge/loaders.py
import json, re

def product_to_doc(row: dict):
    text = (f"SAN PHAM: {row['ten_banh']}\nLOAI: {row['loai']}\n"
            f"GIA: {int(row['gia'])} VND\nMO TA: {row.get('mo_ta') or ''}")
    meta = {k: row.get(k) for k in ("id", "gia", "loai", "hinh_anh", "slug") if row.get(k) is not None}
    return f"product-{row['id']}", text, meta

def load_policy_file(path: str):
    raw = open(path, encoding="utf-8").read()
    m = re.match(r"---\n(.*?)\n---\n(.*)", raw, re.S)
    meta = dict(line.split(":", 1) for line in m.group(1).splitlines())
    meta = {k.strip(): v.strip() for k, v in meta.items()}
    name = path.replace("\\", "/").rsplit("/", 1)[-1].removesuffix(".md")
    return f"policy-{name}", m.group(2).strip(), meta

def faq_to_doc(entry: dict, idx: int):
    return f"faq-{idx}", f"HOI: {entry['question']}\nDAP: {entry['answer']}", {"category": entry.get("category", "")}

def load_faq_seed(path: str) -> list[dict]:
    return json.load(open(path, encoding="utf-8"))
```

```python
# ai-service/app/knowledge/indexer.py
import glob, os
from app.db import catalog_repo
from app.knowledge import loaders

def reindex(store, conn, source: str = "all", data_dir: str = "data") -> int:
    n = 0
    if source in ("products", "all") and conn is not None:
        store.reset("products")
        for row in catalog_repo.list_products(conn):
            i, t, m = loaders.product_to_doc(row)
            store.add("products", [i], [t], [m]); n += 1
    if source in ("policies", "all"):
        store.reset("policies")
        for path in glob.glob(os.path.join(data_dir, "policies", "*.md")):
            i, t, m = loaders.load_policy_file(path)
            store.add("policies", [i], [t], [m]); n += 1
    if source in ("faq", "all"):
        store.reset("faq")
        for idx, e in enumerate(loaders.load_faq_seed(os.path.join(data_dir, "faq_seed.json"))):
            i, t, m = loaders.faq_to_doc(e, idx)
            store.add("faq", [i], [t], [m]); n += 1
    return n
```

- [ ] **Step 4: Run tests** — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat: vector store, loaders, indexer with policy/faq seed data"`

### Task 5: LLM client (Gemini + Fake)

**Files:**
- Create: `ai-service/app/llm.py`, Test: `ai-service/tests/test_llm.py`, `ai-service/tests/conftest.py`

**Interfaces:**
- Produces: `LLMClient` protocol: `generate(system: str, user: str) -> str`. `GeminiClient(settings)` (langchain-google-genai). `FakeLLM(replies: list[str])` — pop tuần tự, lưu `calls: list[tuple[str, str]]`. `gemini_embed(settings) -> callable` trả embedding fn cho VectorStore (GoogleGenerativeAIEmbeddings). Fixture pytest `fake_store` (VectorStore + fake_embed, tmp dir).

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_llm.py
from app.llm import FakeLLM

def test_fake_llm_pops_in_order():
    llm = FakeLLM(["a", "b"])
    assert llm.generate("sys", "u1") == "a"
    assert llm.generate("sys", "u2") == "b"
    assert llm.calls[0] == ("sys", "u1")
```

Run — Expected: FAIL import.

- [ ] **Step 2: Implement**

```python
# ai-service/app/llm.py
from typing import Protocol
from app.config import Settings

class LLMClient(Protocol):
    def generate(self, system: str, user: str) -> str: ...

class FakeLLM:
    def __init__(self, replies: list[str]):
        self._replies = list(replies)
        self.calls: list[tuple[str, str]] = []
    def generate(self, system: str, user: str) -> str:
        self.calls.append((system, user))
        return self._replies.pop(0) if self._replies else ""

class GeminiClient:
    def __init__(self, settings: Settings):
        from langchain_google_genai import ChatGoogleGenerativeAI
        self._chat = ChatGoogleGenerativeAI(
            model=settings.llm_model, google_api_key=settings.gemini_api_key,
            temperature=settings.llm_temperature)
    def generate(self, system: str, user: str) -> str:
        from langchain_core.messages import SystemMessage, HumanMessage
        import time
        try:
            return self._chat.invoke([SystemMessage(system), HumanMessage(user)]).content
        except Exception:
            time.sleep(2)  # spec §11: retry 1 lần rồi mới propagate
            return self._chat.invoke([SystemMessage(system), HumanMessage(user)]).content

def gemini_embed(settings: Settings):
    from langchain_google_genai import GoogleGenerativeAIEmbeddings
    emb = GoogleGenerativeAIEmbeddings(model=f"models/{settings.embedding_model}",
                                       google_api_key=settings.gemini_api_key)
    return lambda texts: emb.embed_documents(list(texts))
```

```python
# ai-service/tests/conftest.py
import pytest
from app.knowledge.vector_store import VectorStore

def fake_embed(texts):
    return [[float(len(t) % 7), float(sum(map(ord, t)) % 11), 1.0] for t in texts]

@pytest.fixture
def fake_store(tmp_path):
    return VectorStore(str(tmp_path), embedding_fn=fake_embed)
```

(Sửa `tests/test_vector_store.py` dùng fixture này, xóa fake_embed trùng.)

- [ ] **Step 3: Run** `venv\Scripts\python -m pytest -v` — Expected: all PASS.
- [ ] **Step 4: Commit** — `git commit -m "feat: llm client abstraction with gemini and fake implementations"`

### Task 6: Engine contract + Baseline engine (System A)

**Files:**
- Create: `ai-service/app/models/__init__.py`, `ai-service/app/models/chat.py`, `ai-service/app/engines/__init__.py`, `ai-service/app/engines/base.py`, `ai-service/app/engines/baseline.py`
- Test: `ai-service/tests/test_baseline_engine.py`

**Interfaces:**
- Produces: `EngineReply` (pydantic): `type: str` (`text|product_list|order_status|handoff|faq`), `content: str`, `citations: list[dict]` (`{source, excerpt}`), `products: list[dict]`, `intent: str`, `confidence: float`, `handoff: bool`, `order: dict | None`. `EngineDeps` dataclass: `llm, store, settings, conn_factory` (callable trả conn hoặc None). Engine protocol: `handle(history: list[dict], user_message: str, context: dict) -> EngineReply` — `history` = list `{sender, content}`.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_baseline_engine.py
from app.engines.baseline import BaselineEngine, parse_llm_json
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def _deps(store, replies):
    return EngineDeps(llm=FakeLLM(replies), store=store, settings=get_settings(), conn_factory=lambda: None)

def test_parse_llm_json_extracts_fields():
    out = parse_llm_json('{"answer": "Giao trong ngày", "confidence": 0.9, "sources": ["faq-1"]}')
    assert out == {"answer": "Giao trong ngày", "confidence": 0.9, "sources": ["faq-1"]}

def test_parse_llm_json_fallback_plain_text():
    out = parse_llm_json("Giao trong ngày nhé")
    assert out["answer"] == "Giao trong ngày nhé"
    assert out["confidence"] == 0.5

def test_baseline_answers_with_citation(fake_store):
    fake_store.add("faq", ["faq-1"], ["HOI: giao bao lâu\nDAP: trong ngày"], [{"category": "shipping"}])
    eng = BaselineEngine(_deps(fake_store, ['{"answer": "Trong ngày ạ", "confidence": 0.9, "sources": ["faq-1"]}']))
    reply = eng.handle([], "giao hàng bao lâu", {})
    assert reply.content == "Trong ngày ạ"
    assert reply.citations[0]["source"] == "faq-1"
    assert reply.handoff is False

def test_baseline_low_confidence_triggers_handoff(fake_store):
    eng = BaselineEngine(_deps(fake_store, ['{"answer": "Không rõ", "confidence": 0.3, "sources": []}']))
    reply = eng.handle([], "hỏi khó", {})
    assert reply.handoff is True
```

Run — Expected: FAIL import.

- [ ] **Step 2: Implement**

```python
# ai-service/app/models/chat.py
from pydantic import BaseModel

class EngineReply(BaseModel):
    type: str = "text"
    content: str = ""
    citations: list[dict] = []
    products: list[dict] = []
    intent: str = "unknown"
    confidence: float = 0.0
    handoff: bool = False
    order: dict | None = None
```

```python
# ai-service/app/engines/base.py
from dataclasses import dataclass
from typing import Callable, Any
from app.config import Settings
from app.knowledge.vector_store import VectorStore
from app.llm import LLMClient

@dataclass
class EngineDeps:
    llm: LLMClient
    store: VectorStore
    settings: Settings
    conn_factory: Callable[[], Any]
```

```python
# ai-service/app/engines/baseline.py
import json, re
from app.engines.base import EngineDeps
from app.models.chat import EngineReply

BASELINE_SYSTEM = """Bạn là trợ lý CSKH của Gấu Bakery, tiệm bánh online Việt Nam.
Dựa vào TÀI LIỆU, trả lời câu hỏi khách bằng tiếng Việt thân thiện.
Nhiệm vụ: FAQ, tư vấn sản phẩm, chính sách giao hàng/đổi trả/thanh toán, trạng thái đơn.
Nếu khách muốn đặt bánh, hướng dẫn vào trang sản phẩm/giỏ hàng.
Không bịa thông tin ngoài tài liệu. Giá format XXX.XXX VNĐ.
TRẢ VỀ JSON: {"answer": "...", "confidence": 0.0-1.0, "sources": ["doc-id trích dẫn"]}"""

def parse_llm_json(raw: str) -> dict:
    m = re.search(r"\{.*\}", raw, re.S)
    if m:
        try:
            d = json.loads(m.group(0))
            return {"answer": str(d.get("answer", "")),
                    "confidence": float(d.get("confidence", 0.5)),
                    "sources": list(d.get("sources", []))}
        except (json.JSONDecodeError, TypeError, ValueError):
            pass
    return {"answer": raw.strip(), "confidence": 0.5, "sources": []}

class BaselineEngine:
    def __init__(self, deps: EngineDeps):
        self.deps = deps

    def _retrieve(self, query: str):
        docs = []
        for col in ("faq", "policies", "products"):
            docs += self.deps.store.query(col, query, top_k=3)
        return sorted(docs, key=lambda d: d.distance)[:6]

    def handle(self, history, user_message, context) -> EngineReply:
        docs = self._retrieve(user_message)
        doc_block = "\n---\n".join(f"[{d.id}] {d.text}" for d in docs)
        hist_block = "\n".join(f"{m['sender']}: {m['content']}" for m in history[-6:])
        user = f"TÀI LIỆU:\n{doc_block}\n\nLỊCH SỬ:\n{hist_block}\n\nKHÁCH: {user_message}"
        parsed = parse_llm_json(self.deps.llm.generate(BASELINE_SYSTEM, user))
        by_id = {d.id: d for d in docs}
        citations = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]
        handoff = parsed["confidence"] < self.deps.settings.handoff_confidence_threshold
        return EngineReply(type="text", content=parsed["answer"], citations=citations,
                           intent="unknown", confidence=parsed["confidence"], handoff=handoff)
```

- [ ] **Step 3: Run** — Expected: PASS.
- [ ] **Step 4: Commit** — `git commit -m "feat: baseline single-pipeline RAG engine (System A)"`

### Task 7: Chat persistence repo

**Files:**
- Create: `ai-service/app/db/chat_repo.py`, Test: `ai-service/tests/test_chat_repo.py`

**Interfaces:**
- Produces: `get_or_create_session(conn, user_id=None, guest_token=None, source='widget', external_user_id=None, session_id=None) -> dict` (row chat_sessions; nếu `session_id` hợp lệ trả row đó, không thì tạo mới); `append_message(conn, session_id, sender, content, content_type='text', metadata=None) -> int`; `get_messages(conn, session_id, limit=50) -> list[dict]`; `update_session(conn, session_id, **fields)` (chỉ cho phép keys: status, intent_label, summary, metadata, closed_at — metadata dict tự json.dumps).

- [ ] **Step 1: Failing test** (integration, cùng pattern skip như Task 3)

```python
# ai-service/tests/test_chat_repo.py
import pytest
from app.config import get_settings
from app.db import mysql, chat_repo

@pytest.fixture
def conn():
    try:
        c = mysql.get_conn(get_settings())
    except Exception:
        pytest.skip("MySQL not available")
    yield c
    c.close()

def test_session_message_roundtrip(conn):
    s = chat_repo.get_or_create_session(conn, guest_token="test-guest-1")
    mid = chat_repo.append_message(conn, s["id"], "customer", "xin chào")
    msgs = chat_repo.get_messages(conn, s["id"])
    assert any(m["id"] == mid and m["content"] == "xin chào" for m in msgs)
    chat_repo.update_session(conn, s["id"], metadata={"draft": {"step": "items"}})
    s2 = chat_repo.get_or_create_session(conn, session_id=s["id"])
    assert s2["id"] == s["id"]
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/db/chat_repo.py
import json

_ALLOWED = {"status", "intent_label", "summary", "metadata", "closed_at"}

def get_or_create_session(conn, user_id=None, guest_token=None, source="widget",
                          external_user_id=None, session_id=None) -> dict:
    with conn.cursor() as cur:
        if session_id:
            cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (session_id,))
            row = cur.fetchone()
            if row:
                return row
        cur.execute(
            "INSERT INTO chat_sessions (user_id, guest_token, source, external_user_id) "
            "VALUES (%s, %s, %s, %s)", (user_id, guest_token, source, external_user_id))
        cur.execute("SELECT * FROM chat_sessions WHERE id = %s", (cur.lastrowid,))
        return cur.fetchone()

def append_message(conn, session_id, sender, content, content_type="text", metadata=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO chat_messages (session_id, sender, content, content_type, metadata) "
            "VALUES (%s, %s, %s, %s, %s)",
            (session_id, sender, content, content_type, json.dumps(metadata) if metadata else None))
        return cur.lastrowid

def get_messages(conn, session_id, limit=50) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM chat_messages WHERE session_id = %s ORDER BY id ASC LIMIT %s",
                    (session_id, limit))
        return list(cur.fetchall())

def update_session(conn, session_id, **fields):
    sets, vals = [], []
    for k, v in fields.items():
        if k not in _ALLOWED:
            raise ValueError(f"field not allowed: {k}")
        if k == "metadata" and isinstance(v, dict):
            v = json.dumps(v, ensure_ascii=False)
        sets.append(f"{k} = %s"); vals.append(v)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE chat_sessions SET {', '.join(sets)} WHERE id = %s", (*vals, session_id))
```

- [ ] **Step 3: Run** (cần MySQL chạy) — Expected: PASS.
- [ ] **Step 4: Commit** — `git commit -m "feat: chat session/message persistence repo"`

### Task 8: POST /chat/send + engine wiring

**Files:**
- Create: `ai-service/app/api/__init__.py`, `ai-service/app/api/chat.py`, `ai-service/app/deps.py`
- Modify: `ai-service/app/main.py` (include router, khởi tạo deps)
- Test: `ai-service/tests/test_chat_api.py`

**Interfaces:**
- Produces: `POST /chat/send` request `{session_id?, user_id?, guest_token?, message, context?: {current_page?, cart_items?}}` → response `{session_id: int, reply: EngineReply-dict, handoff: bool}`. `app.deps.get_engine()` chọn engine theo `settings.engine`; `app.deps.build_deps()` tạo EngineDeps production (GeminiClient + gemini_embed). Override được trong test qua `app.dependency_overrides`.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_chat_api.py
from fastapi.testclient import TestClient
from app.main import app
from app import deps
from app.engines.baseline import BaselineEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def test_chat_send_returns_reply(fake_store, monkeypatch):
    fake_store.add("faq", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    d = EngineDeps(llm=FakeLLM(['{"answer": "Trong ngày", "confidence": 0.9, "sources": []}']),
                   store=fake_store, settings=get_settings(), conn_factory=lambda: None)
    app.dependency_overrides[deps.get_engine] = lambda: BaselineEngine(d)
    client = TestClient(app)
    r = client.post("/chat/send", json={"message": "ship bao lâu", "guest_token": "g1"})
    assert r.status_code == 200
    body = r.json()
    assert body["reply"]["content"] == "Trong ngày"
    app.dependency_overrides.clear()
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/deps.py
from functools import lru_cache
from app.config import get_settings
from app.engines.base import EngineDeps
from app.knowledge.vector_store import VectorStore
from app.llm import GeminiClient, gemini_embed
from app.db import mysql

@lru_cache
def build_deps() -> EngineDeps:
    s = get_settings()
    return EngineDeps(
        llm=GeminiClient(s),
        store=VectorStore(s.chroma_persist_dir, embedding_fn=gemini_embed(s)),
        settings=s,
        conn_factory=lambda: mysql.get_conn(s))

def get_engine():
    s = get_settings()
    d = build_deps()
    if s.engine == "baseline":
        from app.engines.baseline import BaselineEngine
        return BaselineEngine(d)
    from app.engines.multiagent.graph import MultiAgentEngine  # Task 12+
    return MultiAgentEngine(d)
```

(Trước khi Task 12 tồn tại: `get_engine` fallback `BaselineEngine` nếu import multiagent lỗi — bọc try/except ImportError.)

```python
# ai-service/app/api/chat.py
from fastapi import APIRouter, Depends
from pydantic import BaseModel
from app import deps as deps_mod
from app.db import chat_repo

router = APIRouter()

class ChatSendRequest(BaseModel):
    session_id: int | None = None
    user_id: int | None = None
    guest_token: str | None = None
    message: str
    context: dict = {}

@router.post("/chat/send")
def chat_send(req: ChatSendRequest, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    history = []
    session = {"id": 0}
    if conn is not None:
        session = chat_repo.get_or_create_session(
            conn, user_id=req.user_id, guest_token=req.guest_token, session_id=req.session_id)
        history = [{"sender": m["sender"], "content": m["content"]}
                   for m in chat_repo.get_messages(conn, session["id"])]
        chat_repo.append_message(conn, session["id"], "customer", req.message)
    ctx = dict(req.context); ctx["session"] = session; ctx["user_id"] = req.user_id
    reply = engine.handle(history, req.message, ctx)
    if conn is not None:
        chat_repo.append_message(conn, session["id"], "bot", reply.content,
                                 content_type=reply.type if reply.type in ("text", "product_card", "order_card") else "text",
                                 metadata={"intent": reply.intent, "confidence": reply.confidence})
        if reply.handoff:
            chat_repo.update_session(conn, session["id"], status="handoff")
        conn.close()
    return {"session_id": session["id"], "reply": reply.model_dump(), "handoff": reply.handoff}
```

Trong `app/main.py` thêm: `from app.api.chat import router as chat_router; app.include_router(chat_router)`.

Thêm luôn endpoint reindex (spec §6.1) vào `app/api/chat.py`:

```python
@router.post("/knowledge/index")
def knowledge_index(source: str = "all", engine=Depends(deps_mod.get_engine)):
    from app.knowledge.indexer import reindex
    conn = engine.deps.conn_factory()
    n = reindex(engine.deps.store, conn, source)
    if conn: conn.close()
    return {"status": "ok", "indexed_count": n}
```

(Spec §6.1 còn liệt kê `GET /catalog/search` + `POST /orders/lookup` standalone — chức năng đã nằm trong engine/action agent; chỉ thêm endpoint standalone nếu cần debug, không bắt buộc cho widget/eval. Deviation có chủ đích.)

- [ ] **Step 3: Run full suite** `venv\Scripts\python -m pytest -v` — Expected: PASS.
- [ ] **Step 4: Smoke thật (cần GEMINI_API_KEY trong .env + MySQL + đã index):**

```bash
venv\Scripts\python -c "from app.deps import build_deps; from app.knowledge.indexer import reindex; d=build_deps(); print(reindex(d.store, d.conn_factory(), 'all'))"
venv\Scripts\uvicorn app.main:app --port 8000
curl -X POST localhost:8000/chat/send -H "Content-Type: application/json" -d "{\"message\": \"giao hang bao lau\", \"guest_token\": \"t1\"}"
```

Expected: JSON có `reply.content` tiếng Việt hợp lý.

- [ ] **Step 5: Commit** — `git commit -m "feat: /chat/send endpoint with engine selection and persistence"`

### Task 9: PHP proxy

**Files:**
- Create: `api/chat/send.php`, `api/chat/history.php`, `includes/chat_proxy_helpers.php`
- Test: `tests/chat_proxy_helpers_test.php`

**Interfaces:**
- Consumes: FastAPI `/chat/send` (Task 8).
- Produces: `POST /api/chat/send.php` (JSON body `{session_id?, message, guest_token?}`) — gắn `user_id` từ PHP session nếu đăng nhập, forward, trả nguyên response AI. `GET /api/chat/history.php?session_id=N`. Helper: `chat_build_forward_payload(array $input, ?int $authenticatedUserId): array` (lọc field cho phép, ép kiểu, gắn user_id); `chat_ai_service_url(): string` (đọc env `AI_SERVICE_URL`, default `http://localhost:8000`).

- [ ] **Step 1: Failing test**

```php
<?php
// tests/chat_proxy_helpers_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/chat_proxy_helpers.php';

$payload = chat_build_forward_payload(
    ['message' => 'hi', 'session_id' => '5', 'user_id' => '999', 'evil' => 'x'],
    42
);
assert_same('hi', $payload['message'], 'message passthrough');
assert_same(5, $payload['session_id'], 'session_id cast int');
assert_same(42, $payload['user_id'], 'user_id from auth, not from client');
assert_true(!array_key_exists('evil', $payload), 'unknown fields dropped');

$guest = chat_build_forward_payload(['message' => 'hi', 'guest_token' => 'abc'], null);
assert_same('abc', $guest['guest_token'], 'guest token passthrough');
assert_true(!isset($guest['user_id']), 'no user_id for guest');

echo "OK\n";
```

Run: `php tests/chat_proxy_helpers_test.php` — Expected: exit 1, missing include.

- [ ] **Step 2: Implement**

```php
<?php
// includes/chat_proxy_helpers.php
function chat_ai_service_url(): string
{
    $url = getenv('AI_SERVICE_URL');
    return $url !== false && $url !== '' ? rtrim($url, '/') : 'http://localhost:8000';
}

function chat_build_forward_payload(array $input, ?int $authenticatedUserId): array
{
    $payload = ['message' => trim((string) ($input['message'] ?? ''))];
    if (!empty($input['session_id'])) {
        $payload['session_id'] = (int) $input['session_id'];
    }
    if ($authenticatedUserId !== null) {
        $payload['user_id'] = $authenticatedUserId;
    } elseif (!empty($input['guest_token'])) {
        $payload['guest_token'] = substr((string) $input['guest_token'], 0, 64);
    }
    return $payload;
}
```

```php
<?php
// api/chat/send.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_proxy_helpers.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'method']); exit;
}
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (trim((string) ($input['message'] ?? '')) === '') {
    http_response_code(422); echo json_encode(['error' => 'message required']); exit;
}
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$payload = chat_build_forward_payload($input, $userId);

$ch = curl_init(chat_ai_service_url() . '/chat/send');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'ai_service_unavailable',
        'reply' => ['content' => 'Hệ thống chat đang bận, bạn vui lòng thử lại hoặc gọi hotline 0901 234 567.']]);
    exit;
}
http_response_code($code ?: 200);
echo $body;
```

`api/chat/history.php`: cùng bootstrap, GET `session_id` (int), forward `GET {AI_SERVICE_URL}/chat/history?session_id=N` — cần thêm endpoint FastAPI `GET /chat/history` trong `app/api/chat.py`:

```python
@router.get("/chat/history")
def chat_history(session_id: int, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    if conn is None:
        return {"messages": []}
    msgs = chat_repo.get_messages(conn, session_id)
    conn.close()
    return {"messages": [{"id": m["id"], "sender": m["sender"], "content": m["content"],
                          "content_type": m["content_type"], "created_at": str(m["created_at"])}
                         for m in msgs]}
```

Lưu ý session PHP: xác nhận `config/bootstrap.php` có `session_start()` và key user là `$_SESSION['user_id']` — nếu key khác (kiểm tra `pages/login.php`), dùng key thực tế.

- [ ] **Step 3: Run** `php tests/chat_proxy_helpers_test.php` — Expected: `OK`.
- [ ] **Step 4: Commit** — `git commit -m "feat: php chat proxy endpoints"`

### Task 10: Chat widget frontend

**Files:**
- Create: `assets/js/gau-chat-widget.js`, `assets/css/gau-chat-widget.css`
- Modify: `includes/footer.html` (thêm link CSS + script trước `</body>`; nếu footer là `.php`, dùng file thực tế)

**Interfaces:**
- Consumes: `POST /api/chat/send.php`, `GET /api/chat/history.php`.
- Produces: widget floating góc phải dưới; guest token lưu `localStorage['gau_chat_token']`; `session_id` lưu `localStorage['gau_chat_session']`; render text + product cards (`reply.products`) + order card (`reply.order`); quick replies tĩnh: "Xem menu bánh kem", "Kiểm tra đơn hàng", "Chính sách giao hàng"; polling history mỗi 4s khi cửa sổ mở (nhận reply của agent).

- [ ] **Step 1: Implement JS** (rút gọn, đủ chạy)

```javascript
// assets/js/gau-chat-widget.js
(function () {
  const API = '/cakev0/api/chat';
  const token = localStorage.gau_chat_token || (localStorage.gau_chat_token = 'g-' + Math.random().toString(36).slice(2));
  let sessionId = parseInt(localStorage.gau_chat_session || '0', 10) || null;
  let lastMsgId = 0, pollTimer = null;

  const root = document.createElement('div');
  root.id = 'gau-chat';
  root.innerHTML = `
    <button id="gau-chat-toggle">💬</button>
    <div id="gau-chat-window" hidden>
      <div class="gau-chat-header">Gấu Bakery – Hỗ trợ</div>
      <div class="gau-chat-messages"></div>
      <div class="gau-chat-quick">
        <button data-q="Xem menu bánh kem">Menu bánh kem</button>
        <button data-q="Kiểm tra đơn hàng">Kiểm tra đơn</button>
        <button data-q="Chính sách giao hàng">Giao hàng</button>
      </div>
      <form class="gau-chat-input"><input placeholder="Nhập tin nhắn..." /><button>Gửi</button></form>
    </div>`;
  document.body.appendChild(root);

  const win = root.querySelector('#gau-chat-window');
  const list = root.querySelector('.gau-chat-messages');
  const input = root.querySelector('input');

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function bubble(sender, html) {
    const el = document.createElement('div');
    el.className = 'gau-msg gau-msg-' + sender;
    el.innerHTML = html;
    list.appendChild(el);
    list.scrollTop = list.scrollHeight;
  }

  function productCards(products) {
    return '<div class="gau-cards">' + products.map(p =>
      `<a class="gau-card" href="/cakev0/pages/product.php?id=${p.id}">
         <img src="${esc(p.hinh_anh || '')}" alt=""><div>${esc(p.ten_banh)}</div>
         <strong>${Number(p.gia).toLocaleString('vi-VN')} VNĐ</strong></a>`).join('') + '</div>';
  }

  async function send(text) {
    bubble('customer', esc(text));
    const body = { message: text, guest_token: token };
    if (sessionId) body.session_id = sessionId;
    try {
      const r = await fetch(API + '/send.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const data = await r.json();
      if (data.session_id) { sessionId = data.session_id; localStorage.gau_chat_session = sessionId; }
      const rep = data.reply || {};
      let html = esc(rep.content || 'Xin lỗi, có lỗi xảy ra.');
      if (rep.products && rep.products.length) html += productCards(rep.products);
      if (rep.citations && rep.citations.length) html += '<div class="gau-cite">Nguồn: ' + rep.citations.map(c => esc(c.source)).join(', ') + '</div>';
      bubble('bot', html);
    } catch (e) { bubble('bot', 'Không kết nối được, thử lại sau nhé.'); }
  }

  async function poll() {
    if (!sessionId) return;
    const r = await fetch(`${API}/history.php?session_id=${sessionId}`);
    const data = await r.json();
    (data.messages || []).forEach(m => {
      if (m.id > lastMsgId) { lastMsgId = m.id; if (m.sender === 'agent') bubble('agent', esc(m.content)); }
    });
  }

  root.querySelector('#gau-chat-toggle').onclick = () => {
    win.hidden = !win.hidden;
    if (!win.hidden && !pollTimer) pollTimer = setInterval(poll, 4000);
    if (win.hidden && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  };
  root.querySelector('.gau-chat-input').onsubmit = e => {
    e.preventDefault();
    const t = input.value.trim(); if (!t) return;
    input.value = ''; send(t);
  };
  root.querySelectorAll('.gau-chat-quick button').forEach(b => b.onclick = () => send(b.dataset.q));
})();
```

- [ ] **Step 2: CSS**

```css
/* assets/css/gau-chat-widget.css */
#gau-chat { position: fixed; right: 20px; bottom: 20px; z-index: 9999; font-family: inherit; }
#gau-chat-toggle { width: 56px; height: 56px; border-radius: 50%; border: 0; background: #d2691e; color: #fff; font-size: 24px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.3); }
#gau-chat-window { position: fixed; right: 20px; bottom: 88px; width: 340px; height: 480px; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.25); display: flex; flex-direction: column; overflow: hidden; }
.gau-chat-header { background: #d2691e; color: #fff; padding: 12px; font-weight: 600; }
.gau-chat-messages { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.gau-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: 14px; }
.gau-msg-customer { align-self: flex-end; background: #d2691e; color: #fff; }
.gau-msg-bot, .gau-msg-agent { align-self: flex-start; background: #f1f1f1; }
.gau-msg-agent { border-left: 3px solid #d2691e; }
.gau-cards { display: flex; gap: 8px; overflow-x: auto; margin-top: 6px; }
.gau-card { min-width: 120px; border: 1px solid #eee; border-radius: 8px; padding: 6px; font-size: 12px; text-decoration: none; color: #333; }
.gau-card img { width: 100%; height: 70px; object-fit: cover; border-radius: 6px; }
.gau-cite { font-size: 11px; color: #888; margin-top: 4px; }
.gau-chat-quick { display: flex; gap: 6px; padding: 6px 10px; flex-wrap: wrap; }
.gau-chat-quick button { font-size: 12px; border: 1px solid #d2691e; color: #d2691e; background: #fff; border-radius: 14px; padding: 4px 10px; cursor: pointer; }
.gau-chat-input { display: flex; border-top: 1px solid #eee; }
.gau-chat-input input { flex: 1; border: 0; padding: 10px; outline: none; }
.gau-chat-input button { border: 0; background: #d2691e; color: #fff; padding: 0 16px; cursor: pointer; }
@media (max-width: 480px) { #gau-chat-window { width: calc(100vw - 24px); right: 12px; } }
```

- [ ] **Step 3: Nhúng vào footer** — thêm vào file footer dùng chung (kiểm tra `includes/footer.html` hay `includes/footer.php` được include ở các page):

```html
<link rel="stylesheet" href="/cakev0/assets/css/gau-chat-widget.css">
<script src="/cakev0/assets/js/gau-chat-widget.js" defer></script>
```

- [ ] **Step 4: Verify thủ công** — chạy PHP site + AI service, mở trang chủ, gửi "giao hàng bao lâu" → nhận reply có citation. Kiểm tra mobile viewport.
- [ ] **Step 5: Commit** — `git commit -m "feat: chat widget frontend with product cards and polling"`

**🏁 Milestone P1:** FAQ bot System A chạy end-to-end trên website.

---

## Phase 2 — System B multi-agent (LangGraph)

### Task 11: Vietnamese Normalizer

**Files:**
- Create: `ai-service/app/nlp/__init__.py`, `ai-service/app/nlp/normalizer.py`, `ai-service/app/nlp/teencode.json`
- Test: `ai-service/tests/test_normalizer.py`

**Interfaces:**
- Produces: `normalize(text: str) -> str` — lowercase-preserving word-boundary replace teencode/viết tắt/TMĐT glossary; giữ nguyên số, mã đơn, SĐT. `load_teencode() -> dict` cached.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_normalizer.py
from app.nlp.normalizer import normalize

def test_teencode():
    assert normalize("shop oi co banh sn ko") == "shop ơi có bánh sinh nhật không"

def test_keeps_numbers_and_phone():
    assert "0901234567" in normalize("don cua sdt 0901234567 den dau r")

def test_tmdt_glossary():
    out = normalize("ship COD dc ko, co freeship ko")
    assert "giao hàng" in out and "thanh toán khi nhận hàng" in out

def test_plain_vietnamese_unchanged():
    s = "Bánh kem dâu giá bao nhiêu?"
    assert normalize(s) == s
```

- [ ] **Step 2: Implement**

`teencode.json` (≥50 mapping; mẫu bắt buộc):

```json
{
  "ko": "không", "k": "không", "hok": "không", "dc": "được", "đc": "được",
  "r": "rồi", "roi": "rồi", "j": "gì", "sn": "sinh nhật", "sp": "sản phẩm",
  "sdt": "số điện thoại", "đt": "điện thoại", "e": "em", "a": "anh", "c": "chị",
  "mik": "mình", "mk": "mình", "bn": "bao nhiêu", "nhiu": "nhiêu", "ntn": "như thế nào",
  "oi": "ơi", "co": "có", "ship": "giao hàng", "cod": "thanh toán khi nhận hàng",
  "freeship": "miễn phí giao hàng", "bill": "hóa đơn", "stk": "số tài khoản",
  "ck": "chuyển khoản", "add": "địa chỉ", "od": "đơn hàng", "sz": "kích cỡ", "size": "kích cỡ"
}
```

```python
# ai-service/app/nlp/normalizer.py
import json, os, re
from functools import lru_cache

@lru_cache
def load_teencode() -> dict:
    path = os.path.join(os.path.dirname(__file__), "teencode.json")
    return json.load(open(path, encoding="utf-8"))

def normalize(text: str) -> str:
    mapping = load_teencode()
    def repl(m):
        w = m.group(0)
        return mapping.get(w.lower(), w)
    return re.sub(r"[\wÀ-ỹ]+", repl, text)
```

Chú ý test 1: "oi"→"ơi", "co"→"có", "sn"→"sinh nhật", "ko"→"không" đều qua dict — nếu kết quả khác kỳ vọng, chỉnh dict chứ không chỉnh thuật toán. Không phục hồi dấu bằng model (BARTpho) trong scope này — dictionary-only; ghi rõ hạn chế trong báo cáo.

- [ ] **Step 3: Run** — PASS. **Step 4: Commit** — `git commit -m "feat: vietnamese teencode normalizer"`

### Task 12: Router Agent + graph skeleton

**Files:**
- Create: `ai-service/app/engines/multiagent/__init__.py`, `ai-service/app/engines/multiagent/state.py`, `ai-service/app/engines/multiagent/router.py`, `ai-service/app/engines/multiagent/graph.py`
- Test: `ai-service/tests/test_router.py`, `ai-service/tests/test_multiagent_graph.py`

**Interfaces:**
- Produces: `AgentState` TypedDict: `query, normalized_query, intent, confidence, retrieved_docs, products, action_result, response, citations, should_handoff, handoff_reasons, retry_count, history, context`. `classify_intent(llm, text) -> tuple[str, float]` — 11 intent theo spec (`faq, catalog_search, product_recommend, order_status, order_create, policy_shipping, policy_payment, policy_return, complaint, chitchat, handoff_request`), keyword fallback khi JSON parse fail. `MultiAgentEngine(deps)` implement Engine protocol (Task 6). `build_graph(deps)` trả compiled LangGraph.

- [ ] **Step 1: Failing tests**

```python
# ai-service/tests/test_router.py
from app.engines.multiagent.router import classify_intent, keyword_fallback
from app.llm import FakeLLM

def test_classify_via_llm_json():
    llm = FakeLLM(['{"intent": "order_status", "confidence": 0.92}'])
    assert classify_intent(llm, "đơn 123 đến đâu rồi") == ("order_status", 0.92)

def test_classify_invalid_json_uses_keyword_fallback():
    llm = FakeLLM(["xin chao"])
    intent, conf = classify_intent(llm, "cho gặp người thật")
    assert intent == "handoff_request"

def test_keyword_fallback_defaults_faq():
    assert keyword_fallback("bánh này ngon không")[0] == "faq"
```

```python
# ai-service/tests/test_multiagent_graph.py
from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def _eng(store, replies):
    return MultiAgentEngine(EngineDeps(llm=FakeLLM(replies), store=store,
                                       settings=get_settings(), conn_factory=lambda: None))

def test_chitchat_flow(fake_store):
    eng = _eng(fake_store, ['{"intent": "chitchat", "confidence": 0.95}', "Chào bạn, Gấu Bakery nghe ạ!"])
    r = eng.handle([], "chào shop", {})
    assert r.intent == "chitchat"
    assert "Chào" in r.content
    assert r.handoff is False

def test_faq_flow_with_citation(fake_store):
    fake_store.add("faq", ["faq-1"], ["HOI: ship\nDAP: trong ngày"], [{}])
    eng = _eng(fake_store, ['{"intent": "faq", "confidence": 0.9}',
                            '{"answer": "Giao trong ngày ạ", "confidence": 0.88, "sources": ["faq-1"]}'])
    r = eng.handle([], "ship bao lâu", {})
    assert r.intent == "faq"
    assert r.citations[0]["source"] == "faq-1"
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/engines/multiagent/state.py
from typing import TypedDict, Any

class AgentState(TypedDict, total=False):
    query: str
    normalized_query: str
    intent: str
    confidence: float
    retrieved_docs: list
    products: list
    action_result: dict
    response: str
    citations: list
    should_handoff: bool
    handoff_reasons: list
    retry_count: int
    history: list
    context: dict
```

```python
# ai-service/app/engines/multiagent/router.py
import json, re
from app.llm import LLMClient

INTENTS = ["faq", "catalog_search", "product_recommend", "order_status", "order_create",
           "policy_shipping", "policy_payment", "policy_return", "complaint",
           "chitchat", "handoff_request"]

ROUTER_SYSTEM = """Bạn là router của hệ thống CSKH Gấu Bakery.
Phân loại câu của khách vào đúng 1 intent:
faq | catalog_search | product_recommend | order_status | order_create |
policy_shipping | policy_payment | policy_return | complaint | chitchat | handoff_request
Chỉ trả JSON: {"intent": "...", "confidence": 0.0-1.0}"""

_KEYWORDS = [
    ("handoff_request", ["người thật", "nhân viên", "gặp quản lý", "hỗ trợ viên"]),
    ("complaint", ["khiếu nại", "bực", "tệ", "hỏng", "sai đơn", "hoàn tiền"]),
    ("order_status", ["đơn", "kiểm tra đơn", "đến đâu", "mã đơn"]),
    ("order_create", ["đặt bánh", "mua bánh", "chốt đơn", "order"]),
    ("policy_shipping", ["giao hàng", "phí ship", "vận chuyển"]),
    ("policy_payment", ["thanh toán", "chuyển khoản", "vnpay", "thanh toán khi nhận hàng"]),
    ("policy_return", ["đổi trả", "đổi bánh", "trả hàng"]),
    ("catalog_search", ["có bánh", "tìm bánh", "menu", "giá bánh"]),
    ("chitchat", ["chào", "cảm ơn", "hello", "hi "]),
]

def keyword_fallback(text: str) -> tuple[str, float]:
    low = text.lower()
    for intent, kws in _KEYWORDS:
        if any(k in low for k in kws):
            return intent, 0.55
    return "faq", 0.4

def classify_intent(llm: LLMClient, text: str) -> tuple[str, float]:
    raw = llm.generate(ROUTER_SYSTEM, text)
    m = re.search(r"\{.*\}", raw, re.S)
    if m:
        try:
            d = json.loads(m.group(0))
            if d.get("intent") in INTENTS:
                return d["intent"], float(d.get("confidence", 0.5))
        except (json.JSONDecodeError, ValueError, TypeError):
            pass
    return keyword_fallback(text)
```

```python
# ai-service/app/engines/multiagent/graph.py
from langgraph.graph import StateGraph, END
from app.engines.base import EngineDeps
from app.engines.multiagent.state import AgentState
from app.engines.multiagent import router as router_mod
from app.models.chat import EngineReply
from app.nlp.normalizer import normalize

RETRIEVAL_INTENTS = {"faq", "catalog_search", "product_recommend",
                     "policy_shipping", "policy_payment", "policy_return"}
ACTION_INTENTS = {"order_status", "order_create"}
HANDOFF_INTENTS = {"complaint", "handoff_request"}

def build_graph(deps: EngineDeps):
    from app.engines.multiagent.retrieval import retrieval_node
    from app.engines.multiagent.action import action_node
    from app.engines.multiagent.handoff import handoff_node
    from app.engines.multiagent.aggregate import aggregate_node

    def normalize_node(state: AgentState):
        # settings.enable_normalizer=False → ablation B′ (spec §9): thêm field
        # `enable_normalizer: bool = True` vào Settings (Task 2)
        if not getattr(deps.settings, "enable_normalizer", True):
            return {"normalized_query": state["query"]}
        return {"normalized_query": normalize(state["query"])}

    def router_node(state: AgentState):
        intent, conf = router_mod.classify_intent(deps.llm, state["normalized_query"])
        return {"intent": intent, "confidence": conf}

    def chitchat_node(state: AgentState):
        resp = deps.llm.generate(
            "Bạn là trợ lý Gấu Bakery, đáp ngắn gọn thân thiện tiếng Việt.",
            state["query"])
        return {"response": resp}

    def route(state: AgentState) -> str:
        i = state["intent"]
        if i in HANDOFF_INTENTS:
            return "handoff"
        if i in ACTION_INTENTS:
            return "action"
        if i == "chitchat":
            return "chitchat"
        return "retrieval"

    g = StateGraph(AgentState)
    g.add_node("normalize", normalize_node)
    g.add_node("router", router_node)
    g.add_node("retrieval", lambda s: retrieval_node(deps, s))
    g.add_node("action", lambda s: action_node(deps, s))
    g.add_node("chitchat", chitchat_node)
    g.add_node("handoff", lambda s: handoff_node(deps, s))
    g.add_node("aggregate", lambda s: aggregate_node(deps, s))
    g.set_entry_point("normalize")
    g.add_edge("normalize", "router")
    g.add_conditional_edges("router", route,
        {"retrieval": "retrieval", "action": "action", "chitchat": "chitchat", "handoff": "handoff"})
    g.add_edge("retrieval", "aggregate")
    g.add_edge("action", "aggregate")
    g.add_edge("chitchat", "aggregate")
    g.add_edge("handoff", "aggregate")
    g.add_edge("aggregate", END)
    return g.compile()

class MultiAgentEngine:
    def __init__(self, deps: EngineDeps):
        self.deps = deps
        self._graph = build_graph(deps)

    def handle(self, history, user_message, context) -> EngineReply:
        state: AgentState = {"query": user_message, "history": history, "context": context,
                             "retry_count": 0, "citations": [], "products": [],
                             "should_handoff": False, "handoff_reasons": []}
        out = self._graph.invoke(state)
        return EngineReply(
            type=out.get("action_result", {}).get("type", "text"),
            content=out.get("response", ""),
            citations=out.get("citations", []),
            products=out.get("products", []),
            intent=out.get("intent", "unknown"),
            confidence=out.get("confidence", 0.0),
            handoff=out.get("should_handoff", False),
            order=out.get("action_result", {}).get("order"))
```

Để test graph skeleton pass trước khi Task 13–15 tồn tại: tạo 3 file stub thật `retrieval.py`, `action.py`, `handoff.py`, `aggregate.py` với node trả về tối thiểu — Task 13–15 thay stub bằng bản đầy đủ. Stub aggregate:

```python
# ai-service/app/engines/multiagent/aggregate.py
def aggregate_node(deps, state):
    return {"response": state.get("response", "")}
```

Stub retrieval (đủ cho test_faq_flow_with_citation — thực chất là bản gần đầy đủ, Task 13 mở rộng retry/rerank):

```python
# ai-service/app/engines/multiagent/retrieval.py
from app.engines.baseline import parse_llm_json

_COLLECTION = {"catalog_search": "products", "product_recommend": "products",
               "policy_shipping": "policies", "policy_payment": "policies",
               "policy_return": "policies", "faq": "faq"}

RETRIEVAL_SYSTEM = """Bạn là trợ lý Gấu Bakery. Dựa vào TÀI LIỆU trả lời tiếng Việt, trích nguồn.
Trả JSON: {"answer": "...", "confidence": 0.0-1.0, "sources": ["doc-id"]}"""

def retrieval_node(deps, state):
    col = _COLLECTION.get(state["intent"], "faq")
    docs = deps.store.query(col, state["normalized_query"], top_k=5)
    block = "\n---\n".join(f"[{d.id}] {d.text}" for d in docs)
    parsed = parse_llm_json(deps.llm.generate(
        RETRIEVAL_SYSTEM, f"TÀI LIỆU:\n{block}\n\nKHÁCH: {state['query']}"))
    by_id = {d.id: d for d in docs}
    cits = [{"source": s, "excerpt": by_id[s].text[:120]} for s in parsed["sources"] if s in by_id]
    products = [d.metadata | {"ten_banh": d.text.split("\n")[0].replace("SAN PHAM: ", "")}
                for d in docs if d.id.startswith("product-")] if col == "products" else []
    return {"response": parsed["answer"], "confidence": min(state.get("confidence", 1.0), parsed["confidence"]),
            "citations": cits, "retrieved_docs": [d.id for d in docs], "products": products[:5]}
```

Stub action/handoff trả `{"response": "..."}` tĩnh.

- [ ] **Step 3: Run** — 2 test files PASS.
- [ ] **Step 4: Commit** — `git commit -m "feat: multiagent engine with router and langgraph skeleton (System B)"`

### Task 13: Retrieval Agent hoàn chỉnh (retry + rewrite)

**Files:**
- Modify: `ai-service/app/engines/multiagent/retrieval.py`, `ai-service/app/engines/multiagent/graph.py`
- Test: `ai-service/tests/test_retrieval_agent.py`

**Interfaces:**
- Produces: retrieval_node như trên, thêm: nếu `parsed["confidence"] < 0.5` và `retry_count < 2` → node trả `{"retry_count": +1, "normalized_query": rewritten}` và graph edge conditional `retrieval → retrieval` (rewrite bằng LLM: "Viết lại câu truy vấn rõ nghĩa hơn: {query}"); sau max retry → `should_handoff=True, handoff_reasons=["max_retries"]`. `product_recommend` rerank: sản phẩm có promotion (query bảng `promotions` qua conn nếu có) đưa lên đầu.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_retrieval_agent.py
from app.engines.multiagent.graph import MultiAgentEngine
from app.engines.base import EngineDeps
from app.llm import FakeLLM
from app.config import get_settings

def test_low_confidence_retries_then_handoff(fake_store):
    replies = ['{"intent": "faq", "confidence": 0.9}',
               '{"answer": "?", "confidence": 0.2, "sources": []}',   # lần 1 kém
               "câu hỏi giao hàng viết rõ",                            # rewrite
               '{"answer": "?", "confidence": 0.2, "sources": []}',   # lần 2 kém
               "câu hỏi giao hàng viết rõ hơn",                        # rewrite 2
               '{"answer": "?", "confidence": 0.2, "sources": []}']   # lần 3 kém → handoff
    eng = MultiAgentEngine(EngineDeps(llm=FakeLLM(replies), store=fake_store,
                                      settings=get_settings(), conn_factory=lambda: None))
    r = eng.handle([], "abcxyz", {})
    assert r.handoff is True
```

- [ ] **Step 2: Implement** — retrieval_node trả thêm key `needs_retry: bool`; trong `build_graph` thay `g.add_edge("retrieval", "aggregate")` bằng:

```python
def after_retrieval(state):
    if state.get("needs_retry") and state.get("retry_count", 0) < 2:
        return "rewrite"
    return "aggregate"

def rewrite_node(state):
    new_q = deps.llm.generate("Viết lại câu truy vấn tiếng Việt rõ nghĩa hơn, chỉ trả câu viết lại.",
                              state["normalized_query"])
    return {"normalized_query": new_q.strip(), "retry_count": state.get("retry_count", 0) + 1}

g.add_node("rewrite", rewrite_node)
g.add_conditional_edges("retrieval", after_retrieval, {"rewrite": "rewrite", "aggregate": "aggregate"})
g.add_edge("rewrite", "retrieval")
```

Trong retrieval_node cuối hàm:

```python
needs_retry = parsed["confidence"] < 0.5
out = {..., "needs_retry": needs_retry}
if needs_retry and state.get("retry_count", 0) >= 2:
    out["should_handoff"] = True
    out["handoff_reasons"] = state.get("handoff_reasons", []) + ["max_retries"]
    out["needs_retry"] = False
return out
```

Rerank promotion (chỉ khi `state["intent"] == "product_recommend"` và conn khả dụng):

```python
def _promoted_ids(conn) -> set:
    with conn.cursor() as cur:
        cur.execute("SELECT DISTINCT banh_id FROM promotions WHERE NOW() BETWEEN start_date AND end_date")
        return {r["banh_id"] for r in cur.fetchall()}
```

(Kiểm tra `DESCRIBE promotions` — nếu tên cột khác (`product_id`, `ngay_bat_dau`...), dùng tên thật; nếu bảng không có mapping per-product, bỏ rerank, ghi chú trong code.)

- [ ] **Step 3: Run** — PASS toàn suite. **Step 4: Commit** — `git commit -m "feat: retrieval agent retry with query rewrite and promotion rerank"`

### Task 14: Action Agent — order lookup

**Files:**
- Create: `ai-service/app/db/orders_repo.py`
- Modify: `ai-service/app/engines/multiagent/action.py`
- Test: `ai-service/tests/test_orders_repo.py`, `ai-service/tests/test_action_agent.py`

**Interfaces:**
- Produces: `orders_repo.lookup_orders(conn, phone=None, order_id=None, user_id=None, limit=5) -> list[dict]` (mỗi dict: id, status, total_amount, created_at, payment_method, items: list `{ten_banh, quantity, price}`). `extract_phone(text) -> str | None` (regex `(0|\+84)\d{8,10}`). `extract_order_id(text) -> int | None` (regex `(?:đơn|don|#|mã|ma)\s*#?(\d{1,8})`). action_node: intent `order_status` → nếu user đăng nhập (`context["user_id"]`) lookup theo user; guest → cần phone trong câu, không có → hỏi lại (response, không handoff). Kết quả → `action_result = {"type": "order_status", "orders": [...]}` + response text tóm tắt. Intent `order_create` → stub chuyển Task 17.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_action_agent.py
from app.engines.multiagent.action import extract_phone, extract_order_id, format_order_summary

def test_extract_phone():
    assert extract_phone("sdt 0901234567 nhé") == "0901234567"
    assert extract_phone("không có gì") is None

def test_extract_order_id():
    assert extract_order_id("đơn #123 đến đâu") == 123
    assert extract_order_id("don 45 den dau roi") == 45

def test_format_order_summary():
    text = format_order_summary([{"id": 7, "status": "pending", "total_amount": 350000,
                                  "created_at": "2026-07-19", "payment_method": "COD",
                                  "items": [{"ten_banh": "Bánh kem dâu", "quantity": 1, "price": 350000}]}])
    assert "#7" in text and "350.000" in text and "pending" in text.lower() or "chờ" in text
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/db/orders_repo.py
def lookup_orders(conn, phone=None, order_id=None, user_id=None, limit=5) -> list[dict]:
    where, params = [], []
    if order_id: where.append("o.id = %s"); params.append(order_id)
    if phone: where.append("o.phone = %s"); params.append(phone)
    if user_id: where.append("o.user_id = %s"); params.append(user_id)
    if not where:
        return []
    with conn.cursor() as cur:
        cur.execute(
            "SELECT o.id, o.status, o.total_amount, o.created_at, o.payment_method "
            f"FROM orders o WHERE {' AND '.join(where)} ORDER BY o.created_at DESC LIMIT %s",
            (*params, limit))
        orders = list(cur.fetchall())
        for o in orders:
            cur.execute(
                "SELECT b.ten_banh, oi.quantity, oi.price FROM order_items oi "
                "JOIN banh b ON b.id = oi.banh_id WHERE oi.order_id = %s", (o["id"],))
            o["items"] = list(cur.fetchall())
    return orders
```

```python
# ai-service/app/engines/multiagent/action.py
import re

STATUS_VI = {"pending": "Chờ xác nhận", "confirmed": "Đã xác nhận", "shipping": "Đang giao",
             "delivered": "Đã giao", "cancelled": "Đã hủy", "cod_not_deposited": "COD chờ cọc",
             "paid": "Đã thanh toán"}

def extract_phone(text: str):
    m = re.search(r"(?:\+84|0)\d{8,10}", text)
    return m.group(0) if m else None

def extract_order_id(text: str):
    m = re.search(r"(?:đơn|don|#|mã|ma)\s*#?(\d{1,8})", text, re.I)
    return int(m.group(1)) if m else None

def fmt_vnd(v) -> str:
    return f"{int(v):,}".replace(",", ".") + " VNĐ"

def format_order_summary(orders: list[dict]) -> str:
    if not orders:
        return "Mình không tìm thấy đơn hàng nào khớp thông tin bạn cung cấp."
    lines = []
    for o in orders:
        status = STATUS_VI.get(str(o["status"]).lower(), o["status"])
        items = ", ".join(f"{i['ten_banh']} x{i['quantity']}" for i in o["items"])
        lines.append(f"Đơn #{o['id']} — {status} — {fmt_vnd(o['total_amount'])} ({items})")
    return "Thông tin đơn hàng của bạn:\n" + "\n".join(lines)

def action_node(deps, state):
    if state["intent"] == "order_create":
        from app.engines.multiagent.order_create import order_create_node  # Task 17
        return order_create_node(deps, state)
    # order_status
    conn = deps.conn_factory()
    user_id = state.get("context", {}).get("user_id")
    phone = extract_phone(state["query"])
    order_id = extract_order_id(state["query"])
    if conn is None or (not user_id and not phone):
        return {"response": "Bạn cho mình xin số điện thoại đặt hàng (hoặc đăng nhập) để tra cứu đơn nhé."}
    orders = __import__("app.db.orders_repo", fromlist=["lookup_orders"]).lookup_orders(
        conn, phone=phone, order_id=order_id, user_id=user_id)
    conn.close()
    def _ser(o):
        o = dict(o); o["created_at"] = str(o["created_at"]); o["total_amount"] = float(o["total_amount"])
        return o
    return {"response": format_order_summary(orders),
            "action_result": {"type": "order_status", "orders": [_ser(o) for o in orders]}}
```

(Trước Task 17, tạo `order_create.py` stub: `def order_create_node(deps, state): return {"response": "Bạn muốn đặt bánh nào ạ? Chức năng đặt hàng sắp mở."}`.)

- [ ] **Step 3: Run** — PASS. **Step 4: Commit** — `git commit -m "feat: action agent order lookup"`

### Task 15: Handoff Policy Agent + tickets + /chat/handoff

**Files:**
- Create: `ai-service/app/db/ticket_repo.py`
- Modify: `ai-service/app/engines/multiagent/handoff.py`, `ai-service/app/engines/multiagent/aggregate.py`, `ai-service/app/api/chat.py`
- Test: `ai-service/tests/test_handoff.py`

**Interfaces:**
- Produces: `decide_handoff(state, threshold) -> tuple[bool, list[str]]` — 4 factor theo spec (intent ∈ {complaint, handoff_request}; confidence < threshold; keyword ∈ {"khiếu nại", "hoàn tiền gấp", "gặp quản lý"}; retry_count ≥ 2). `ticket_repo.create_ticket(conn, session_id, subject, priority='medium', draft_response=None) -> int`; `ticket_repo.list_open_tickets(conn) -> list[dict]`. handoff_node: quyết định + sinh draft response (LLM tóm tắt + đề xuất trả lời) + tạo ticket khi có conn + session id trong context. `POST /chat/handoff` body `{session_id, reason, priority}` → `{ticket_id}`. aggregate_node cuối: nếu `should_handoff` → nối câu "Mình đã chuyển cuộc hội thoại đến nhân viên hỗ trợ, bạn chờ chút nhé." vào response.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_handoff.py
from app.engines.multiagent.handoff import decide_handoff

def test_intent_trigger():
    ok, reasons = decide_handoff({"intent": "complaint", "confidence": 0.9,
                                  "query": "bánh hỏng", "retry_count": 0}, 0.6)
    assert ok and "intent_triggers_handoff" in reasons

def test_confidence_trigger():
    ok, reasons = decide_handoff({"intent": "faq", "confidence": 0.3,
                                  "query": "x", "retry_count": 0}, 0.6)
    assert ok and any(r.startswith("low_confidence") for r in reasons)

def test_keyword_trigger():
    ok, reasons = decide_handoff({"intent": "faq", "confidence": 0.9,
                                  "query": "tôi muốn gặp quản lý", "retry_count": 0}, 0.6)
    assert ok and "keyword_match" in reasons

def test_no_trigger():
    ok, _ = decide_handoff({"intent": "faq", "confidence": 0.9,
                            "query": "giao lâu không", "retry_count": 0}, 0.6)
    assert not ok
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/engines/multiagent/handoff.py
HANDOFF_KEYWORDS = ["khiếu nại", "hoàn tiền gấp", "gặp quản lý"]
HANDOFF_INTENTS = {"complaint", "handoff_request"}

def decide_handoff(state: dict, threshold: float) -> tuple[bool, list[str]]:
    reasons = []
    if state.get("intent") in HANDOFF_INTENTS:
        reasons.append("intent_triggers_handoff")
    if state.get("confidence", 1.0) < threshold:
        reasons.append(f"low_confidence_{state.get('confidence')}")
    low = state.get("query", "").lower()
    if any(k in low for k in HANDOFF_KEYWORDS):
        reasons.append("keyword_match")
    if state.get("retry_count", 0) >= 2:
        reasons.append(f"max_retries_{state.get('retry_count')}")
    return bool(reasons), reasons

def handoff_node(deps, state):
    ok, reasons = decide_handoff(state, deps.settings.handoff_confidence_threshold)
    draft = deps.llm.generate(
        "Bạn là trợ lý CSKH. Viết draft trả lời lịch sự cho nhân viên tham khảo (tiếng Việt, ngắn).",
        f"Khách nói: {state['query']}")
    session = state.get("context", {}).get("session") or {}
    conn = deps.conn_factory()
    if conn is not None and session.get("id"):
        from app.db import ticket_repo
        priority = "high" if state.get("intent") == "complaint" else "medium"
        ticket_repo.create_ticket(conn, session["id"],
                                  subject=state["query"][:200], priority=priority, draft_response=draft)
        conn.close()
    return {"should_handoff": ok, "handoff_reasons": reasons,
            "response": "Mình đã ghi nhận và chuyển cho nhân viên hỗ trợ. Bạn chờ trong ít phút nhé, "
                        "hoặc gọi hotline 0901 234 567."}
```

```python
# ai-service/app/db/ticket_repo.py
def create_ticket(conn, session_id, subject, priority="medium", draft_response=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO support_tickets (session_id, subject, priority, draft_response) "
            "VALUES (%s, %s, %s, %s)", (session_id, subject, priority, draft_response))
        return cur.lastrowid

def list_open_tickets(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM support_tickets WHERE status IN ('open','in_progress') ORDER BY created_at DESC")
        return list(cur.fetchall())
```

`/chat/handoff` trong `app/api/chat.py`:

```python
class HandoffRequest(BaseModel):
    session_id: int
    reason: str = ""
    priority: str = "medium"

@router.post("/chat/handoff")
def chat_handoff(req: HandoffRequest, engine=Depends(deps_mod.get_engine)):
    from app.db import ticket_repo, chat_repo as cr
    conn = engine.deps.conn_factory()
    tid = ticket_repo.create_ticket(conn, req.session_id, subject=req.reason or "Yêu cầu hỗ trợ",
                                    priority=req.priority)
    cr.update_session(conn, req.session_id, status="handoff")
    conn.close()
    return {"ticket_id": tid, "status": "open"}
```

aggregate_node bản đầy đủ:

```python
# ai-service/app/engines/multiagent/aggregate.py
def aggregate_node(deps, state):
    resp = state.get("response", "")
    if state.get("should_handoff") and "nhân viên" not in resp:
        resp = (resp + "\n\nMình đã chuyển cuộc hội thoại đến nhân viên hỗ trợ, bạn chờ chút nhé.").strip()
    return {"response": resp}
```

- [ ] **Step 3: Run** — PASS. **Step 4: Set `ENGINE=multiagent` trong `.env`, smoke lại curl như Task 8.** **Step 5: Commit** — `git commit -m "feat: handoff policy agent, tickets, /chat/handoff"`

**🏁 Milestone P2:** System B chạy cùng contract với System A, đổi qua `ENGINE`.

---

## Phase 3 — Chốt đơn COD trong chat

### Task 16: PHP internal order API

**Files:**
- Create: `api/internal/orders/create.php`, `includes/internal_order_api.php`
- Test: `tests/internal_order_api_test.php`

**Interfaces:**
- Consumes: logic INSERT của `pages/checkout.php:121` (orders + order_items).
- Produces: `internal_api_verify_signature(string $body, ?string $signature, string $secret): bool` (hash_equals HMAC-SHA256 hex); `internal_order_validate(array $p): ?string` (null = hợp lệ, string = lỗi); `create_order_internal(mysqli $conn, array $p): array` — validate user/sản phẩm tồn tại, quantity 1–20, **tính lại total server-side từ giá DB**, INSERT orders (`payment_method='Tiền mặt'`, `status='pending'`) + order_items, trả `['order_id' => int, 'total_amount' => float, 'status' => 'pending']` hoặc `['error' => ..., 'reason' => ...]`. Endpoint đọc raw body, verify HMAC (sai → 401), gọi helper, trả JSON.

- [ ] **Step 1: Failing test**

```php
<?php
// tests/internal_order_api_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/internal_order_api.php';

$secret = 'test-secret';
$body = '{"user_id": 1}';
$sig = hash_hmac('sha256', $body, $secret);
assert_true(internal_api_verify_signature($body, $sig, $secret), 'valid signature accepted');
assert_true(!internal_api_verify_signature($body, 'sai', $secret), 'invalid signature rejected');
assert_true(!internal_api_verify_signature($body, null, $secret), 'missing signature rejected');

assert_same('missing_user', internal_order_validate(['items' => [['banh_id' => 1, 'quantity' => 1]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'user required');
assert_same('invalid_items', internal_order_validate(['user_id' => 1, 'items' => [], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'items required');
assert_same('invalid_quantity', internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 99]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'quantity cap 20');
assert_same('invalid_phone', internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 1]], 'recipient_name' => 'A', 'phone' => '123', 'address' => 'HN']), 'phone regex');
assert_same(null, internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 2]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'valid payload passes');

echo "OK\n";
```

Run: `php tests/internal_order_api_test.php` — Expected: FAIL missing include.

- [ ] **Step 2: Implement**

```php
<?php
// includes/internal_order_api.php
function internal_api_verify_signature(string $body, ?string $signature, string $secret): bool
{
    if ($signature === null || $signature === '' || $secret === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
}

function internal_order_validate(array $p): ?string
{
    if (empty($p['user_id']) || (int) $p['user_id'] <= 0) return 'missing_user';
    if (empty($p['items']) || !is_array($p['items'])) return 'invalid_items';
    foreach ($p['items'] as $item) {
        $qty = (int) ($item['quantity'] ?? 0);
        if (empty($item['banh_id']) || $qty < 1 || $qty > 20) return 'invalid_quantity';
    }
    if (trim((string) ($p['recipient_name'] ?? '')) === '') return 'missing_recipient';
    if (!preg_match('/^(0|\+84)\d{8,10}$/', (string) ($p['phone'] ?? ''))) return 'invalid_phone';
    if (trim((string) ($p['address'] ?? '')) === '') return 'missing_address';
    return null;
}

function create_order_internal(mysqli $conn, array $p): array
{
    if (($err = internal_order_validate($p)) !== null) {
        return ['error' => 'validation', 'reason' => $err];
    }
    $userId = (int) $p['user_id'];
    $stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) { $stmt->close(); return ['error' => 'validation', 'reason' => 'user_not_found']; }
    $stmt->close();

    $total = 0.0;
    $resolved = [];
    foreach ($p['items'] as $item) {
        $banhId = (int) $item['banh_id'];
        $qty = (int) $item['quantity'];
        $stmt = $conn->prepare('SELECT id, gia FROM banh WHERE id = ?');
        $stmt->bind_param('i', $banhId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['error' => 'validation', 'reason' => 'product_not_found:' . $banhId];
        $total += ((float) $row['gia']) * $qty;
        $resolved[] = ['banh_id' => $banhId, 'quantity' => $qty, 'price' => (float) $row['gia']];
    }

    $conn->begin_transaction();
    try {
        $name = trim((string) $p['recipient_name']);
        $phone = (string) $p['phone'];
        $address = trim((string) $p['address']);
        $note = trim((string) ($p['note'] ?? 'Đặt qua chat'));
        $method = 'Tiền mặt';
        $status = 'pending';
        $stmt = $conn->prepare(
            'INSERT INTO orders (user_id, recipient_name, phone, address, note, payment_method, total_amount, status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isssssds', $userId, $name, $phone, $address, $note, $method, $total, $status);
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare('INSERT INTO order_items (order_id, banh_id, quantity, price) VALUES (?, ?, ?, ?)');
        foreach ($resolved as $r) {
            $stmt->bind_param('iiid', $orderId, $r['banh_id'], $r['quantity'], $r['price']);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
        return ['order_id' => $orderId, 'total_amount' => $total, 'status' => $status];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Internal order API error: ' . $e->getMessage());
        return ['error' => 'server', 'reason' => 'insert_failed'];
    }
}
```

(Đối chiếu cột INSERT với `pages/checkout.php:121` — checkout có thêm `coupon_code`, `coupon_discount`; chat order không dùng coupon nên bỏ qua nếu cột có default, nếu NOT NULL thì truyền `''` và `0`. Kiểm tra `DESCRIBE orders` khi implement. Đối chiếu cột `order_items` (`price` hay `gia`) với checkout.php.)

```php
<?php
// api/internal/orders/create.php
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../includes/internal_order_api.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'method']); exit; }

$secret = getenv('INTERNAL_API_SECRET') ?: '';
$body = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] ?? null;
if (!internal_api_verify_signature($body, $sig, $secret)) {
    http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit;
}
$payload = json_decode($body, true) ?: [];
$result = create_order_internal($conn, $payload);   // $conn từ bootstrap/db connect hiện có
if (isset($result['error'])) { http_response_code(422); }
echo json_encode($result, JSON_UNESCAPED_UNICODE);
```

(Kiểm tra biến kết nối mysqli trong `config/bootstrap.php` — nếu tên khác `$conn`, dùng tên thật.)

- [ ] **Step 3: Run** `php tests/internal_order_api_test.php` — Expected: `OK`.
- [ ] **Step 4: Commit** — `git commit -m "feat: internal order creation api with hmac auth"`

### Task 17: Slot-filling order create (Action Agent)

**Files:**
- Create: `ai-service/app/services/__init__.py`, `ai-service/app/services/order_create_service.py`
- Modify: `ai-service/app/engines/multiagent/order_create.py` (thay stub)
- Test: `ai-service/tests/test_order_create_service.py`

**Interfaces:**
- Consumes: PHP internal API (Task 16), `catalog_repo.search_products_like`, `chat_repo.update_session`.
- Produces: draft state trong `chat_sessions.metadata["order_draft"]`: `{"step": "items|recipient|phone|address|confirm", "items": [{"banh_id", "ten_banh", "gia", "quantity"}], "recipient_name", "phone", "address"}`. `advance_draft(deps, session, message, user_id) -> tuple[str, dict | None, dict]` trả `(response_text, order_result_or_None, new_draft)`. `submit_order(settings, payload) -> dict` — POST HMAC qua httpx. CONFIRM_WORDS = {"đồng ý", "ok", "oke", "xác nhận", "chốt", "đúng rồi", "yes"}.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_order_create_service.py
import json
from app.services import order_create_service as svc

def test_sign_payload_matches_php_hmac():
    body = json.dumps({"user_id": 1}, ensure_ascii=False, separators=(",", ":"))
    sig = svc.sign_payload(body, "test-secret")
    import hmac, hashlib
    assert sig == hmac.new(b"test-secret", body.encode(), hashlib.sha256).hexdigest()

def test_parse_quantity():
    assert svc.parse_quantity("2 cái") == 2
    assert svc.parse_quantity("lấy 1 nhé") == 1
    assert svc.parse_quantity("bánh kem dâu") == 1  # default

def test_is_confirmation():
    assert svc.is_confirmation("ok chốt đơn")
    assert svc.is_confirmation("đồng ý")
    assert not svc.is_confirmation("khoan đã")

def test_flow_requires_login():
    resp, order, draft = svc.advance_draft(None, {"metadata": None}, "đặt bánh kem", user_id=None)
    assert "đăng nhập" in resp
    assert order is None
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/services/order_create_service.py
import hashlib, hmac, json, re
import httpx
from app.engines.multiagent.action import extract_phone

CONFIRM_WORDS = {"đồng ý", "dong y", "ok", "oke", "xác nhận", "xac nhan", "chốt", "chot", "đúng rồi", "yes"}
LOGIN_MSG = ("Để đặt bánh trong chat, bạn vui lòng đăng nhập tài khoản Gấu Bakery trước nhé: "
             "/cakev0/pages/login.php")

def sign_payload(body: str, secret: str) -> str:
    return hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()

def parse_quantity(text: str) -> int:
    m = re.search(r"\b(\d{1,2})\b", text)
    return max(1, min(20, int(m.group(1)))) if m else 1

def is_confirmation(text: str) -> bool:
    low = text.lower()
    return any(w in low for w in CONFIRM_WORDS)

def fmt_vnd(v) -> str:
    return f"{int(v):,}".replace(",", ".") + " VNĐ"

def _summary(draft: dict) -> str:
    lines = [f"- {i['ten_banh']} x{i['quantity']} = {fmt_vnd(i['gia'] * i['quantity'])}" for i in draft["items"]]
    total = sum(i["gia"] * i["quantity"] for i in draft["items"])
    return ("Xác nhận đơn hàng (COD):\n" + "\n".join(lines) +
            f"\nTổng: {fmt_vnd(total)}\nNgười nhận: {draft['recipient_name']}"
            f"\nSĐT: {draft['phone']}\nĐịa chỉ: {draft['address']}"
            "\n\nBạn trả lời \"đồng ý\" để chốt đơn, hoặc nhắn nội dung cần sửa.")

def submit_order(settings, payload: dict) -> dict:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
    r = httpx.post(settings.internal_order_api_url, content=body,
                   headers={"Content-Type": "application/json",
                            "X-Internal-Signature": sign_payload(body, settings.internal_api_secret)},
                   timeout=15)
    return r.json()

def advance_draft(deps, session: dict, message: str, user_id) -> tuple[str, dict | None, dict]:
    if not user_id:
        return LOGIN_MSG, None, {}
    meta = session.get("metadata")
    meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
    draft = meta.get("order_draft") or {"step": "items", "items": []}

    step = draft["step"]
    if step == "items":
        conn = deps.conn_factory()
        from app.db import catalog_repo
        found = catalog_repo.search_products_like(conn, re.sub(r"\d+", "", message).strip()) if conn else []
        if conn: conn.close()
        if not found:
            return ("Mình chưa tìm thấy bánh đó. Bạn cho mình tên bánh cụ thể hơn nhé "
                    "(ví dụ: bánh kem dâu)."), None, draft
        p = found[0]
        draft["items"] = [{"banh_id": p["id"], "ten_banh": p["ten_banh"],
                           "gia": float(p["gia"]), "quantity": parse_quantity(message)}]
        draft["step"] = "recipient"
        return (f"Bạn chọn {p['ten_banh']} x{draft['items'][0]['quantity']} "
                f"({fmt_vnd(p['gia'])}/cái). Cho mình xin tên người nhận nhé."), None, draft
    if step == "recipient":
        draft["recipient_name"] = message.strip()[:255]
        draft["step"] = "phone"
        return "Số điện thoại người nhận là gì ạ?", None, draft
    if step == "phone":
        phone = extract_phone(message)
        if not phone:
            return "Số điện thoại chưa hợp lệ, bạn nhập lại giúp mình (vd 0901234567).", None, draft
        draft["phone"] = phone
        draft["step"] = "address"
        return "Địa chỉ giao bánh ở đâu ạ?", None, draft
    if step == "address":
        draft["address"] = message.strip()
        draft["step"] = "confirm"
        return _summary(draft), None, draft
    if step == "confirm":
        if not is_confirmation(message):
            draft["step"] = "items"
            return "Ok mình làm lại nhé. Bạn muốn đặt bánh nào ạ?", None, draft
        payload = {"user_id": user_id, "recipient_name": draft["recipient_name"],
                   "phone": draft["phone"], "address": draft["address"],
                   "items": [{"banh_id": i["banh_id"], "quantity": i["quantity"]} for i in draft["items"]]}
        try:
            result = submit_order(deps.settings, payload)
        except Exception:
            return ("Hệ thống đặt hàng đang bận, mình đã lưu đơn nháp và chuyển nhân viên hỗ trợ xử lý giúp bạn."), None, draft
        if "order_id" in result:
            return (f"Đặt bánh thành công! Mã đơn #{result['order_id']}, "
                    f"tổng {fmt_vnd(result['total_amount'])}, thanh toán khi nhận hàng (COD). "
                    "Cảm ơn bạn đã ủng hộ Gấu Bakery! 🎂"), result, {}
        return f"Chưa tạo được đơn ({result.get('reason', 'lỗi')}). Bạn kiểm tra lại thông tin giúp mình nhé.", None, draft
    return "Bạn muốn đặt bánh nào ạ?", None, {"step": "items", "items": []}
```

```python
# ai-service/app/engines/multiagent/order_create.py
import json
from app.services import order_create_service as svc

def order_create_node(deps, state):
    ctx = state.get("context", {})
    session = ctx.get("session") or {}
    resp, order, new_draft = svc.advance_draft(deps, session, state["query"], ctx.get("user_id"))
    conn = deps.conn_factory()
    if conn is not None and session.get("id"):
        from app.db import chat_repo
        meta = session.get("metadata")
        meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
        meta["order_draft"] = new_draft
        chat_repo.update_session(conn, session["id"], metadata=meta)
        conn.close()
    out = {"response": resp}
    if order:
        out["action_result"] = {"type": "order_card", "order": order}
    return out
```

**Sticky draft:** khi session đang có `order_draft` với step ≠ items rỗng, router có thể phân intent sai (khách trả lời "0901234567" → không phải order_create). Fix trong `MultiAgentEngine.handle`: trước khi invoke graph, nếu session metadata có `order_draft` đang mở (`step` ≠ "items" hoặc items không rỗng) và câu không phải yêu cầu thoát ("thôi", "hủy") → set thẳng `intent="order_create"`, bỏ qua router:

```python
def _open_draft(session) -> bool:
    import json
    meta = session.get("metadata")
    meta = json.loads(meta) if isinstance(meta, str) else (meta or {})
    d = meta.get("order_draft") or {}
    return bool(d.get("items")) or d.get("step") not in (None, "items")

# trong handle(), sau khi build state:
session = context.get("session") or {}
if _open_draft(session) and not any(w in user_message.lower() for w in ("thôi", "hủy", "huy don")):
    state["intent"] = "order_create"; state["confidence"] = 1.0
    state["normalized_query"] = normalize(user_message)
    out = self._graph.invoke({**state})  # graph vẫn chạy, router node giữ intent đã set
```

Cách đơn giản nhất: trong `router_node`, nếu `state.get("intent") == "order_create"` (pre-set) thì trả nguyên `{"intent": "order_create", "confidence": 1.0}` không gọi LLM.

- [ ] **Step 3: Run unit tests** — PASS.
- [ ] **Step 4: E2E thủ công:** login web → chat "đặt bánh kem dâu 2 cái" → điền tên/SĐT/địa chỉ → "đồng ý" → check `SELECT * FROM orders ORDER BY id DESC LIMIT 1`. Expected: đơn `Tiền mặt`/`pending` đúng total.
- [ ] **Step 5: Commit** — `git commit -m "feat: in-chat COD order creation with slot filling"`

**🏁 Milestone P3:** chốt đơn COD end-to-end trong chat.

---

## Phase 4 — Admin inbox hợp nhất

### Task 18: Admin chat APIs + tab UI

**Files:**
- Create: `api/chat/sessions.php`, `api/chat/agent_reply.php`, `assets/js/admin-chat.js`, `assets/css/admin-chat.css`
- Modify: `admin/admin.php` (thêm tab `chat` vào nav + section render), `ai-service/app/api/chat.py` (thêm 2 endpoint admin)

**Interfaces:**
- Produces FastAPI: `GET /admin/sessions` → `{sessions: [{id, source, status, user_id, last_message, updated_at, open_ticket_count}]}` (JOIN chat_messages lấy last, COUNT tickets open); `POST /admin/reply` body `{session_id, admin_id, content}` → append message sender='agent', nếu session status='handoff' giữ nguyên, trả `{message_id}`. PHP proxy 2 endpoint này, **kiểm tra admin session** (`$_SESSION['admin_id']` — xác nhận key thật trong `admin/admin.php` phần login) trước khi forward.
- Produces UI: tab "Hội thoại" — bảng session (badge nguồn 💬/Ⓜ, badge handoff đỏ), click mở panel lịch sử + ô reply, polling 5s.

- [ ] **Step 1: FastAPI endpoints** (thêm vào `app/api/chat.py`)

```python
@router.get("/admin/sessions")
def admin_sessions(engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    with conn.cursor() as cur:
        cur.execute("""
            SELECT s.id, s.source, s.status, s.user_id, s.updated_at,
                   (SELECT content FROM chat_messages m WHERE m.session_id = s.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM support_tickets t WHERE t.session_id = s.id AND t.status IN ('open','in_progress')) AS open_ticket_count
            FROM chat_sessions s ORDER BY s.updated_at DESC LIMIT 100""")
        rows = [dict(r, updated_at=str(r["updated_at"])) for r in cur.fetchall()]
    conn.close()
    return {"sessions": rows}

class AdminReply(BaseModel):
    session_id: int
    admin_id: int
    content: str

@router.post("/admin/reply")
def admin_reply(req: AdminReply, engine=Depends(deps_mod.get_engine)):
    conn = engine.deps.conn_factory()
    with conn.cursor() as cur:
        cur.execute("INSERT INTO chat_messages (session_id, sender, content, admin_id) VALUES (%s, 'agent', %s, %s)",
                    (req.session_id, req.content, req.admin_id))
        mid = cur.lastrowid
    conn.close()
    return {"message_id": mid}
```

- [ ] **Step 2: PHP proxy** — `api/chat/sessions.php` và `api/chat/agent_reply.php`: cùng pattern Task 9 nhưng đầu file chặn không phải admin:

```php
if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
```

`agent_reply.php` gắn `admin_id` từ session vào payload trước khi forward (không tin client).

- [ ] **Step 3: Admin tab UI** — trong `admin/admin.php`: tìm nav tabs hiện có (pattern `?tab=orders`), thêm link `?tab=chat`; section render `<div id="admin-chat-root"></div>` + include `admin-chat.js`/`admin-chat.css` khi `$_GET['tab'] === 'chat'`.

```javascript
// assets/js/admin-chat.js
(function () {
  const API = '/cakev0/api/chat';
  const root = document.getElementById('admin-chat-root');
  root.innerHTML = `<div class="ac-layout">
      <div class="ac-list"><table><tbody id="ac-rows"></tbody></table></div>
      <div class="ac-panel">
        <div id="ac-msgs" class="ac-msgs">Chọn hội thoại</div>
        <form id="ac-form" hidden><input id="ac-input" placeholder="Trả lời khách..."><button>Gửi</button></form>
      </div></div>`;
  let current = null;

  function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  async function loadSessions() {
    const r = await fetch(API + '/sessions.php');
    const data = await r.json();
    document.getElementById('ac-rows').innerHTML = (data.sessions || []).map(s => `
      <tr data-id="${s.id}" class="${current === s.id ? 'ac-active' : ''}">
        <td>${s.source === 'messenger' ? 'Ⓜ' : '💬'} #${s.id}</td>
        <td>${esc((s.last_message || '').slice(0, 40))}</td>
        <td>${s.status === 'handoff' ? '<span class="ac-badge">HANDOFF</span>' : esc(s.status)}</td>
      </tr>`).join('');
    document.querySelectorAll('#ac-rows tr').forEach(tr =>
      tr.onclick = () => { current = +tr.dataset.id; loadHistory(); });
  }

  async function loadHistory() {
    if (!current) return;
    const r = await fetch(`${API}/history.php?session_id=${current}`);
    const data = await r.json();
    document.getElementById('ac-msgs').innerHTML = (data.messages || []).map(m =>
      `<div class="ac-m ac-${m.sender}"><b>${m.sender}</b>: ${esc(m.content)}</div>`).join('');
    document.getElementById('ac-form').hidden = false;
  }

  document.addEventListener('submit', async e => {
    if (e.target.id !== 'ac-form') return;
    e.preventDefault();
    const inp = document.getElementById('ac-input');
    if (!inp.value.trim() || !current) return;
    await fetch(API + '/agent_reply.php', { method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: current, content: inp.value.trim() }) });
    inp.value = ''; loadHistory();
  });

  loadSessions();
  setInterval(() => { loadSessions(); loadHistory(); }, 5000);
})();
```

`admin-chat.css`: `.ac-layout` grid 2 cột (320px + 1fr, height 70vh), `.ac-list` overflow-y auto, hàng hover nền xám, `.ac-active` nền cam nhạt, `.ac-badge` nền đỏ chữ trắng bo tròn, `.ac-msgs` overflow-y auto padding 12px, `.ac-m.ac-agent` viền trái cam, `#ac-form` flex input flex-1.

- [ ] **Step 4: Thống kê (spec §8)** — thêm vào `GET /admin/sessions` response khối `stats`: `{today_sessions, handoff_sessions, intent_counts}` (3 câu SQL COUNT/GROUP BY trên `chat_sessions` — `DATE(created_at)=CURDATE()`, `status='handoff'`, `GROUP BY intent_label`); render 3 số trên đầu tab chat.
- [ ] **Step 5: Verify thủ công** — 2 browser: khách chat gây handoff ("cho gặp người thật") → admin thấy badge handoff → reply → khách nhận reply qua polling widget (sender agent, viền cam).
- [ ] **Step 6: Commit** — `git commit -m "feat: unified admin chat inbox with agent reply"`

**🏁 Milestone P4:** handoff loop khép kín bot → ticket → người thật → khách.

---

## Phase 5 — Facebook Messenger

### Task 19: Webhook nhận tin + verify chữ ký

**Files:**
- Create: `ai-service/app/channels/__init__.py`, `ai-service/app/channels/messenger.py`, `ai-service/app/api/messenger.py`
- Modify: `ai-service/app/main.py` (include router)
- Test: `ai-service/tests/test_messenger.py`

**Interfaces:**
- Produces: `verify_signature(app_secret: str, body: bytes, header: str | None) -> bool` (header dạng `sha256=<hex>`); `extract_events(payload: dict) -> list[dict]` — mỗi event `{psid, text}` từ `entry[].messaging[]` (bỏ qua event không có `message.text`); `GET /channels/messenger/webhook` (echo `hub.challenge` khi `hub.verify_token` khớp, 403 khi sai); `POST /channels/messenger/webhook` — verify chữ ký → mỗi event: get_or_create_session theo `external_user_id=psid, source='messenger'` → gọi engine → gửi reply qua `send_text` (Task 20). Trả `{"status": "ok"}` ngay (xử lý tuần tự trong request — đủ cho scope).

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_messenger.py
import hashlib, hmac, json
from app.channels.messenger import verify_signature, extract_events

def test_verify_signature():
    body = b'{"a": 1}'
    sig = "sha256=" + hmac.new(b"secret", body, hashlib.sha256).hexdigest()
    assert verify_signature("secret", body, sig)
    assert not verify_signature("secret", body, "sha256=wrong")
    assert not verify_signature("secret", body, None)

def test_extract_events():
    payload = {"entry": [{"messaging": [
        {"sender": {"id": "psid-1"}, "message": {"text": "hi"}},
        {"sender": {"id": "psid-2"}, "postback": {"payload": "x"}}]}]}
    events = extract_events(payload)
    assert events == [{"psid": "psid-1", "text": "hi"}]
```

- [ ] **Step 2: Implement**

```python
# ai-service/app/channels/messenger.py
import hashlib, hmac
import httpx

GRAPH_URL = "https://graph.facebook.com/v21.0/me/messages"

def verify_signature(app_secret: str, body: bytes, header) -> bool:
    if not header or not header.startswith("sha256="):
        return False
    expected = hmac.new(app_secret.encode(), body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, header[len("sha256="):])

def extract_events(payload: dict) -> list[dict]:
    out = []
    for entry in payload.get("entry", []):
        for m in entry.get("messaging", []):
            text = (m.get("message") or {}).get("text")
            psid = (m.get("sender") or {}).get("id")
            if text and psid:
                out.append({"psid": psid, "text": text})
    return out

def send_text(page_token: str, psid: str, text: str):
    httpx.post(GRAPH_URL, params={"access_token": page_token},
               json={"recipient": {"id": psid}, "message": {"text": text[:2000]}}, timeout=10)
```

```python
# ai-service/app/api/messenger.py
from fastapi import APIRouter, Request, Response, Depends
from app.config import get_settings
from app.channels import messenger as ms
from app import deps as deps_mod
from app.db import chat_repo

router = APIRouter()

@router.get("/channels/messenger/webhook")
def verify(request: Request):
    q = request.query_params
    if q.get("hub.verify_token") == get_settings().fb_verify_token:
        return Response(q.get("hub.challenge", ""), media_type="text/plain")
    return Response(status_code=403)

@router.post("/channels/messenger/webhook")
async def receive(request: Request, engine=Depends(deps_mod.get_engine)):
    s = get_settings()
    body = await request.body()
    if not ms.verify_signature(s.fb_app_secret, body, request.headers.get("X-Hub-Signature-256")):
        return Response(status_code=403)
    for ev in ms.extract_events(await request.json()):
        conn = engine.deps.conn_factory()
        session = chat_repo.get_or_create_session(conn, source="messenger", external_user_id=ev["psid"])
        # tái dùng session cũ theo PSID nếu có
        history = [{"sender": m["sender"], "content": m["content"]}
                   for m in chat_repo.get_messages(conn, session["id"])]
        chat_repo.append_message(conn, session["id"], "customer", ev["text"])
        reply = engine.handle(history, ev["text"], {"session": session, "user_id": None})
        chat_repo.append_message(conn, session["id"], "bot", reply.content)
        conn.close()
        ms.send_text(s.fb_page_token, ev["psid"], reply.content)
    return {"status": "ok"}
```

`get_or_create_session` cần thêm nhánh tìm theo PSID trước khi tạo — sửa `chat_repo.get_or_create_session`: nếu `external_user_id` truyền vào, SELECT session `active` mới nhất có `external_user_id` đó trước, có thì trả luôn.

- [ ] **Step 3: Run** — PASS. **Step 4: Commit** — `git commit -m "feat: messenger webhook with signature verification"`

### Task 20: Messenger product template + setup docs

**Files:**
- Modify: `ai-service/app/channels/messenger.py`, `ai-service/app/api/messenger.py`
- Create: `ai-service/docs/messenger-setup.md`
- Test: bổ sung `ai-service/tests/test_messenger.py`

**Interfaces:**
- Produces: `build_generic_template(products: list[dict], base_url: str) -> dict` — Messenger generic template ≤10 elements `{title, subtitle (giá), image_url, default_action.url}`; `send_payload(page_token, psid, message: dict)`. Webhook: nếu `reply.products` không rỗng → gửi text rồi gửi template.

- [ ] **Step 1: Failing test**

```python
def test_build_generic_template():
    from app.channels.messenger import build_generic_template
    tpl = build_generic_template(
        [{"id": 1, "ten_banh": "Bánh kem dâu", "gia": 250000, "hinh_anh": "a.jpg"}],
        "https://cake-i8l0.onrender.com/cakev0")
    el = tpl["attachment"]["payload"]["elements"][0]
    assert el["title"] == "Bánh kem dâu"
    assert "250.000" in el["subtitle"]
    assert el["default_action"]["url"].startswith("https://")
```

- [ ] **Step 2: Implement**

```python
def build_generic_template(products: list[dict], base_url: str) -> dict:
    def vnd(v): return f"{int(v):,}".replace(",", ".") + " VNĐ"
    elements = [{
        "title": p["ten_banh"][:80],
        "subtitle": vnd(p["gia"]),
        "image_url": p.get("hinh_anh") if str(p.get("hinh_anh", "")).startswith("http")
                     else f"{base_url}/{str(p.get('hinh_anh', '')).lstrip('/')}",
        "default_action": {"type": "web_url", "url": f"{base_url}/pages/product.php?id={p['id']}"},
    } for p in products[:10]]
    return {"attachment": {"type": "template",
                           "payload": {"template_type": "generic", "elements": elements}}}

def send_payload(page_token: str, psid: str, message: dict):
    httpx.post(GRAPH_URL, params={"access_token": page_token},
               json={"recipient": {"id": psid}, "message": message}, timeout=10)
```

Trong webhook receive, sau `send_text`: `if reply.products: ms.send_payload(s.fb_page_token, ev["psid"], ms.build_generic_template(reply.products, s.site_base_url))`.

`docs/messenger-setup.md`: các bước — tạo FB App (Business) → thêm product Messenger → tạo Page + Page token → đặt env `FB_PAGE_TOKEN/FB_VERIFY_TOKEN/FB_APP_SECRET` → chạy `ngrok http 8000` → cấu hình Callback URL `https://<ngrok>/channels/messenger/webhook` + verify token → subscribe field `messages` → thêm tester accounts (App chưa review chỉ nhắn được với tester/admin). Ghi rõ: App review là rủi ro chấp nhận, demo bằng tester.

- [ ] **Step 3: Run + verify thật với ngrok + tester.** **Step 4: Commit** — `git commit -m "feat: messenger product cards and setup guide"`

**🏁 Milestone P5:** đủ 3 kênh: widget, admin inbox, Messenger.

---

## Phase 6 — Dataset, Eval, Deploy

### Task 21: Dataset schema + validator + synthetic generator

**Files:**
- Create: `ai-service/eval/__init__.py`, `ai-service/eval/dataset_schema.py`, `ai-service/eval/generate_synthetic.py`, `ai-service/eval/dataset/README.md`, `ai-service/eval/dataset/samples.jsonl` (bắt đầu 10 mẫu tay)
- Test: `ai-service/tests/test_dataset_schema.py`

**Interfaces:**
- Produces: mỗi dòng JSONL: `{"id": "s001", "messages": ["câu khách 1", ...], "expected_intent": "<1 trong 11 intent>", "expected_handoff": bool, "ground_truth_answer": "...", "tags": ["teencode"|"no_diacritics"|"edge_case"|"common"]}`. `validate_sample(d: dict) -> list[str]` (list lỗi, rỗng = hợp lệ); `load_dataset(path) -> list[dict]` (raise nếu có lỗi). `generate_synthetic.py`: script gọi Gemini sinh mẫu theo prompt persona khách bánh online (in JSONL ra stdout, người làm review tay rồi mới append vào samples.jsonl — không auto-append).

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_dataset_schema.py
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
```

- [ ] **Step 2: Implement**

```python
# ai-service/eval/dataset_schema.py
import json
from app.engines.multiagent.router import INTENTS

VALID_TAGS = {"teencode", "no_diacritics", "edge_case", "common"}

def validate_sample(d: dict) -> list[str]:
    errs = []
    if not d.get("id"): errs.append("missing id")
    if not d.get("messages") or not isinstance(d["messages"], list): errs.append("messages must be non-empty list")
    if d.get("expected_intent") not in INTENTS: errs.append(f"invalid intent: {d.get('expected_intent')}")
    if not isinstance(d.get("expected_handoff"), bool): errs.append("expected_handoff must be bool")
    if not d.get("ground_truth_answer"): errs.append("missing ground_truth_answer")
    if not set(d.get("tags", [])) <= VALID_TAGS: errs.append("invalid tags")
    return errs

def load_dataset(path: str) -> list[dict]:
    rows = []
    for n, line in enumerate(open(path, encoding="utf-8"), 1):
        line = line.strip()
        if not line: continue
        d = json.loads(line)
        errs = validate_sample(d)
        if errs:
            raise ValueError(f"line {n}: {errs}")
        rows.append(d)
    return rows
```

`generate_synthetic.py`: dùng `GeminiClient`, prompt: "Sinh 10 câu hỏi khách hàng tiệm bánh online tiếng Việt cho intent {intent}, {style: 'viết tắt teencode thiếu dấu' | 'bình thường'}, trả JSONL đúng schema..." — loop qua 11 intent × 2 style, in stdout.

`eval/dataset/README.md`: quy trình build 150 mẫu — 10 tay/intent chính + synthetic review tay + phân bổ theo tỷ lệ intent trong thesis plan §6.2 (catalog 30%, faq 20%, order_status 15%, recommend 15%, policy 10%, complaint 5%, chitchat 3%, handoff 2%), 70% common / 30% edge-case, annotation 2 người.

- [ ] **Step 3: Run** — PASS. Viết 10 mẫu tay đầu vào `samples.jsonl` (đủ 11 intent xuất hiện ≥1 lần, trừ order_create có thể multi-message).
- [ ] **Step 4: Commit** — `git commit -m "feat: eval dataset schema, validator, synthetic generator"`

### Task 22: Eval harness (run + metrics M1–M6)

**Files:**
- Create: `ai-service/eval/run_eval.py`, `ai-service/eval/metrics.py`, `ai-service/eval/annotate_template.py`
- Test: `ai-service/tests/test_metrics.py`

**Interfaces:**
- Produces: `run_eval.py --engine baseline|multiagent --dataset eval/dataset/samples.jsonl --out results/<engine>.jsonl --sleep 4` — mỗi sample: gửi tuần tự messages vào engine (session mới/sample), ghi per-turn: `{sample_id, turn, predicted_intent, confidence, response, citations, retrieved_docs, predicted_handoff, latency_ms}`; **checkpoint resume**: nếu out file tồn tại, skip sample_id đã có. `metrics.py compute(results_path, dataset_path) -> dict` trả: `intent_accuracy` (predicted_intent turn cuối vs expected), `grounded_rate` (tỷ lệ turn có ≥1 citation nằm trong retrieved_docs), `handoff_precision/recall/f1` (any-turn predicted vs expected_handoff), `avg_first_response_ms`, `p95_first_response_ms`, `task_completion_rate` (sample expected_intent=order_create có turn chứa "Mã đơn #" hoặc order result). M5 retention đo trên production log (SQL đếm session ≥3 customer messages — kèm câu SQL trong docstring). M1 accuracy: `annotate_template.py` xuất CSV `{sample_id, question, ground_truth, response_A, response_B, correct_A: '', correct_B: ''}` cho 2 annotator điền; `metrics.py kappa(csv1, csv2)` tính Cohen's Kappa.

- [ ] **Step 1: Failing test**

```python
# ai-service/tests/test_metrics.py
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
```

- [ ] **Step 2: Implement**

```python
# ai-service/eval/metrics.py
def grounded_turn(turn: dict) -> bool:
    docs = set(turn.get("retrieved_docs", []))
    cits = [c.get("source") for c in turn.get("citations", [])]
    return bool(cits) and all(c in docs for c in cits)

def handoff_prf(pred: dict, truth: dict) -> tuple[float, float, float]:
    tp = sum(1 for k in truth if truth[k] and pred.get(k))
    fp = sum(1 for k in truth if not truth[k] and pred.get(k))
    fn = sum(1 for k in truth if truth[k] and not pred.get(k))
    p = tp / (tp + fp) if tp + fp else 0.0
    r = tp / (tp + fn) if tp + fn else 0.0
    f1 = 2 * p * r / (p + r) if p + r else 0.0
    return p, r, f1

def cohen_kappa(a: list, b: list) -> float:
    n = len(a)
    po = sum(1 for x, y in zip(a, b) if x == y) / n
    pa = (sum(a) / n) * (sum(b) / n) + ((n - sum(a)) / n) * ((n - sum(b)) / n)
    return (po - pa) / (1 - pa) if pa != 1 else 1.0
```

(`compute()` tổng hợp — đọc JSONL results + dataset, group theo sample_id, áp các hàm trên; ~60 dòng thuần Python, không phụ thuộc pandas.)

`run_eval.py` khung chính:

```python
import argparse, json, os, time
from app.config import get_settings
from app.deps import build_deps
from app.engines.baseline import BaselineEngine
from app.engines.multiagent.graph import MultiAgentEngine
from eval.dataset_schema import load_dataset

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--engine", choices=["baseline", "multiagent"], required=True)
    ap.add_argument("--dataset", default="eval/dataset/samples.jsonl")
    ap.add_argument("--out", required=True)
    ap.add_argument("--sleep", type=float, default=4.0)   # throttle free tier
    args = ap.parse_args()

    d = build_deps()
    engine = BaselineEngine(d) if args.engine == "baseline" else MultiAgentEngine(d)
    done = set()
    if os.path.exists(args.out):
        done = {json.loads(l)["sample_id"] for l in open(args.out, encoding="utf-8") if l.strip()}
    with open(args.out, "a", encoding="utf-8") as f:
        for sample in load_dataset(args.dataset):
            if sample["id"] in done:
                continue
            history = []
            for turn_i, msg in enumerate(sample["messages"]):
                t0 = time.perf_counter()
                reply = engine.handle(history, msg, {"session": {"id": 0}, "user_id": None})
                latency = int((time.perf_counter() - t0) * 1000)
                f.write(json.dumps({
                    "sample_id": sample["id"], "turn": turn_i,
                    "predicted_intent": reply.intent, "confidence": reply.confidence,
                    "response": reply.content,
                    "citations": reply.citations,
                    "retrieved_docs": getattr(reply, "retrieved_docs", []) or
                                      [c["source"] for c in reply.citations],
                    "predicted_handoff": reply.handoff, "latency_ms": latency,
                }, ensure_ascii=False) + "\n")
                f.flush()
                history += [{"sender": "customer", "content": msg},
                            {"sender": "bot", "content": reply.content}]
                time.sleep(args.sleep)

if __name__ == "__main__":
    main()
```

Chú ý: để `retrieved_docs` chuẩn cho M2, thêm field `retrieved_docs: list[str] = []` vào `EngineReply` (Task 6 model) và điền từ cả 2 engine (baseline: ids của `_retrieve`; multiagent: state `retrieved_docs`).

- [ ] **Step 3: Run** unit metrics — PASS. Chạy thử `run_eval.py --engine baseline` với 10 mẫu, kiểm tra out file + resume.
- [ ] **Step 4: Commit** — `git commit -m "feat: eval harness with throttled runs, checkpoints, metrics M1-M6"`

### Task 23: Chạy thực nghiệm + phân tích

**Files:**
- Create: `ai-service/eval/analyze.py` (đọc 2 results + dataset → bảng so sánh + Wilcoxon), `ai-service/eval/results/` (gitignore results thô? — KHÔNG, commit results để tái lập)

**Interfaces:**
- Consumes: Task 21 dataset đầy đủ 150 mẫu (build xong trước task này), Task 22 harness.
- Produces: `results/baseline.jsonl`, `results/multiagent.jsonl`, `results/comparison.md` (bảng M1–M6 hai hệ + delta + p-value).

- [ ] **Step 1:** Hoàn thiện dataset 150 mẫu theo `eval/dataset/README.md` (manual + synthetic đã review). Run validator: `venv\Scripts\python -c "from eval.dataset_schema import load_dataset; print(len(load_dataset('eval/dataset/samples.jsonl')))"` → `150`.
- [ ] **Step 2:** `python -m eval.run_eval --engine baseline --out eval/results/baseline.jsonl` rồi `--engine multiagent --out eval/results/multiagent.jsonl` (mỗi lần chạy nhiều giờ do throttle — dùng checkpoint nếu đứt). Ablation B′ (spec §9): set `ENABLE_NORMALIZER=0` trong env rồi chạy thêm `--engine multiagent --out eval/results/multiagent_no_norm.jsonl`.
- [ ] **Step 3:** `analyze.py`: compute metrics 2 hệ; latency per-sample paired → Wilcoxon signed-rank (tự cài scipy: thêm `scipy` vào requirements); xuất `comparison.md`. Annotator điền CSV M1 (2 người) → `metrics.kappa` ≥ 0.8 mới chấp nhận.
- [ ] **Step 4:** Commit — `git commit -m "feat: experiment results and comparative analysis"`

### Task 24: Deploy + docs

**Files:**
- Create: `ai-service/Dockerfile`, `ai-service/README.md`
- Modify: `docker-compose.yml` (service `ai-service`), `render.yaml` (nếu deploy Render: thêm service Python)

**Interfaces:**
- Produces: `docker compose up` chạy PHP + MySQL + AI service; env truyền đủ (GEMINI_API_KEY, INTERNAL_API_SECRET chung 2 service, AI_SERVICE_URL cho PHP).

- [ ] **Step 1: Dockerfile**

```dockerfile
FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app ./app
COPY data ./data
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

- [ ] **Step 2:** docker-compose thêm:

```yaml
  ai-service:
    build: ./ai-service
    ports: ["8000:8000"]
    environment:
      - GEMINI_API_KEY=${GEMINI_API_KEY}
      - MYSQL_HOST=db
      - INTERNAL_API_SECRET=${INTERNAL_API_SECRET}
      - INTERNAL_ORDER_API_URL=http://web/cakev0/api/internal/orders/create.php
      - ENGINE=multiagent
    depends_on: [db]
```

(Đối chiếu tên service PHP/MySQL trong docker-compose.yml hiện có — dùng tên thật thay `web`/`db`.)

- [ ] **Step 3: Rate limit (spec §12)** — middleware in-memory trong `app/main.py`: dict `{key: [timestamps]}`, key = session_id hoặc IP, giới hạn 20 req/phút cho `/chat/send` → vượt trả 429 `{"error": "rate_limited"}`. Test đơn giản trong `tests/test_health.py`: gọi 21 lần liên tiếp với cùng guest, lần 21 → 429 (dùng dependency override engine fake).
- [ ] **Step 4:** `docker compose up --build` → smoke: widget chat hoạt động qua stack container. README: setup, index knowledge, chạy eval, biến env.
- [ ] **Step 5: Commit** — `git commit -m "feat: dockerize ai-service and wire into compose stack"`

**🏁 Milestone P6:** hệ thống deploy được + kết quả thực nghiệm cho báo cáo.

---

## Ghi chú thực thi

- **Xác minh trước khi tin:** các điểm đánh dấu "kiểm tra `DESCRIBE ...`" hoặc "xác nhận key session" là chỗ plan dựa trên giả định về code hiện có — worker PHẢI xác minh bằng code thật trước khi implement, sửa interface tương ứng nếu lệch (cập nhật cả test).
- **Gemini quota:** dev dùng FakeLLM là chính; smoke thật hạn chế. Eval throttle `--sleep 4` (~15 req/phút < 1500/ngày cần chia 2 ngày nếu vượt — checkpoint hỗ trợ).
- **Thứ tự bắt buộc:** Task 12 trước 13–15 (stub → full); Task 16 trước 17; Task 21–22 trước 23.
- Sau mỗi phase milestone: chạy full pytest + php tests, smoke thủ công trước khi sang phase mới.
