# Spec thiết kế: Trợ lý CSKH & Chốt đơn Đa kênh cho Gấu Bakery (Multi-Agent RAG)

> **Ngày lập:** 2026-07-19 | **Trạng thái:** Approved design — chờ implementation plan
> **Kế thừa:** `docs/superpowers/plans/ai-cskh-agent-plan.md` (plan triển khai 2026-07-06) và `docs/superpowers/plans/ai-cskh-agent-thesis-plan.md` (plan khóa luận 2026-07-06). Spec này là tài liệu hợp nhất chính thức; khi mâu thuẫn, spec này thắng.
> **Case study:** Gấu Bakery — https://cake-i8l0.onrender.com/cakev0/

---

## 1. Tổng quan

Xây dựng AI Agent CSKH đa kênh cho website bán bánh Gấu Bakery (PHP 8.2 + MySQL 8.0, không framework), đồng thời phục vụ khóa luận: so sánh định lượng **System A (RAG single-pipeline)** với **System B (multi-agent LangGraph)** trên cùng hạ tầng.

Năng lực agent:

| # | Năng lực | Đầu vào | Đầu ra |
|---|----------|---------|--------|
| 1 | Trả lời FAQ | Câu hỏi + knowledge base | Câu trả lời có trích nguồn |
| 2 | Tra cứu catalog | Từ khóa, loại bánh, giá | Danh sách SKU (product card) |
| 3 | Gợi ý sản phẩm | Ngữ cảnh hội thoại | 3–5 sản phẩm đề xuất |
| 4 | Giải thích chính sách | Truy vấn đổi trả/giao hàng/thanh toán | Trích dẫn chính sách |
| 5 | Kiểm tra đơn hàng | SĐT + mã đơn (hoặc user đăng nhập) | Trạng thái, timeline (order card) |
| 6 | **Chốt đơn COD trong chat** | SKU, số lượng, tên/SĐT/địa chỉ | Đơn hàng thật trong bảng `orders` |
| 7 | Handoff người thật | Confidence thấp / khiếu nại / yêu cầu | Ticket + tóm tắt + draft response |

## 2. Quyết định thiết kế đã chốt

| # | Quyết định | Lựa chọn | Lý do |
|---|-----------|----------|-------|
| D1 | Quan hệ với 2 doc cũ | Kế thừa + hợp nhất | Tái dùng thiết kế đã có, cập nhật hiện trạng code |
| D2 | LLM provider | **Gemini 2.x Flash free tier** (LLM) + **text-embedding-004** (embedding) | Free 1500 req/ngày, đủ dev + thực nghiệm, tiếng Việt ổn |
| D3 | Phạm vi chốt đơn | **Full trong chat (COD)** | Đề tài yêu cầu "chốt đơn"; slot-filling + xác nhận + tạo đơn thật |
| D4 | Kênh | Widget web + Admin inbox hợp nhất + **Facebook Messenger** | Đa kênh đúng nghĩa; Zalo OA ngoài scope |
| D5 | Kiến trúc service | **1 FastAPI service, 2 engine sau flag `ENGINE=baseline\|multiagent`** | A/B công bằng: chung normalizer, vector store, DB, API contract |
| D6 | Tạo đơn | Python **không** insert thẳng MySQL; gọi **PHP internal API** | 1 nguồn sự thật business logic (coupon, invoice email, status) |
| D7 | Guest đặt đơn | Không hỗ trợ — yêu cầu đăng nhập | `orders.user_id` NOT NULL (FK `users`); không đổi schema |
| D8 | Thanh toán trong chat | COD only | VNPAY → bot đưa link checkout; không xử lý thanh toán trong chat |

## 3. Kiến trúc hệ thống

```
┌────────────────────────── Kênh ──────────────────────────┐
│ Widget (web)      Messenger        Admin inbox           │
│    │                  │            (admin.php?tab=chat)  │
│    ▼                  ▼                  ▼               │
│ PHP proxy        FB webhook         PHP proxy            │
│ /api/chat/*      (FastAPI trực tiếp)  /api/chat/*        │
└────┬──────────────────┬──────────────────┬───────────────┘
     └──────────────────┴──────────────────┘
                        ▼
┌──────────────────────────────────────────────────────────┐
│         AI Agent Service (Python 3.12 + FastAPI)          │
│                                                          │
│  Channel Adapter → chuẩn hóa → POST /chat/send nội bộ    │
│                                                          │
│  ENGINE=baseline:                                        │
│    Normalizer → Embed → ChromaDB → 1 LLM call → answer   │
│                                                          │
│  ENGINE=multiagent (LangGraph StateGraph):               │
│    Normalizer → Router Agent                             │
│      ├─ Retrieval Agent (faq/catalog/policy, per-        │
│      │   collection + rerank + retry query-rewrite)      │
│      ├─ Action Agent (order lookup + order create        │
│      │   slot-filling → PHP internal API)                │
│      ├─ Chitchat Agent                                   │
│      └─ Handoff Policy Agent (multi-factor)              │
│    → Response Aggregator (format + citation)             │
│                                                          │
│  Hạ tầng: ChromaDB (local persist) | MySQL banh_store    │
│  LLM: Gemini 2.x Flash | Embedding: text-embedding-004   │
└──────────────────────────────────────────────────────────┘
```

- Hai engine expose **cùng API contract** (`/chat/send` response schema giống hệt) — frontend/eval harness không biết engine nào đang chạy.
- Danh sách intent, LangGraph state machine, chi tiết từng agent: giữ nguyên thiết kế trong thesis plan §7 (Router/Retrieval/Action/Handoff/Aggregator, `AgentState`, conditional edges, retry).

## 4. Chốt đơn trong chat (flow chi tiết)

### 4.1. Điều kiện

- Khách **phải đăng nhập** (session PHP hiện có; proxy truyền `user_id` đã xác thực xuống AI service — AI service không tự xác thực khách).
- Messenger: PSID chưa map được user → bot hướng dẫn đăng nhập web để đặt đơn; Messenger chỉ tư vấn/tra cứu.

### 4.2. Slot-filling đa lượt

```
1. Intent order_create → Action Agent mở draft order trong chat_sessions.metadata
2. Thu thập tuần tự (mỗi slot có validate):
   - items: [{banh_id, quantity}] — resolve tên bánh → SKU qua catalog search, xác nhận giá
   - recipient_name, phone (regex VN), address, note (optional)
3. Bước XÁC NHẬN bắt buộc: bot tóm tắt đơn (items + giá + tổng + địa chỉ)
   → khách trả lời đồng ý rõ ràng mới tạo đơn
4. Action Agent gọi PHP internal API tạo đơn COD
5. Thành công → order card + mã đơn; thất bại → báo lý do, GIỮ draft
```

### 4.3. PHP Internal API

`POST /api/internal/orders/create.php`

- Tái dùng logic `pages/checkout.php` (INSERT `orders` + `order_items`, prepared statements, coupon, trigger invoice email flow hiện có).
- Auth server-to-server: header `X-Internal-Signature` = HMAC-SHA256(body, `INTERNAL_API_SECRET`). Sai chữ ký → 401. Secret đặt trong env cả 2 phía, không hardcode.
- Request: `{user_id, recipient_name, phone, address, note, payment_method: "COD", items: [{banh_id, quantity}], coupon_code?}`
- Response: `{order_id, status, total_amount}` hoặc `{error, reason}`.
- Validate phía PHP: user tồn tại, sản phẩm tồn tại, quantity 1–20, tổng tiền tính lại server-side (không tin giá từ client/AI).

## 5. Database

4 bảng mới (SQL chi tiết theo plan cũ §5, với chỉnh sửa):

| Bảng | Ghi chú |
|------|---------|
| `chat_sessions` | `source` ENUM('widget','messenger') — bỏ 'zalo'; **thêm** `external_user_id VARCHAR(64) NULL` (Messenger PSID) + INDEX; `metadata` JSON chứa draft order state |
| `chat_messages` | Giữ nguyên plan cũ (sender: customer/bot/agent, content_type: text/product_card/order_card) |
| `faq_entries` | Giữ nguyên |
| `support_tickets` | Giữ nguyên (priority, status, draft_response) |

Không đổi schema bảng hiện có.

## 6. API

### 6.1. FastAPI

| Endpoint | Mô tả |
|----------|-------|
| `POST /chat/send` | Contract theo plan cũ §6.1; thêm field response `order` (order card khi vừa tạo đơn) |
| `GET /catalog/search` | Semantic search sản phẩm |
| `POST /orders/lookup` | Tra cứu đơn theo phone+order_id hoặc user_id |
| `POST /chat/handoff` | Tạo ticket |
| `POST /knowledge/index` | Reindex (products/policies/faq/all) |
| `GET /channels/messenger/webhook` | Verify token (hub.challenge) |
| `POST /channels/messenger/webhook` | Nhận message, verify `X-Hub-Signature-256` |
| `GET /health` | Health check |

### 6.2. PHP proxy + internal

```
/api/chat/send.php       → forward AI service (kèm user_id đã xác thực)
/api/chat/history.php    → lịch sử session
/api/chat/sessions.php   → danh sách session (admin, kiểm tra quyền admin)
/api/internal/orders/create.php → tạo đơn (HMAC auth, §4.3)
```

## 7. Kênh Messenger

- FastAPI nhận webhook trực tiếp (không qua PHP).
- Map PSID → `chat_sessions.external_user_id`; session per PSID.
- Reply qua Messenger Send API; product card → generic template (ảnh + tên + giá + nút link).
- Env: `FB_PAGE_TOKEN`, `FB_VERIFY_TOKEN`, `FB_APP_SECRET`.
- **Rủi ro chấp nhận:** Facebook App review chậm → demo bằng test user + ngrok; không chặn tiến độ widget/inbox.

## 8. Admin inbox hợp nhất

Tab `admin.php?tab=chat`:

- Danh sách session mọi kênh (widget + messenger), badge kênh, badge handoff, polling 3–5s.
- Mở session → xem lịch sử, reply trực tiếp (ghi `chat_messages` sender='agent', đẩy về đúng kênh).
- Ticket panel: xem draft response AI, sửa, gửi, resolve.
- Thống kê: số chat/ngày, intent distribution, resolution rate.

## 9. Thiết kế nghiên cứu (giữ nguyên thesis plan, delta bên dưới)

Giữ nguyên: 5 research questions, literature review, dataset 150 hội thoại (70/30 common/edge-case, 2 annotator, Cohen's Kappa > 0.8), Vietnamese Normalizer (diacritics restoration + teencode dict + TMĐT glossary), 4 system so sánh (System 0 / A / B / B′ ablation), thống kê paired t-test hoặc Wilcoxon, p < 0.05.

**Delta:**

1. **Metric mới M6 — Task Completion Rate (chốt đơn):** tỷ lệ hội thoại intent `order_create` kết thúc bằng đơn tạo thành công / tổng hội thoại order_create trong dataset. Chỉ System B có Action Agent slot-filling; System A đo được bao nhiêu % tự thoát sang hướng dẫn checkout đúng.
2. LLM cố định **Gemini 2.x Flash** cho cả A và B (cùng model, cùng temperature) — biến độc lập duy nhất là kiến trúc.
3. Eval harness throttle theo free tier quota (xem §11).

5 metrics gốc: M1 Answer Accuracy (manual, 2 annotator), M2 Grounded Response Rate (auto check citation), M3 Handoff Accuracy (P/R/F1 vs ground truth), M4 First Response Time (ms, server-side), M5 Conversation Retention Rate (≥3 user messages/session).

## 10. Cấu hình

```env
ENGINE=multiagent               # baseline | multiagent
LLM_PROVIDER=gemini
LLM_MODEL=gemini-2.0-flash      # chốt version cụ thể lúc implement
EMBEDDING_MODEL=text-embedding-004
GEMINI_API_KEY=...
CHROMA_PERSIST_DIR=./data/chroma_db
MYSQL_HOST=... MYSQL_DATABASE=banh_store
INTERNAL_API_SECRET=...         # HMAC với PHP
INTERNAL_ORDER_API_URL=https://.../api/internal/orders/create.php
FB_PAGE_TOKEN=... FB_VERIFY_TOKEN=... FB_APP_SECRET=...
HANDOFF_CONFIDENCE_THRESHOLD=0.6
CORS_ORIGINS=https://cake-i8l0.onrender.com
```

Cấu trúc thư mục `ai-service/` theo plan cũ §7, bổ sung:

```
app/engines/baseline.py         # System A
app/engines/multiagent/         # System B (graph.py, nodes/*.py)
app/channels/messenger.py       # webhook + send API
app/services/order_create_service.py  # slot-filling + gọi PHP internal API
eval/                           # dataset/, run_eval.py, metrics.py
```

## 11. Error handling

| Tình huống | Xử lý |
|-----------|-------|
| LLM timeout/error | Retry 1 lần → fallback message + đề nghị handoff |
| Gemini rate limit (free tier) | Exponential backoff; eval harness throttle (sleep giữa request, checkpoint resume); production: message "hệ thống bận" + handoff |
| Retrieval rỗng/kém | System B: retry với query rewrite (max 2) → handoff; System A: trả lời kèm disclaimer + confidence thấp → handoff |
| Order create fail (validation, sản phẩm không tồn tại) | Bot báo lý do cụ thể, giữ draft, cho sửa slot |
| PHP internal API không phản hồi | Bot xin lỗi + tạo ticket handoff kèm draft order để nhân viên tạo đơn tay |
| Messenger webhook sai chữ ký | 403, log, không xử lý |
| Session mất/hết hạn | Tạo session mới, chào lại |

## 12. Security

- Prepared statements mọi truy vấn (cả PHP internal API lẫn Python read-only).
- HMAC-SHA256 cho internal API; verify `X-Hub-Signature-256` cho Messenger.
- Rate limit `/chat/send`: per session + per IP (in-memory, đủ cho scope).
- Sanitize input chat trước khi render (XSS) ở widget và admin inbox.
- AI service dùng MySQL user quyền hạn chế: SELECT trên bảng nghiệp vụ, INSERT/UPDATE chỉ trên 4 bảng chat mới.
- Không đưa PII vào prompt log; log request-id, latency, token usage.

## 13. Testing

| Lớp | Công cụ | Nội dung |
|-----|---------|----------|
| Unit (Python) | pytest, mock LLM | Normalizer (teencode/diacritics case), Router intent, Handoff policy multi-factor, slot-filling validate |
| Integration (Python) | pytest + fake LLM + test DB | `/chat/send` cả 2 engine, order create flow end-to-end tới PHP API (mock HTTP) |
| PHP | pattern `tests/` hiện có | Internal order API: HMAC sai → 401, validate items, tính lại tổng tiền |
| Eval | `eval/run_eval.py` | Chạy dataset 150 hội thoại qua cả 2 engine → CSV → metrics.py tính M1–M6 |
| Manual | 2 annotator | M1 Accuracy, Cohen's Kappa |

## 14. Lộ trình (cao độ — chi tiết ở implementation plan)

1. **Nền tảng:** migration 4 bảng, ai-service skeleton, config, knowledge indexing (catalog + 4 policy + FAQ seed)
2. **System A** (baseline RAG) + widget + PHP proxy — end-to-end sớm nhất
3. **System B** (LangGraph multi-agent) trên cùng contract
4. **Chốt đơn:** PHP internal API + slot-filling Action Agent
5. **Handoff + Admin inbox** hợp nhất
6. **Messenger** channel
7. **Dataset + Normalizer + Eval harness** → chạy thực nghiệm → phân tích
8. **Polish:** rate limit, logging, deploy Render, docs

## 15. Rủi ro chính

| Rủi ro | Mức | Giải pháp |
|--------|-----|-----------|
| Gemini free tier quota không đủ lúc eval | Cao | Throttle + checkpoint resume; dự phòng: API key thứ 2 hoặc trả phí nhỏ |
| FB App review chậm | Cao | Test user + ngrok cho demo; Messenger là kênh phụ, không chặn thesis |
| Chốt đơn bị lạm dụng (spam đơn) | Trung bình | Yêu cầu login + rate limit + quantity cap + xác nhận bắt buộc |
| LLM trả lời sai chính sách | Trung bình | Citation bắt buộc, confidence threshold, handoff |
| Render cold start làm lệch M4 latency | Trung bình | Đo M4 trên môi trường local/warm; ghi rõ trong báo cáo |
| Tiếng Việt teencode ngoài dictionary | Trung bình | Ablation B′ đo chính xác đóng góp normalizer; mở rộng dict theo dataset |

---

> **Trạng thái:** Design approved (user, 2026-07-19) — bước tiếp: implementation plan (superpowers:writing-plans)
