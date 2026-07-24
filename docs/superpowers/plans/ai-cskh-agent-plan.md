# Kế hoạch triển khai: Trợ lý CSKH & Chốt đơn Đa kênh cho Gấu Bakery

> **Ngày lập:** 2026-07-06 | **Trạng thái:** Draft | **Version:** 0.1.0
> **Website:** https://cake-i8l0.onrender.com/cakev0/

---

## 1. Bối cảnh & Mục tiêu

### 1.1. Bài toán

Gấu Bakery là tiệm bánh online vừa và nhỏ (SME). Hiện tại CSKH thực hiện thủ công qua hotline và form contact. Khi lượng đơn tăng, nhân viên không phản hồi kịp thời, mất khách.

### 1.2. Mục tiêu

Xây dựng AI Agent CSKH đa kênh:

| # | Năng lực | Đầu vào | Đầu ra |
|---|----------|---------|--------|
| 1 | Trả lời FAQ | Câu hỏi + knowledge base | Câu trả lời có trích nguồn |
| 2 | Tra cứu catalog | Từ khóa, loại bánh, giá | Danh sách SKU phù hợp |
| 3 | Gợi ý sản phẩm | Ngữ cảnh hội thoại | Đề xuất 3-5 sản phẩm |
| 4 | Giải thích chính sách | Truy vấn đổi trả/giao hàng | Trích dẫn chính sách |
| 5 | Kiểm tra đơn hàng | Mã ĐT + SĐT | Trạng thái, timeline |
| 6 | Handoff cho người thật | Confidence thấp | Ticket + tóm tắt |
| 7 | Chốt đơn trong chat | SKU + số lượng | Draft đơn / redirect |

### 1.3. Kênh tương tác

- Website widget chat (góc phải dưới màn hình)
- Admin dashboard (người thật tiếp nhận handoff)
- *(Future)* Facebook Messenger, Zalo OA
## 2. Hiện trạng hệ thống

### 2.1. Tổng quan codebase

| Thành phần | Mô tả |
|------------|-------|
| **Ngôn ngữ** | PHP 8.2 thuần (không framework) |
| **Database** | MySQL 8.0, database `banh_store` |
| **Web Server** | Apache + mod_rewrite |
| **Frontend** | HTML/CSS/JS thuần, responsive |
| **Thanh toán** | VNPAY Sandbox + COD (Tiền mặt) |
| **Email** | PHPMailer + Gmail API OAuth |
| **Upload** | UploadThing PHP SDK |
| **Deploy** | Docker + Render |

### 2.2. Danh sách bảng hiện có (17 bảng)

```
admins, banh, cart, cart_coupons, contact_requests, favorites,
login_logs, login_tokens, order_items, orders, password_reset_requests,
pending_registrations, product_images, product_reviews, promotions,
reviews, users
```

### 2.3. Cấu trúc thư mục

```text
cakev0/
├── admin/admin.php          # Trang quản trị (199KB)
├── assets/                  # CSS, JS, ảnh
├── config/                  # Bootstrap, config, DB connect, coupons, uploadthing
├── database/                # SQL dump, migrations
├── includes/                # Header, footer, mailer, invoice, helpers
├── pages/                   # 21 trang khách hàng
├── tests/                   # Unit tests
├── vnpay/                   # VNPAY integration
├── vendor/                  # Composer packages
├── index.php                # Trang chủ
├── docker-compose.yml
├── Dockerfile
└── render.yaml
```

### 2.4. Trang chính sách (dữ liệu cho RAG index)

| Trang | URL | Nội dung |
|-------|-----|----------|
| Chính sách vận chuyển | `/pages/shipping.php` | Phí ship, thời gian, khu vực |
| Chính sách thanh toán | `/pages/payment-policy.php` | VNPAY, COD, chuyển khoản |
| Chính sách đổi trả | `/pages/exchanges-policy.php` | Điều kiện đổi, hoàn tiền |
| Chính sách bảo mật | `/pages/privacy.php` | Bảo vệ dữ liệu |


## 3. Kiến trúc tổng thể

### 3.1. Sơ đồ kiến trúc

```
┌──────────────────────────────────────────────────────────┐
│                Gấu Bakery Website (PHP)                   │
│  ┌──────────┐  ┌──────────┐  ┌───────────────────────┐  │
│  │ Frontend │  │  Admin   │  │  API Proxy (NEW)      │  │
│  │ +Chat    │  │  +Chat   │  │  /api/chat/*          │  │
│  │  Widget  │  │  Panel   │  │  /api/knowledge/*     │  │
│  └────┬─────┘  └────┬─────┘  └───────────┬───────────┘  │
└───────┼─────────────┼────────────────────┼──────────────┘
        │             │                    │
        │  WebSocket  │  REST              │ REST
        ▼             ▼                    ▼
┌──────────────────────────────────────────────────────────┐
│           AI Agent Service (Python + FastAPI)             │
│                      Port 8000                            │
│  ┌──────────────────────────────────────────────────┐    │
│  │  API Layer: POST /chat/send, /chat/stream        │    │
│  │  GET /catalog/search, POST /orders/lookup        │    │
│  │  POST /chat/handoff, POST /knowledge/index       │    │
│  └──────────────────────┬───────────────────────────┘    │
│                         │                                 │
│  ┌──────────────────────┴───────────────────────────┐    │
│  │  LLM Orchestrator (LangChain)                     │    │
│  │  IntentClassifier → RAGEngine → ResponseGenerator │    │
│  │  + ConversationMemory + HandoffManager            │    │
│  └──────────────────────┬───────────────────────────┘    │
│                         │                                 │
│  ┌──────────────────────┴───────────────────────────┐    │
│  │  Infrastructure: ChromaDB | MySQL | Redis (opt)   │    │
│  │  LLM Providers: OpenAI | Gemini | Claude          │    │
│  └──────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────┘
```

### 3.2. Luồng xử lý chat

```
1. Khách gửi tin từ widget
2. PHP proxy → AI Service /chat/send
3. Intent Classifier: faq | catalog_search | order_status | policy | complaint
4. Nếu FAQ/policy → RAG retrieval → generate answer + citation
5. Nếu catalog_search → semantic search → gợi ý SKU
6. Nếu order_status → Order Tracker → format response
7. Nếu complaint hoặc confidence < threshold → handoff
8. Ghi message vào DB
9. Trả response → widget
```

### 3.3. Luồng Handoff

```
Bot: confidence < 0.6 HOẶC keyword trigger ("gặp người thật", "khiếu nại", "hoàn tiền")
→ Tạo support_ticket với draft response
→ Gửi notification đến admin
→ Admin mở chat panel, đọc summary, reply trực tiếp
→ Khi resolve → đóng ticket + đánh dấu session closed
```


## 4. Công nghệ lựa chọn

### 4.1. AI Agent Service

| Layer | Technology | Lý do |
|-------|-----------|-------|
| Runtime | Python 3.12 | Hệ sinh thái AI mạnh nhất |
| Framework | FastAPI | Async, auto-docs, hiệu năng cao |
| LLM Framework | LangChain | Orchestration, RAG chain, memory |
| Vector DB | ChromaDB | Nhẹ, local first, không cần server riêng |
| Embedding | text-embedding-3-small | Giá rẻ, tiếng Việt tốt |
| Web Server | Uvicorn + Gunicorn | Production ASGI |

### 4.2. LLM Provider (cấu hình qua .env)

```env
LLM_PROVIDER=openai          # openai | gemini | claude
LLM_MODEL=gpt-4o-mini
LLM_API_KEY=sk-...
LLM_TEMPERATURE=0.3
LLM_MAX_TOKENS=1024
EMBEDDING_PROVIDER=openai
EMBEDDING_MODEL=text-embedding-3-small
CHROMA_PERSIST_DIR=./chroma_data
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USER=root
MYSQL_DATABASE=banh_store
SERVICE_PORT=8000
CORS_ORIGINS=https://cake-i8l0.onrender.com
HANDOFF_CONFIDENCE_THRESHOLD=0.6
HANDOFF_KEYWORDS=hỗ trợ viên,người thật,gọi điện,khiếu nại,hoàn tiền gấp
```

### 4.3. Python Dependencies

```txt
fastapi==0.115.0
uvicorn[standard]==0.30.0
langchain==0.3.0
langchain-openai==0.2.0
langchain-google-genai==2.0.0
langchain-anthropic==0.2.0
chromadb==0.5.0
pymysql==1.1.0
pydantic==2.9.0
pydantic-settings==2.5.0
python-dotenv==1.0.0
httpx==0.27.0
```


## 5. Database Schema mới

### 5.1. chat_sessions

```sql
CREATE TABLE chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_token VARCHAR(64) NULL,
    status ENUM('active','closed','handoff') DEFAULT 'active',
    source ENUM('widget','messenger','zalo') DEFAULT 'widget',
    intent_label VARCHAR(50) NULL,
    summary TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.2. chat_messages

```sql
CREATE TABLE chat_messages (
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
```

### 5.3. faq_entries

```sql
CREATE TABLE faq_entries (
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
```

### 5.4. support_tickets

```sql
CREATE TABLE support_tickets (
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


## 6. API Design

### 6.1. AI Agent Service (FastAPI)

#### POST /chat/send - Gửi tin nhắn

```yaml
Request:
  session_id: string|null    # null = tạo session mới
  user_id: int|null          # ID user đã đăng nhập
  guest_token: string|null   # Token khách vãng lai
  message: string            # Nội dung tin nhắn
  context:                   # Ngữ cảnh bổ sung
    current_page: string     # Trang web khách đang xem
    cart_items: array        # Sản phẩm trong giỏ

Response:
  session_id: string
  reply:
    type: text|product_list|order_status|handoff|faq
    content: string
    citations: [{source, excerpt}]
    products: [{id, ten_banh, gia, hinh_anh, slug}]
    intent: string
    confidence: float
    suggested_actions: ["Xem chi tiết", "Thêm vào giỏ", ...]
  handoff: boolean
```

#### GET /catalog/search - Tìm kiếm sản phẩm

```yaml
Query:
  q: string
  category: string|null      # kem, ngọt, mặn, mì
  max_price: int|null
  limit: int (default 5)
Response:
  products: array
  total: int
```

#### POST /orders/lookup - Tra cứu đơn hàng

```yaml
Request:
  phone: string
  order_id: int|null
  user_id: int|null
Response:
  orders: [{id, status, total, created_at, items}]
```

#### POST /knowledge/index - Index lại knowledge base

```yaml
Request:
  source: products|policies|faq|all
Response:
  status: string
  indexed_count: int
```

#### POST /chat/handoff - Chuyển tiếp cho người thật

```yaml
Request:
  session_id: string
  reason: string
  priority: low|medium|high|urgent
Response:
  ticket_id: int
  status: string
```

### 6.2. PHP API Proxy

```
/api/chat/send.php      → Forward đến AI Service
/api/chat/history.php    → Lấy lịch sử session
/api/chat/sessions.php   → Danh sách session (admin)
```


## 7. Cấu trúc thư mục AI Service

```text
ai-service/
│
├── app/
│   ├── __init__.py
│   ├── main.py                    # FastAPI app entry point
│   ├── config.py                  # Settings from env / pydantic-settings
│   │
│   ├── api/                       # Route handlers
│   │   ├── __init__.py
│   │   ├── chat.py                # POST /chat/send, /chat/stream, /chat/handoff
│   │   ├── catalog.py             # GET /catalog/search
│   │   ├── orders.py              # POST /orders/lookup
│   │   └── knowledge.py           # POST /knowledge/index, /knowledge/reindex
│   │
│   ├── core/                      # Core AI logic
│   │   ├── __init__.py
│   │   ├── orchestrator.py        # LLM Orchestrator - main chain dispatcher
│   │   ├── intent_classifier.py   # Intent classification (LLM + keyword)
│   │   ├── rag_engine.py          # RAG retrieval + generation
│   │   └── memory.py              # Conversation buffer memory
│   │
│   ├── services/                  # Business logic services
│   │   ├── __init__.py
│   │   ├── catalog_service.py     # Product search & recommendation
│   │   ├── order_service.py       # Order status lookup
│   │   ├── policy_service.py      # Policy content retrieval
│   │   └── handoff_service.py     # Handoff decision & ticket management
│   │
│   ├── models/                    # Pydantic schemas
│   │   ├── __init__.py
│   │   ├── chat.py                # ChatRequest, ChatResponse, Message
│   │   ├── catalog.py             # ProductSearch, ProductResult
│   │   └── ticket.py              # TicketCreate, TicketResponse
│   │
│   ├── db/                        # Database layer
│   │   ├── __init__.py
│   │   ├── mysql.py               # MySQL connection pool (aiomysql/PyMySQL)
│   │   └── repositories/
│   │       ├── __init__.py
│   │       ├── chat_repo.py       # CRUD chat_sessions, chat_messages
│   │       ├── catalog_repo.py    # Read-only banh, promotions, reviews
│   │       └── ticket_repo.py     # CRUD support_tickets
│   │
│   └── knowledge/                 # Vector store & indexing
│       ├── __init__.py
│       ├── vector_store.py        # ChromaDB collection wrapper
│       ├── indexer.py             # Orchestrate full/partial reindex
│       ├── product_loader.py      # Load products from banh table
│       ├── policy_loader.py       # Load/scrape policy pages
│       └── faq_loader.py          # Load from faq_entries table
│
├── data/
│   ├── chroma_db/                 # Persist ChromaDB data (gitignore)
│   └── faq_seed.json             # FAQ seed data (10-15 câu mẫu)
│
├── tests/
│   ├── __init__.py
│   ├── conftest.py                # Fixtures: test DB, mock LLM
│   ├── test_chat.py               # Integration test for /chat/send
│   ├── test_rag.py                # Unit test RAG retrieval accuracy
│   └── test_intent.py             # Unit test intent classification
│
├── .env                           # Environment variables (gitignore)
├── .env.example                   # Template for onboarding
├── Dockerfile                     # Multi-stage Python Docker build
├── requirements.txt               # Python dependencies
├── pyproject.toml                 # Project metadata
└── README.md                      # AI Service documentation
```


## 8. Chi tiết các module

### 8.1. Intent Classifier

```python
# app/core/intent_classifier.py

INTENT_LABELS = [
    "faq",                # Hỏi thông tin chung, cách dùng
    "catalog_search",     # Tìm kiếm sản phẩm cụ thể
    "product_recommend",  # Nhờ gợi ý sản phẩm
    "order_status",       # Kiểm tra trạng thái đơn
    "order_create",       # Muốn đặt bánh
    "policy_shipping",    # Hỏi về vận chuyển
    "policy_payment",     # Hỏi về thanh toán
    "policy_return",      # Hỏi về đổi trả
    "complaint",          # Khiếu nại, phàn nàn
    "chitchat",           # Chào hỏi, xã giao
    "handoff_request"     # Yêu cầu gặp người thật
]
```

Phương pháp: LLM few-shot prompting + rule-based keyword fallback.

### 8.2. RAG Engine

```python
# app/core/rag_engine.py

class RAGEngine:
    """
    Knowledge sources trong ChromaDB:

    1. Product catalog (~50 products):
       - Document: ten_banh + mo_ta + loai + gia
       - Metadata: id, gia, loai, hinh_anh, slug

    2. Policies (4 pages):
       - Document per section: title + content
       - Metadata: policy_type, url

    3. FAQ entries:
       - Document: question + answer
       - Metadata: category, id
    """

    def retrieve(self, query, top_k=5, filter=None) -> list[Document]
    def generate_answer(self, query, docs, chat_history=None) -> str
```

### 8.3. System Prompt (RAG)

```
Bạn là trợ lý CSKH của Gấu Bakery, tiệm bánh online tại Việt Nam.

QUY TẮC:
1. Trả lời bằng tiếng Việt, thân thiện, chuyên nghiệp
2. Luôn trích dẫn nguồn từ tài liệu nội bộ khi trả lời
3. Nếu không có thông tin, nói thật và đề nghị chuyển người thật
4. Gợi ý sản phẩm phù hợp khi khách có nhu cầu
5. Format giá: XXX.XXX VNĐ
6. Khi khách muốn đặt bánh, hướng dẫn vào trang sản phẩm
7. Không hứa giảm giá, khuyến mãi nếu không có trong hệ thống

THÔNG TIN TIỆM:
- Tên: Gấu Bakery
- Hotline: 0901 234 567 (8h-21h hàng ngày)
- Website: https://cake-i8l0.onrender.com/cakev0/
```

### 8.4. Chat Widget Frontend

Vị trí: góc phải dưới màn hình, floating button + expandable window.
Components:
- Floating toggle button (💬 icon + notification badge)
- Chat window: header (logo + status), message list, quick replies, input box
- Quick replies: "Xem menu bánh kem", "Kiểm tra đơn hàng", "Chính sách giao hàng"
- Message types: text bubble, product card (ảnh + tên + giá + nút), order status card

Files:
- `assets/js/gau-chat-widget.js` - Widget logic (polling → WebSocket later)
- `assets/css/gau-chat-widget.css` - Widget styles
- Tích hợp vào `includes/footer.html`

### 8.5. Admin Chat Dashboard

Tab mới trong `admin/admin.php?tab=chat`:
- Active sessions list (real-time via polling/SSE)
- Click session → mở chat panel
- Agent actions: reply, close session + summary, view ticket
- Notification badge khi có handoff mới
- Thống kê: số chat/ngày, resolution rate, intent distribution

### 8.6. Knowledge Base Indexing

Product document template:
```
SAN PHAM: {ten_banh}
LOAI: {loai}
GIA: {gia} VND
MO TA: {mo_ta}
SLUG: {slug}
```

Policy document template:
```
CHINH SACH: {policy_name}
TIEU DE: {section_title}
NOI DUNG: {section_content}
URL: {url}
```

FAQ seed data (`data/faq_seed.json`):
```json
[
  {
    "question": "Thời gian giao bánh là bao lâu?",
    "answer": "Gấu Bakery giao bánh trong ngày với các đơn đặt trước 15h. Với đơn sau 15h, chúng tôi sẽ giao vào sáng hôm sau.",
    "category": "shipping"
  },
  {
    "question": "Tôi có thể thanh toán bằng những cách nào?",
    "answer": "Gấu Bakery hỗ trợ: (1) VNPAY online, (2) Thanh toán khi nhận hàng (COD), (3) Chuyển khoản ngân hàng.",
    "category": "payment"
  },
  {
    "question": "Tôi muốn đổi bánh thì phải làm sao?",
    "answer": "Bạn có thể đổi bánh trong 2 giờ sau khi nhận nếu bánh có vấn đề chất lượng. Liên hệ hotline 0901 234 567.",
    "category": "returns"
  }
]
```


## 9. Lộ trình triển khai (6 Phase)

### Phase 0: Setup Infrastructure (2 ngày)

- [ ] Tạo thư mục `ai-service/` với cấu trúc như trên
- [ ] Viết `Dockerfile`, cập nhật `docker-compose.yml` (thêm AI service)
- [ ] Cài đặt Python dependencies (`requirements.txt`)
- [ ] Viết `app/config.py` với multi-provider LLM support
- [ ] Viết `app/db/mysql.py` connection pool
- [ ] Tạo migration SQL cho 4 bảng mới (chat_sessions, chat_messages, faq_entries, support_tickets)
- [ ] Tạo `app/main.py` FastAPI skeleton với health check
- [ ] Cấu hình CORS, logging

### Phase 1: Knowledge Base + FAQ Bot (3 ngày)

- [ ] Viết `app/knowledge/vector_store.py` (ChromaDB wrapper: init, add, query)
- [ ] Viết `app/knowledge/product_loader.py` (load từ bảng `banh` → ChromaDB)
- [ ] Viết `app/knowledge/policy_loader.py` (load nội dung từ 4 trang policy PHP)
- [ ] Viết `app/knowledge/faq_loader.py` (load từ bảng `faq_entries`)
- [ ] Viết `app/knowledge/indexer.py` (orchestrate full build)
- [ ] Viết `app/core/rag_engine.py` (retrieve + generate answer với citations)
- [ ] Viết `app/core/intent_classifier.py` (LLM + keyword)
- [ ] Viết `app/core/orchestrator.py` (dispatch intent → appropriate handler)
- [ ] Viết `app/api/chat.py` (POST /chat/send - FAQ only)
- [ ] Seed FAQ data (10-15 câu mẫu)
- [ ] Test RAG retrieval với các câu hỏi mẫu tiếng Việt

### Phase 2: Chat Widget Frontend (2 ngày)

- [ ] Tạo `assets/js/gau-chat-widget.js` (class-based, event-driven)
- [ ] Tạo `assets/css/gau-chat-widget.css` (responsive, animation)
- [ ] Tạo PHP proxy: `api/chat/send.php`, `api/chat/history.php`
- [ ] Tạo `api/chat/sessions.php` (admin list)
- [ ] Tích hợp widget vào `includes/footer.html`
- [ ] Implement polling (gọi API mỗi 3s, sau upgrade lên WebSocket)
- [ ] Render product cards trong chat (ảnh + tên + giá + nút "Xem")
- [ ] Quick reply buttons (dynamic từ server)
- [ ] Guest token generation + lưu localStorage
- [ ] Test widget flow end-to-end

### Phase 3: Catalog Search + Order Lookup (3 ngày)

- [ ] Viết `app/services/catalog_service.py`
  - Semantic search trên product vector store
  - Filter theo loại (kem/ngọt/mặn/mì), giá
  - Format kết quả thành product card
- [ ] Viết `app/api/catalog.py` (GET /catalog/search)
- [ ] Viết `app/services/order_service.py`
  - Lookup theo phone + order_id hoặc user_id
  - Format kết quả thành order card (status, items, timeline)
- [ ] Viết `app/api/orders.py` (POST /orders/lookup)
- [ ] Tích hợp order lookup vào orchestrator (khi intent = order_status)
- [ ] Product recommendation engine:
  - Dựa trên intent + context (cart items, current page)
  - Ưu tiên sản phẩm đang khuyến mãi
- [ ] Render order cards trong chat widget
- [ ] Test catalog search và order lookup

### Phase 4: Handoff & Ticket System (3 ngày)

- [ ] Viết `app/services/handoff_service.py`:
  - Check confidence threshold (default 0.6)
  - Check keyword triggers ("gặp người thật", "khiếu nại", "hoàn tiền")
  - Auto-escalate khi khách gửi 3+ complaint messages
  - Tạo support_ticket + draft AI response
- [ ] Viết `app/api/chat.py` (POST /chat/handoff)
- [ ] Admin chat dashboard UI:
  - Tab "Hội thoại" trong admin.php
  - Real-time session list (polling/SSE)
  - Click → mở chat takeover panel
  - Agent reply input + send button
  - Close session + auto-generate summary
  - View/create ticket
- [ ] Draft response: AI gợi ý câu trả lời dựa trên context + FAQ
- [ ] Notification badge (số ticket đang open)
- [ ] Test full handoff flow: bot → ticket → agent reply → resolve

### Phase 5: Polish & Production (2 ngày)

- [ ] Rate limiting (in-memory hoặc Redis)
- [ ] Streaming response (Server-Sent Events) cho typing indicator
- [ ] WebSocket upgrade (Socket.io hoặc FastAPI WebSocket)
- [ ] Caching frequent queries (Redis hoặc in-memory LRU)
- [ ] Logging: request ID, latency, LLM token usage
- [ ] Admin stats dashboard: số chat/ngày, intent distribution, resolution rate, avg response time
- [ ] Security review:
  - Input sanitization (chat messages)
  - Rate limit per IP/session
  - CORS hardening
  - SQL injection prevention (prepared statements)
- [ ] Deploy AI Service lên Render/Railway
- [ ] Environment variables cho production
- [ ] Documentation: API docs (FastAPI auto), vận hành, troubleshooting

### Phase 6: Advanced (Tương lai)

- [ ] Chốt đơn trong chat: add to cart → checkout link
- [ ] Tích hợp Facebook Messenger webhook
- [ ] Tích hợp Zalo OA webhook
- [ ] Multi-language (VI/EN) với i18n
- [ ] Sentiment analysis + auto-escalate khi khách frustrated
- [ ] Proactive triggers: chào khách sau 30s browse, khi cart > 200K
- [ ] AI phân tích feedback định kỳ (weekly report)
- [ ] A/B testing system prompt variants
- [ ] Voice chat (speech-to-text + text-to-speech)


## 10. Rủi ro & Giải pháp

| # | Rủi ro | Mức độ | Giải pháp |
|---|--------|--------|-----------|
| 1 | LLM API costs tăng cao | 🔴 High | Cache response giống nhau; dùng GPT-4o-mini; rate limit; Gemini free tier fallback |
| 2 | LLM trả lời sai chính sách | 🟡 Medium | Luôn kèm citation link; confidence threshold → handoff; admin review định kỳ |
| 3 | ChromaDB performance | 🟢 Low | ~50 SP + 4 policies; scale được đến hàng ngàn docs |
| 4 | PHP proxy bottleneck | 🟡 Medium | PHP-FPM; cache response; WebSocket bypass PHP |
| 5 | Latency LLM (2-5s) | 🟡 Medium | Streaming response; typing indicator; queue async |
| 6 | Không có GPU cho embedding local | 🟢 Low | Dùng API embedding (~$0.02/1M tokens) |
| 7 | Dữ liệu sản phẩm thay đổi | 🟡 Medium | Webhook reindex khi admin CRUD; cron job nightly |
| 8 | SQL injection proxy PHP | 🟡 Medium | Prepared statements; validate input; rate limit |
| 9 | Tiếng Việt + teencode | 🟡 Medium | GPT-4o-mini hiểu TV tốt; normalization step |
| 10 | Render cold start | 🟡 Medium | Uptime monitor keep-alive; cân nhắc Railway/VPS |

---

## 11. Phụ lục

### 11.1. Use Case tham khảo

- **24/7 AI Chatbot** - Self-service FAQ, trả lời ngoài giờ hành chính
- **Customer Support Agent** - Tiếp nhận, phân loại, escalate, resolve
- **E-commerce Personal Shopper Agent** - Gợi ý sản phẩm, upsell, cross-sell
- **[ShoppingGPT](https://github.com/hoanganhvu123/ShoppingGPT)** - Architecture reference

### 11.2. Tài khoản test

```
Admin: admin / (hashed in DB)
User:  nhatanh / (hashed in DB)

VNPAY Sandbox:
  Ngân hàng: NCB
  Số thẻ:    9704198526191432198
  Tên:       NGUYEN VAN A
  Ngày:      07/15
  OTP:       123456
```

### 11.3. Môi trường development

```bash
# Start full stack (PHP + MySQL + AI Service)
docker compose up --build

# Hoặc chạy AI Service riêng
cd ai-service
python -m venv venv
venv\\Scripts\\activate     # Windows
pip install -r requirements.txt

# Index knowledge base lần đầu
python -m app.knowledge.indexer --full

# Run dev server
uvicorn app.main:app --reload --port 8000

# Truy cập API docs
http://localhost:8000/docs
```

### 11.4. API References

- [LangChain RAG Tutorial](https://python.langchain.com/docs/tutorials/rag/)
- [ChromaDB Documentation](https://docs.trychroma.com/)
- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [OpenAI Chat API](https://platform.openai.com/docs/api-reference/chat)
- [Google Gemini API](https://ai.google.dev/gemini-api)
- [Anthropic Claude API](https://docs.anthropic.com/en/docs)

---

> **Trạng thái:** Draft - Chờ Review
> **Người lập:** AI Agent (Cline)
> **Ngày lập:** 2026-07-06
> **Người review:** [TBD]
