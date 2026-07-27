# Kiến trúc hệ thống - Sơ đồ kỹ thuật

> Tài liệu phục vụ chương Thiết kế hệ thống trong báo cáo khóa luận.
> Đã đồng bộ với codebase hiện tại: `ai-service/app/*`, `api/chat/*`, `includes/chat_proxy_helpers.php`, `database/migrations/*`.

---

## 1. Tổng quan kiến trúc hệ thống

```mermaid
graph TB
    subgraph Channels["Kênh giao tiếp"]
        W["Widget Web<br/>assets/js/gau-chat-widget.js"]
        AUI["Admin Chat<br/>assets/js/admin-chat.js"]
        M["Facebook Messenger<br/>/channels/messenger/webhook"]
    end

    subgraph PHP["PHP Backend"]
        Proxy["/api/chat/* Proxy<br/>send · history · sessions<br/>session_action · agent_reply"]
        InternalOrder["Internal Order API<br/>/api/internal/orders/create.php<br/>HMAC signature"]
        DB[(MySQL<br/>users · banh · orders<br/>chat_sessions · tickets)]
    end

    subgraph AI["AI Agent Service - Python 3.12 + FastAPI"]
        API["Chat/Admin API<br/>POST /chat/send<br/>GET /chat/history<br/>POST /chat/handoff<br/>GET /admin/sessions<br/>POST /admin/session-action<br/>POST /admin/reply<br/>POST /knowledge/index"]
        Health["GET /health<br/>rate limit 20 req/min/key"]
        DI{{"Engine selector<br/>ENGINE=baseline|multiagent|demo"}}

        subgraph Engines["Engines"]
            BL["BaselineEngine<br/>single RAG pipeline"]
            MA["MultiAgentEngine<br/>LangGraph StateGraph"]
            DE["DemoEngine<br/>wraps MultiAgent + canned fallback"]
        end

        subgraph Agents["Multi-agent nodes"]
            N["Normalizer<br/>teencode dictionary"]
            R["Router<br/>20 intents"]
            RET["Retrieval<br/>FAQ · policy · products"]
            ACT["Action<br/>order · coupon · review<br/>compare · favorite · dietary<br/>custom quote"]
            HO["Handoff<br/>complaint · human request"]
            AG["Aggregate<br/>EngineReply"]
        end

        subgraph Knowledge["Knowledge layer"]
            VS["ChromaDB PersistentClient"]
            BM["BM25Okapi"]
            RRF["Reciprocal Rank Fusion"]
            EMB["Gemini embedding<br/>gemini-embedding-001"]
        end

        LLM["LLMClient<br/>DeepSeek in Render<br/>Gemini supported by config"]
        TG["Telegram notify<br/>optional handoff alert"]
    end

    W --> Proxy
    AUI --> Proxy
    M --> API
    Proxy --> API
    API --> DI
    DI --> BL
    DI --> MA
    DI --> DE
    DE -.-> MA
    MA --> N --> R
    R --> RET
    R --> ACT
    R --> HO
    RET --> VS
    RET --> BM
    VS --> EMB
    BM --> RRF
    RET --> LLM
    R --> LLM
    HO --> LLM
    HO --> TG
    ACT --> DB
    ACT --> InternalOrder
    InternalOrder --> DB
    Proxy --> DB
```

---

## 2. LangGraph Multi-Agent State Machine

Sơ đồ phản ánh `ai-service/app/engines/multiagent/graph.py`.

```mermaid
stateDiagram-v2
    [*] --> normalize
    normalize --> router

    state route <<choice>>
    router --> route

    route --> retrieval: faq · catalog_search · product_recommend<br/>policy_shipping · policy_payment · policy_return
    route --> action: order_status · order_create · promotion · bestseller<br/>coupon_inquiry · review_lookup · product_compare<br/>favorite_add · favorite_view · dietary_inquiry<br/>custom_cake_quote
    route --> chitchat: chitchat
    route --> handoff: complaint · handoff_request

    state retrieval_check <<choice>>
    retrieval --> retrieval_check
    retrieval_check --> rewrite: needs_retry AND retry_count < 2
    retrieval_check --> aggregate: confident enough OR retries exhausted
    rewrite --> retrieval

    action --> aggregate
    chitchat --> aggregate
    handoff --> aggregate
    aggregate --> [*]: EngineReply

    note right of router
        Multi-turn drafts pin intent:
        order_create, custom_cake_quote
    end note

    note left of retrieval
        products/policies/faq
        ChromaDB + BM25 + RRF
    end note
```

---

## 3. Router Agent - 20 intent

Sơ đồ phản ánh `ai-service/app/engines/multiagent/router.py`.

```mermaid
flowchart TD
    Q["User query"] --> KW{"keyword_fallback() match?"}
    KW -->|yes, conf=0.55| DONE["Return intent + confidence<br/>skip LLM router"]
    KW -->|no, conf=0.40| HIST["Format last 6 history messages"]
    HIST --> LLM["LLMClient.generate()<br/>ROUTER_SYSTEM prompt"]
    LLM --> PARSE{"Parse JSON"}
    PARSE -->|intent in INTENTS| RESULT["Return LLM intent + confidence"]
    PARSE -->|invalid| FALLBACK["Return keyword fallback<br/>default faq @ 0.40"]

    subgraph IntentGroups["Intent groups in current code"]
        G1["Retrieval<br/>faq, catalog_search, product_recommend,<br/>policy_shipping, policy_payment, policy_return"]
        G2["Action<br/>promotion, bestseller, order_status, order_create,<br/>coupon_inquiry, review_lookup, product_compare,<br/>favorite_add, favorite_view, dietary_inquiry,<br/>custom_cake_quote"]
        G3["Human support<br/>complaint, handoff_request"]
        G4["General<br/>chitchat"]
    end
```

---

## 4. Hybrid Retrieval Pipeline

Sơ đồ phản ánh `ai-service/app/knowledge/vector_store.py` và `ai-service/app/engines/multiagent/retrieval.py`.

```mermaid
flowchart LR
    Q["normalized_query"] --> DENSE["Dense retrieval<br/>ChromaDB collection.query()"]
    Q --> SPARSE["Sparse retrieval<br/>BM25Okapi"]

    DENSE --> VR["Vector docs<br/>top_k=5"]
    SPARSE --> BR["BM25 docs<br/>top_k=5"]
    VR --> FUSE["RRF fuse<br/>score += 1/(60 + rank + 1)"]
    BR --> FUSE
    FUSE --> DOCS["RetrievedDoc[]"]

    DOCS --> PROMPT["RETRIEVAL_SYSTEM<br/>docs + history + customer query"]
    PROMPT --> LLM["LLMClient"]
    LLM --> JSON["JSON answer<br/>confidence · sources"]
    JSON --> CITS["Keep citations only if<br/>source exists in retrieved docs"]
    DOCS --> PRODUCTS["Product cards<br/>when collection=products"]

    subgraph Collections["Collections"]
        C1["products<br/>from MySQL banh"]
        C2["policies<br/>shipping/payment/return docs"]
        C3["faq<br/>FAQ seed / faq_entries"]
    end
```

---

## 5. Action Agent

Sơ đồ phản ánh `ai-service/app/engines/multiagent/action.py`, `features.py`, `order_create.py`, `custom_quote.py`.

```mermaid
flowchart TD
    INTENT["Router intent"] --> CASE{"Action type"}

    CASE -->|order_status| OS["orders_repo.lookup_orders()<br/>by user_id / phone / order_id"]
    CASE -->|order_create| OC["order_create_service.advance_draft()<br/>slot filling: product -> recipient -> phone -> address -> confirm"]
    OC --> PHPAPI["PHP internal order API<br/>create_order_internal()<br/>stock lock + order_items"]

    CASE -->|promotion| PR["Query active promotions"]
    CASE -->|bestseller| BS["Aggregate order_items"]
    CASE -->|coupon_inquiry| CO["Query public cart_coupons"]
    CASE -->|review_lookup| RV["product_reviews summary"]
    CASE -->|product_compare| CP["semantic product resolve + compare"]
    CASE -->|favorite_add/view| FV["favorites table<br/>requires logged-in user"]
    CASE -->|dietary_inquiry| DIET["filter banh by co_trung/co_sua/co_gluten/co_hat"]
    CASE -->|custom_cake_quote| CQ["custom quote draft<br/>lead in contact_requests"]

    OS --> OUT["EngineReply"]
    PHPAPI --> OUT
    PR --> OUT
    BS --> OUT
    CO --> OUT
    RV --> OUT
    CP --> OUT
    FV --> OUT
    DIET --> OUT
    CQ --> OUT
```

---

## 6. Handoff Escalation Flow

Sơ đồ phản ánh `ai-service/app/engines/multiagent/handoff.py`, `ticket_repo.py`, `notify.py` và admin endpoints trong `api/chat.py`.

```mermaid
flowchart TD
    Q["Customer message"] --> ROUTER["Router"]
    ROUTER -->|complaint or handoff_request| HANDOFF["handoff_node()"]
    ROUTER -->|retrieval max retries| MARK["Set should_handoff=true<br/>session status open/handoff"]

    HANDOFF --> DECIDE["decide_handoff()<br/>intent + confidence + keyword + retry_count"]
    DECIDE --> DRAFT{"handoff_request?"}
    DRAFT -->|no| LLM["Generate draft response<br/>for staff reference"]
    DRAFT -->|yes| SKIP["Skip draft generation"]
    LLM --> TICKET["support_tickets<br/>subject · priority · draft_response"]
    SKIP --> TICKET
    TICKET --> TG["Telegram notify<br/>if token/chat_id configured"]
    TICKET --> REPLY["Bot tells customer<br/>staff will support soon"]

    MARK --> ADMIN["Admin chat queue"]
    REPLY --> ADMIN
    ADMIN --> CLAIM["POST /admin/session-action<br/>claim · close · reopen"]
    ADMIN --> AGENT["POST /admin/reply"]
```

---

## 7. Baseline vs Multi-Agent

| Tiêu chí | BaselineEngine | MultiAgentEngine |
|---|---|---|
| Routing | Không phân loại intent | `keyword_fallback()` + LLM router |
| Retrieval | Search cả `faq`, `policies`, `products` | Search collection theo intent |
| Action | Không tạo/truy vấn đơn trực tiếp | Có action node cho đơn hàng, coupon, review, favorite, dietary, custom quote |
| Retry | Không có rewrite cycle | Retrieval có rewrite tối đa 2 lần |
| Handoff | Confidence thấp | Complaint/handoff intent + low confidence/retry signal |
| Citation | Có, nếu LLM trả source hợp lệ | Có, theo collection phù hợp |
| Demo fallback | Không | `DemoEngine` bắt lỗi và trả canned response theo keyword |

---

## 8. Data Flow - Một lượt chat tìm sản phẩm

```mermaid
sequenceDiagram
    participant U as Khách hàng
    participant W as Widget
    participant P as PHP Proxy
    participant F as FastAPI
    participant R as Router
    participant S as VectorStore
    participant L as LLMClient
    participant DB as MySQL

    U->>W: "có bánh kem dâu không"
    W->>P: POST /cakev0/api/chat/send
    P->>F: POST /chat/send
    F->>DB: get_or_create_session + load history
    F->>R: classify_intent(normalized_query, history)
    R-->>F: catalog_search
    F->>S: query(products, normalized_query, top_k=5)
    S-->>F: RetrievedDoc[]
    F->>L: RETRIEVAL_SYSTEM + docs + history + query
    L-->>F: answer + confidence + sources
    F->>DB: append bot message + intent metadata
    F-->>P: session_id + reply + handoff
    P-->>W: JSON
    W-->>U: Bot response + product cards
```

---

## 9. Data Flow - Tạo đơn COD qua chat

```mermaid
sequenceDiagram
    participant U as Khách hàng đã đăng nhập
    participant W as Widget
    participant F as FastAPI
    participant AI as order_create_service
    participant PHP as PHP Internal API
    participant DB as MySQL

    U->>W: "đặt 2 bánh Croissant"
    W->>F: POST /chat/send via PHP proxy
    F->>AI: advance_draft(step=items)
    AI->>DB: search_products_like()
    AI-->>F: ask recipient name

    U->>W: "Nguyễn Văn A"
    F->>AI: step=recipient
    AI-->>F: ask phone

    U->>W: "0901234567"
    F->>AI: step=phone
    AI-->>F: ask address

    U->>W: "123 Trần Hưng Đạo"
    F->>AI: step=address
    AI-->>F: summary + ask confirm

    U->>W: "đồng ý"
    F->>AI: step=confirm
    AI->>PHP: POST /api/internal/orders/create.php<br/>HMAC signature
    PHP->>DB: transaction: orders + order_items + stock decrement
    DB-->>PHP: order_id
    PHP-->>AI: order_id + total_amount
    AI-->>F: order_card
    F-->>W: Đặt bánh thành công
```

---

## 10. Data Flow - Gửi email và hóa đơn qua Resend

```mermaid
sequenceDiagram
    participant Biz as Nghiệp vụ hệ thống
    participant PDF as Invoice PDF
    participant Mail as Mailer
    participant Resend as Resend API
    participant DB as MySQL
    participant U as Khách hàng

    Biz->>Mail: send_custom_mail()
    alt Gửi hóa đơn đơn hàng
        Biz->>PDF: render_invoice_pdf(order, items)
        PDF-->>Biz: PDF bytes
        Biz->>Mail: send_custom_mail_with_attachments()
    end
    Mail->>Mail: mail_driver() = resend
    Mail->>Mail: validate RESEND_API_KEY + MAIL_FROM_ADDRESS
    Mail->>Resend: POST https://api.resend.com/emails
    Resend-->>Mail: HTTP 2xx hoặc error
    alt Thành công và là email hóa đơn
        Mail->>DB: UPDATE orders.invoice_email_sent_at
        Mail-->>U: Email HTML + PDF attachment
    else Thất bại
        Mail->>Mail: error_log + diagnose_mail
    end
```

Ghi chú:

- Mail driver hiện hỗ trợ `smtp`, `gmail_api`, `resend`.
- Resend yêu cầu `MAIL_DRIVER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`.
- Các nghiệp vụ dùng mail chung gồm xác thực đăng ký, phản hồi liên hệ, thông báo yêu cầu mật khẩu và hóa đơn PDF.
