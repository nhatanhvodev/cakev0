# Kiến trúc hệ thống — Sơ đồ kỹ thuật

> Tài liệu phục vụ chương Thiết kế hệ thống trong báo cáo khóa luận.
> Render Mermaid bằng GitHub, VS Code plugin, hoặc mermaid.live.

---

## 1. Tổng quan kiến trúc hệ thống

```mermaid
graph TB
    subgraph Channels["Kênh giao tiếp"]
        W[Widget Web<br/>HTML/CSS/JS]
        M[Facebook Messenger<br/>Webhook]
    end

    subgraph PHP["PHP 8.2 Backend"]
        Proxy["/api/chat/* Proxy"]
        DB_PHP[(MySQL 8.0<br/>users · orders · products)]
    end

    subgraph AI["AI Agent Service — Python 3.12 + FastAPI"]
        API["POST /chat/message<br/>GET /chat/history"]
        DI{{"Dependency Injection<br/>ENGINE flag"}}

        subgraph Engines["Engine Selection"]
            BL["BaselineEngine<br/>RAG đơn giản"]
            MA["MultiAgentEngine<br/>LangGraph StateGraph"]
            DE["DemoEngine<br/>Fallback an toàn"]
        end

        subgraph Knowledge["Knowledge Layer"]
            VS["VectorStore<br/>ChromaDB + BM25"]
            EMB["Embedding<br/>text-embedding-004"]
            NLP["Vietnamese Normalizer<br/>teencode · diacritics"]
        end

        LLM["LLMClient<br/>Gemini 2.0 Flash"]
        TG["Telegram Notify<br/>Handoff alerts"]
    end

    W -->|HTTP polling| Proxy
    M -->|Webhook| API
    Proxy -->|REST| API
    API --> DI
    DI -->|baseline| BL
    DI -->|multiagent| MA
    DI -->|demo| DE
    DE -.->|wraps| MA
    MA --> VS
    MA --> LLM
    BL --> VS
    BL --> LLM
    VS --> EMB
    MA --> NLP
    MA --> TG
    MA -.->|order lookup · order create| DB_PHP
```

---

## 2. LangGraph Multi-Agent State Machine

Sơ đồ phản ánh `app/engines/multiagent/graph.py` — `build_graph()`.

```mermaid
stateDiagram-v2
    [*] --> normalize: entry point

    normalize --> router: normalized_query

    state router_decision <<choice>>
    router --> router_decision

    router_decision --> retrieval: faq · catalog_search · product_recommend<br/>policy_shipping · policy_payment · policy_return
    router_decision --> action: order_status · order_create
    router_decision --> chitchat: chitchat
    router_decision --> handoff: complaint · handoff_request

    state retrieval_check <<choice>>
    retrieval --> retrieval_check

    retrieval_check --> rewrite: needs_retry AND<br/>retry_count < 2
    retrieval_check --> aggregate: confidence ≥ 0.5<br/>OR retries exhausted

    rewrite --> retrieval: rewritten query

    action --> aggregate
    chitchat --> aggregate
    handoff --> aggregate

    aggregate --> [*]: EngineReply

    note right of normalize
        enable_normalizer=False
        → ablation mode (skip)
    end note

    note right of retrieval
        Hybrid search:
        dense (ChromaDB) + BM25
        → RRF fusion
    end note

    note left of handoff
        Multi-factor policy:
        intent + confidence +
        keyword + retry_count
        → ticket + Telegram alert
    end note
```

---

## 3. Router Agent — Phân luồng intent

Sơ đồ phản ánh `app/engines/multiagent/router.py` — `classify_intent()`.

```mermaid
flowchart TD
    Q["User query"] --> KW{"Keyword fallback<br/>confidence ≥ 0.55?"}

    KW -->|Yes| DONE["Return (intent, confidence)<br/>Skip LLM call"]
    KW -->|No| HIST["Format history<br/>last 6 messages"]

    HIST --> LLM["Gemini 2.0 Flash<br/>ROUTER_SYSTEM prompt"]
    LLM --> PARSE{"Parse JSON<br/>from response"}

    PARSE -->|Valid JSON<br/>intent ∈ INTENTS| RESULT["Return (intent, confidence)"]
    PARSE -->|Invalid JSON<br/>or unknown intent| FALLBACK["Keyword fallback<br/>default: faq @ 0.4"]

    subgraph Keywords["9 keyword groups (ưu tiên cao → thấp)"]
        direction LR
        K1["handoff_request<br/>người thật · nhân viên"]
        K2["complaint<br/>khiếu nại · bực · hỏng"]
        K3["order_status<br/>đơn · mã đơn"]
        K4["catalog_search<br/>có bánh · menu"]
        K5["chitchat<br/>chào · cảm ơn"]
    end
```

---

## 4. Hybrid Retrieval Pipeline (Phase 2)

Sơ đồ phản ánh `app/knowledge/vector_store.py` — `VectorStore.query()`.

```mermaid
flowchart LR
    Q["User query<br/>(normalized)"] --> DENSE["Dense Retrieval<br/>ChromaDB<br/>text-embedding-004"]
    Q --> SPARSE["Sparse Retrieval<br/>BM25Okapi<br/>regex tokenizer"]

    DENSE --> VR["Vector Results<br/>top-k=5<br/>by cosine distance"]
    SPARSE --> BR["BM25 Results<br/>top-k=5<br/>by BM25 score"]

    VR --> RRF["Reciprocal Rank Fusion<br/>k=60"]
    BR --> RRF

    RRF --> FINAL["Fused Results<br/>top-k=5<br/>re-ranked by RRF score"]

    subgraph RRF_Formula["RRF Score Formula"]
        F["score(d) = Σ 1/(k + rank_i + 1)<br/>k = 60 (smoothing constant)"]
    end

    subgraph Collections["ChromaDB Collections"]
        direction TB
        C1["products — ~50 SKU"]
        C2["policies — shipping · payment · return"]
        C3["faq — 15-20 entries"]
    end
```

---

## 5. Handoff Escalation Flow

Sơ đồ phản ánh `app/engines/multiagent/handoff.py` + `app/services/notify.py`.

```mermaid
flowchart TD
    STATE["AgentState"] --> DECIDE{"decide_handoff()<br/>Multi-factor policy"}

    DECIDE --> F1["Factor 1: Intent<br/>complaint · handoff_request"]
    DECIDE --> F2["Factor 2: Confidence<br/>< threshold"]
    DECIDE --> F3["Factor 3: Keywords<br/>khiếu nại · hoàn tiền gấp"]
    DECIDE --> F4["Factor 4: Retries<br/>retry_count ≥ 2"]

    F1 & F2 & F3 & F4 --> REASONS["Collect reasons[]"]
    REASONS --> SHOULD{"Any reasons?"}

    SHOULD -->|Yes| DRAFT["LLM: generate<br/>draft response<br/>(skip if handoff_request)"]
    SHOULD -->|No| NORMAL["Normal flow"]

    DRAFT --> TICKET["MySQL: create ticket<br/>subject · priority · draft"]
    TICKET --> NOTIFY["Telegram Bot API<br/>async notification<br/>to support team"]
    NOTIFY --> REPLY["Response:<br/>'Đã chuyển cho nhân viên...'<br/>+ hotline 0901 234 567"]
```

---

## 6. So sánh kiến trúc: Baseline vs Multi-Agent

```mermaid
graph LR
    subgraph A["System A — Baseline RAG"]
        A1["Input"] --> A2["Normalizer"]
        A2 --> A3["Embed + ChromaDB"]
        A3 --> A4["1 LLM call<br/>(all instructions)"]
        A4 --> A5["Output"]
    end

    subgraph B["System B — Multi-Agent LangGraph"]
        B1["Input"] --> B2["Normalizer"]
        B2 --> B3["Router Agent<br/>(keyword-first)"]
        B3 --> B4["Specialized Agent<br/>retrieval · action<br/>chitchat · handoff"]
        B4 --> B5["Retry?"]
        B5 -->|Yes| B6["Rewrite query"]
        B6 --> B4
        B5 -->|No| B7["Aggregate"]
        B7 --> B8["Output"]
    end
```

| Tiêu chí | System A | System B |
|----------|----------|----------|
| LLM calls/turn | 1 | 2-3 |
| Routing | Không | Keyword-first + LLM fallback |
| Retrieval | Single-stage, all sources | Per-collection + BM25 hybrid |
| Retry | Không | Query rewrite, max 2 |
| Handoff | confidence < 0.6 | Multi-factor (4 signals) |
| Action | Text only | Order lookup + COD creation |
| Citation | Dễ lẫn nguồn | Per-collection, chính xác |

---

## 7. Data Flow — Một request hoàn chỉnh

```mermaid
sequenceDiagram
    participant U as Khách hàng
    participant W as Widget
    participant P as PHP Proxy
    participant F as FastAPI
    participant N as Normalizer
    participant R as Router
    participant S as VectorStore
    participant L as Gemini LLM
    participant DB as MySQL
    participant TG as Telegram

    U->>W: "có bánh kem dâu không"
    W->>P: POST /api/chat/send
    P->>F: POST /chat/message
    F->>F: Load history from DB

    F->>N: normalize("có bánh kem dâu không")
    N-->>F: "có bánh kem dâu không"

    F->>R: classify_intent()
    R->>R: keyword_fallback → "catalog_search" @ 0.55
    R-->>F: (catalog_search, 0.55)

    F->>S: query("products", query, top_k=5)
    S->>S: Dense: ChromaDB search
    S->>S: Sparse: BM25 search
    S->>S: RRF fusion
    S-->>F: [RetrievedDoc × 5]

    F->>L: RETRIEVAL_SYSTEM + docs + query
    L-->>F: {"answer": "...", "confidence": 0.85, "sources": [...]}

    F-->>P: EngineReply(content, citations, products)
    P-->>W: JSON response
    W-->>U: "Dạ shop có Bánh kem dâu..."
```
