# Kịch bản Demo — Bảo vệ khóa luận

> Hướng dẫn demo hệ thống AI CSKH Gấu Bakery trước hội đồng.
> Chạy trên: https://cake-i8l0.onrender.com/cakev0/
> Engine: `ENGINE=demo` (fault-tolerant) hoặc `ENGINE=multiagent` (full).

---

## Chuẩn bị trước demo

1. Mở website Gấu Bakery, đảm bảo trang load bình thường
2. Bật widget chat (icon góc phải dưới)
3. Chuẩn bị 2 tab: (a) website chat, (b) Render dashboard hoặc terminal logs
4. Nếu demo Telegram notify: mở Telegram group trên điện thoại

**Lưu ý:** Dùng `ENGINE=demo` để tránh lỗi Gemini quota. Nếu API ổn, dùng `ENGINE=multiagent` cho demo thật.

---

## Kịch bản 1: FAQ — Hỏi thông tin cửa hàng

**Mục đích:** Cho thấy retrieval agent truy xuất từ collection `faq`.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"shop mở mấy giờ vậy"** | Bot trả lời giờ mở cửa, có trích nguồn từ FAQ |
| 2 | Gõ: **"ship bao lau"** (không dấu) | Normalizer xử lý → "ship bao lâu" → policy_shipping intent |

**Điểm nhấn cho hội đồng:**
- Router phân loại đúng `faq` và `policy_shipping`
- Vietnamese normalizer xử lý text không dấu
- Citation trỏ về đúng nguồn trong knowledge base

---

## Kịch bản 2: Tìm sản phẩm — Catalog Search

**Mục đích:** Cho thấy hybrid retrieval (dense + BM25) trên collection `products`.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"có bánh kem dâu không"** | Trả về sản phẩm bánh kem dâu với thông tin giá, mô tả |
| 2 | Gõ: **"cái đó giá bao nhiêu"** | Router dùng history context → hiểu "cái đó" = bánh kem dâu |

**Điểm nhấn:**
- Hybrid search: BM25 bắt keyword "kem dâu" + dense embedding bắt semantic
- History-aware: câu 2 không nhắc tên bánh nhưng bot hiểu nhờ lịch sử 6 messages
- Product cards hiển thị metadata (giá, loại)

---

## Kịch bản 3: Gợi ý sản phẩm — Product Recommend

**Mục đích:** Cho thấy promotion-aware reranking.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"sinh nhật bé trai nên mua bánh gì"** | Bot gợi ý 3-5 bánh, sản phẩm đang khuyến mãi ưu tiên lên đầu |

**Điểm nhấn:**
- Intent `product_recommend` → retrieval agent
- `_promoted_ids()` query MySQL promotions table
- `_rerank_by_promotion()` đẩy sản phẩm KM lên đầu (stable sort)

---

## Kịch bản 4: Chính sách — Policy Query

**Mục đích:** Cho thấy retrieval từ collection `policies` với citation chính xác.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"thanh toán vnpay được không"** | Bot trả lời chính sách thanh toán, trích nguồn policy doc |
| 2 | Gõ: **"đổi bánh được ko a"** | Normalizer: "ko" → "không" → policy_return intent |

**Điểm nhấn:**
- Teencode normalization: "ko" → "không"
- 3 collection riêng biệt (products, policies, faq) → citation không lẫn

---

## Kịch bản 5: Tra cứu đơn hàng — Order Status

**Mục đích:** Cho thấy action agent truy vấn MySQL realtime.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Đăng nhập tài khoản test có đơn hàng | |
| 2 | Gõ: **"đơn hàng của tôi đến đâu rồi"** | Bot trả về danh sách đơn hàng với trạng thái, số tiền |

**Điểm nhấn:**
- Router → `order_status` intent → action agent (không phải retrieval)
- `extract_phone()` và `extract_order_id()` dùng regex
- `lookup_orders()` query MySQL realtime
- Status mapping: "pending" → "Chờ xác nhận" (tiếng Việt)

**Fallback nếu chưa đăng nhập:**
- Gõ: **"kiểm tra đơn 0901234567"** → bot extract phone number từ text

---

## Kịch bản 6: Đặt bánh COD — Order Create (In-chat)

**Mục đích:** Cho thấy slot-filling conversational flow.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"đặt 2 bánh croissant"** | Bot hỏi thông tin: tên, SĐT, địa chỉ giao |
| 2 | Trả lời từng bước theo hướng dẫn bot | Bot confirm đơn trước khi tạo |
| 3 | Xác nhận | Đơn hàng tạo trong MySQL `orders` table |

**Điểm nhấn:**
- `_open_draft()` check session metadata → giữ context order_create qua nhiều turn
- `_has_exit_word()` detect "hủy", "thôi" → cancel draft
- Tạo đơn qua PHP internal API (1 nguồn sự thật business logic)

---

## Kịch bản 7: Chitchat — Hội thoại thường

**Mục đích:** Cho thấy router phân biệt chitchat vs retrieval.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"chào shop"** | Bot chào lại thân thiện, ngắn gọn |
| 2 | Gõ: **"cảm ơn nha"** | Bot đáp lịch sự, không retrieval |

**Điểm nhấn:**
- Keyword "chào", "cảm ơn" → `chitchat` @ 0.55 → skip LLM router call
- Chitchat node dùng LLM generate trực tiếp (không query VectorStore)
- Tiết kiệm 1 LLM call nhờ keyword-first optimization

---

## Kịch bản 8: Khiếu nại + Handoff — Escalation

**Mục đích:** Cho thấy multi-factor handoff policy + Telegram notification.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"bánh giao bị móp, bực quá, hoàn tiền cho tôi"** | Bot nhận khiếu nại, tạo ticket, thông báo Telegram |
| 2 | Kiểm tra Telegram group | Nhận alert: session ID, priority HIGH, reason codes |

**Điểm nhấn:**
- `decide_handoff()` — 4 factors: intent (`complaint`), keyword ("hoàn tiền"), confidence, retry_count
- Ticket tạo trong MySQL `support_tickets` table với draft response
- `notify_handoff()` gửi async Telegram (daemon thread, non-blocking)
- Bot response: "Đã chuyển cho nhân viên... hotline 0901 234 567"

---

## Kịch bản 9: Yêu cầu gặp người thật — Direct Handoff

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ: **"cho gặp nhân viên"** | Bot chuyển ngay, skip LLM draft generation |

**Điểm nhấn:**
- `handoff_request` intent → handoff node
- Skip `llm.generate()` draft (optimization: khách đã yêu cầu rõ ràng)

---

## Kịch bản 10: Retry + Query Rewrite

**Mục đích:** Cho thấy retry mechanism khi retrieval confidence thấp.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Gõ câu mơ hồ: **"cái này có được ko"** | Retrieval confidence < 0.5 → rewrite → retry |
| 2 | Quan sát log | Thấy: retrieval → rewrite → retrieval → aggregate (hoặc handoff nếu 2 retry fail) |

**Điểm nhấn:**
- `after_retrieval()`: `needs_retry AND retry_count < 2` → rewrite node
- Rewrite node dùng LLM viết lại query rõ nghĩa hơn
- Max 2 retries → handoff (tránh infinite loop)

---

## Kịch bản 11: Demo Engine Fallback

**Mục đích:** Cho thấy fault-tolerance khi API lỗi.

| Bước | Hành động | Kỳ vọng |
|------|-----------|---------|
| 1 | Đặt `ENGINE=demo`, tắt Gemini API key | |
| 2 | Gõ: **"có bánh gì ngon"** | Bot trả lời bằng pre-scripted response (không crash) |

**Điểm nhấn:**
- `DemoEngine` wraps `MultiAgentEngine`, catches all exceptions
- Fallback: `keyword_fallback()` → pre-scripted Vietnamese response
- 11 canned responses covering all intents
- Widget chat không bao giờ hiện lỗi 500

---

## Thứ tự demo đề xuất

Trình bày 25-30 phút:

1. **Mở đầu** (2 phút): Giới thiệu website Gấu Bakery, mở widget chat
2. **Kịch bản 7** — Chitchat: warm-up, cho thấy bot hoạt động
3. **Kịch bản 1** — FAQ: retrieval cơ bản
4. **Kịch bản 2** — Catalog Search: hybrid search + history context
5. **Kịch bản 3** — Product Recommend: promotion reranking
6. **Kịch bản 4** — Policy: normalizer + citation
7. **Kịch bản 5** — Order Status: action agent + MySQL
8. **Kịch bản 8** — Handoff: escalation + Telegram (cao trào)
9. **Kịch bản 11** — Demo Fallback: fault-tolerance (kết thúc ấn tượng)

Bỏ qua kịch bản 6 (order create) và 10 (retry) nếu hết thời gian — giải thích bằng slide.

---

## Xử lý sự cố trong demo

| Sự cố | Xử lý |
|-------|-------|
| Gemini quota hết | Chuyển `ENGINE=demo`, giải thích fallback mechanism |
| Website Render cold start | Chờ 30s, giải thích free tier spin-down |
| Bot trả lời sai intent | Giải thích đây là LLM, có confidence score, nếu thấp sẽ handoff |
| MySQL connection lỗi | Demo kịch bản không cần DB (FAQ, catalog, chitchat) |
| Telegram không nhận notify | Check bot token/chat ID, demo ticket trong DB thay thế |
