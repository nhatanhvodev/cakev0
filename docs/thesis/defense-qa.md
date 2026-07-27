# Chuẩn bị Q&A bảo vệ khóa luận

> Câu hỏi dự kiến từ hội đồng, đồng bộ với codebase hiện tại.
> Khi trình bày kết quả định lượng, chỉ nêu số liệu sau khi đã chạy `ai-service/eval/run_eval.py` và `ai-service/eval/analyze.py`.

---

## A. Research Questions

### RQ1: Multi-agent khác gì so với RAG đơn giản?

BaselineEngine dùng một pipeline RAG chung: truy xuất đồng thời `faq`, `policies`, `products`, sau đó đưa toàn bộ tài liệu vào một prompt sinh câu trả lời.

MultiAgentEngine tách bài toán thành các node trong LangGraph: normalizer, router, retrieval, action, chitchat, handoff và aggregate. Router phân loại intent trước, nhờ đó retrieval chỉ truy xuất collection phù hợp hoặc action node gọi trực tiếp CSDL/API nghiệp vụ.

Dẫn chứng code:

- `ai-service/app/engines/baseline.py` - `BaselineEngine._retrieve()`
- `ai-service/app/engines/multiagent/graph.py` - `build_graph()`
- `ai-service/app/engines/multiagent/router.py` - `INTENTS`, `classify_intent()`

### RQ2: Multi-agent có làm câu trả lời grounded hơn không?

Về thiết kế, có. Cả Baseline và Multi-agent đều yêu cầu LLM trả `sources[]` và chỉ giữ citation nếu source nằm trong tài liệu đã truy xuất. Điểm khác là Baseline search cả ba collection cùng lúc, còn Multi-agent search theo intent: sản phẩm, chính sách hoặc FAQ. Điều này giảm nhiễu nguồn và giúp câu trả lời bám đúng domain nhỏ hơn.

Dẫn chứng code:

- `baseline.py` - kiểm tra `sources` khớp retrieved docs.
- `retrieval.py` - map intent sang collection bằng `_COLLECTION`.
- `vector_store.py` - ChromaDB + BM25 + RRF.

### RQ3: Handoff hoạt động như thế nào?

Hệ thống có hai lớp chuyển người thật:

- Direct handoff: intent `complaint` hoặc `handoff_request` đi vào `handoff_node()`, tạo `support_tickets`, sinh draft trả lời cho nhân viên nếu cần và gửi Telegram nếu có cấu hình.
- Low-confidence/retry handoff: retrieval thất bại sau số lần retry tối đa thì đặt `should_handoff=true`; API cập nhật trạng thái phiên để admin tiếp nhận.

Dẫn chứng code:

- `multiagent/handoff.py` - `decide_handoff()`, `handoff_node()`.
- `multiagent/retrieval.py` - max retry tạo tín hiệu `should_handoff`.
- `api/chat.py` - nếu `reply.handoff` thì cập nhật trạng thái session.
- `db/ticket_repo.py` - tạo ticket hỗ trợ.

### RQ4: Multi-agent có tăng độ trễ không?

Có thể tăng, vì có thêm bước router và đôi khi có bước rewrite/retry. Tuy nhiên code đã có keyword-first router: nếu query khớp từ khóa, hệ thống bỏ qua LLM router và trả intent ngay với confidence 0.55.

Độ trễ không nên trả lời bằng số ước đoán nếu chưa chạy eval. Repo hiện có sẵn công cụ đo:

- `eval/run_eval.py` ghi `latency_ms` từng turn.
- `eval/analyze.py` tính latency trung bình, p95 và Wilcoxon signed-rank cho latency theo cặp baseline/multiagent.

### RQ5: Tiền xử lý tiếng Việt đang làm gì?

Normalizer hiện là rule-based dictionary, tập trung vào teencode và từ viết tắt thường gặp, ví dụ `ko -> không`, `sn -> sinh nhật`, `ship -> giao hàng`, `sdt -> số điện thoại`. Hệ thống chưa có phục hồi dấu tổng quát bằng mô hình NLP riêng.

Dẫn chứng code:

- `ai-service/app/nlp/normalizer.py`
- `ai-service/app/nlp/teencode.json`
- `Settings.enable_normalizer`

---

## B. Kiến trúc và công nghệ

### Tại sao chọn LangGraph?

Bài toán CSKH có nhiều nhánh xử lý: hỏi FAQ, tìm sản phẩm, tra cứu đơn, tạo đơn, khiếu nại và trò chuyện thường. LangGraph phù hợp vì có state graph, conditional edges và retry cycle. Trong code, mọi nhánh đều hội tụ về `aggregate` để trả một `EngineReply` thống nhất cho API.

### LLM provider hiện tại là gì?

Code hiện hỗ trợ hai provider qua `LLM_PROVIDER`: `deepseek` và `gemini`.

Trạng thái hiện tại:

- `app/config.py` default: `llm_provider=deepseek`, `llm_model=deepseek-v4-flash`.
- `render.yaml`: production AI service cũng cấu hình `LLM_PROVIDER=deepseek`.
- Embedding vẫn dùng Gemini qua `embedding_model=gemini-embedding-001`.

Vì vậy khi bảo vệ nên nói: hệ thống tách `LLMClient` để thay provider theo cấu hình; triển khai hiện tại dùng DeepSeek cho chat model và Gemini cho embedding.

### Tại sao dùng ChromaDB + BM25?

ChromaDB xử lý tìm kiếm ngữ nghĩa, hữu ích khi khách hỏi theo nhu cầu. BM25 bắt từ khóa chính xác như tên bánh, VNPAY, COD, gluten. Kết quả hai phía được hợp nhất bằng Reciprocal Rank Fusion, không cần chuẩn hóa score giữa cosine distance và BM25 score.

Dẫn chứng code: `ai-service/app/knowledge/vector_store.py`.

### Hệ thống dùng những API chính nào?

FastAPI:

- `POST /chat/send`
- `GET /chat/history`
- `POST /chat/handoff`
- `GET /admin/sessions`
- `POST /admin/session-action`
- `POST /admin/reply`
- `POST /knowledge/index`
- `GET /health`
- `GET|POST /channels/messenger/webhook`

PHP proxy:

- `/cakev0/api/chat/send`
- `/cakev0/api/chat/history`
- `/cakev0/api/chat/sessions`
- `/cakev0/api/chat/session_action`
- `/cakev0/api/chat/agent_reply`

Internal order API:

- `/cakev0/api/internal/orders/create.php`, xác thực bằng HMAC.

---

## C. Kỹ thuật deep-dive

### Router có bao nhiêu intent?

Router hiện có 20 intent:

`faq`, `catalog_search`, `product_recommend`, `promotion`, `bestseller`, `order_status`, `order_create`, `policy_shipping`, `policy_payment`, `policy_return`, `complaint`, `chitchat`, `handoff_request`, `coupon_inquiry`, `review_lookup`, `product_compare`, `favorite_add`, `favorite_view`, `dietary_inquiry`, `custom_cake_quote`.

### Action Agent xử lý những tác vụ nào?

Action Agent không chỉ tra cứu đơn. Code hiện xử lý:

- Tra cứu đơn hàng.
- Tạo đơn COD qua chat.
- Xem khuyến mãi và sản phẩm bán chạy.
- Hỏi mã giảm giá công khai.
- Xem đánh giá sản phẩm.
- So sánh sản phẩm.
- Thêm/xem yêu thích.
- Tư vấn theo thành phần cần tránh: trứng, sữa, gluten, hạt.
- Nhận yêu cầu báo giá bánh thiết kế riêng.

### Tạo đơn qua chat có an toàn không?

Luồng tạo đơn qua chat không ghi trực tiếp vào DB từ LLM. AI Service thu thập thông tin, sau đó gọi PHP internal API. PHP kiểm tra HMAC, validate payload, kiểm tra user, khóa dòng sản phẩm bằng `FOR UPDATE`, tạo `orders`, `order_items` và trừ tồn kho trong transaction.

Dẫn chứng:

- `ai-service/app/services/order_create_service.py`
- `includes/internal_order_api.php`
- `api/internal/orders/create.php`

### DemoEngine có hỗ trợ đầy đủ mọi intent không?

Không nên nói vậy. `DemoEngine` dùng `keyword_fallback()` để phân loại nhanh, nhưng bảng canned response hiện chỉ có một nhóm fallback cho các intent demo chính. Nếu intent mới chưa có canned response riêng, code rơi về câu FAQ mặc định. Điểm đúng để trình bày là: DemoEngine giúp widget không crash khi LLM lỗi, không phải thay thế đầy đủ MultiAgentEngine.

### Telegram notification có block response không?

Không. `notify_handoff()` gửi Telegram bằng background daemon thread. Nếu thiếu token/chat ID thì bỏ qua, không làm hỏng luồng chat chính.

---

## D. Đánh giá thực nghiệm

### Dataset hiện có gì?

`ai-service/eval/dataset/samples.jsonl` hiện có 150 mẫu hội thoại. Mỗi mẫu có:

- `messages`
- `expected_intent`
- `expected_handoff`
- `ground_truth_answer`
- `tags`

### Metrics trong code là gì?

`eval/metrics.py` tính:

- `intent_accuracy`
- `grounded_rate`
- `handoff_precision`
- `handoff_recall`
- `handoff_f1`
- `avg_first_response_ms`
- `p95_first_response_ms`
- `task_completion_rate`

`eval/analyze.py` xuất bảng so sánh baseline vs multiagent và tính Wilcoxon signed-rank cho latency lượt đầu theo sample_id.

### Có kết quả thực nghiệm trong repo chưa?

Chưa có kết quả JSONL/Markdown được commit trong `ai-service/eval/results`, ngoài README và `.gitkeep`. Vì vậy khi bảo vệ không nên nêu phần trăm cải thiện cụ thể nếu chưa chạy:

```powershell
cd ai-service
venv\Scripts\python -m eval.run_eval --engine baseline --out eval/results/baseline.jsonl
venv\Scripts\python -m eval.run_eval --engine multiagent --out eval/results/multiagent.jsonl
venv\Scripts\python -m eval.analyze --baseline eval/results/baseline.jsonl --multiagent eval/results/multiagent.jsonl --dataset eval/dataset/samples.jsonl --out eval/results/comparison.md
```

### Cohen's Kappa đã có chưa?

Code có hàm tính Cohen's Kappa trong `eval/metrics.py`, nhưng repo chưa có file CSV của hai annotator. Vì vậy nên trình bày Kappa là phương pháp/mục tiêu đánh giá nhãn thủ công, không phải kết quả đã hoàn thành.

---

## E. Thực tiễn triển khai

### Hệ thống đã deploy thế nào?

`render.yaml` hiện định nghĩa AI service `gau-bakery-ai` bằng Docker, health check `/health`, region Singapore. PHP web service được ghi chú là quản lý riêng, không nằm trong blueprint này.

Các biến production quan trọng trong `render.yaml`:

- `ENGINE=multiagent`
- `LLM_PROVIDER=deepseek`
- `LLM_MODEL=deepseek-v4-flash`
- `EMBEDDING_MODEL=gemini-embedding-001`
- `MYSQL_SSL=true`
- `INTERNAL_ORDER_API_URL=https://cake-i8l0.onrender.com/cakev0/api/internal/orders/create.php`
- `CORS_ORIGINS=https://cake-i8l0.onrender.com`

### Có production-ready hoàn toàn chưa?

Nên trả lời thận trọng: hệ thống đã có kiến trúc deploy được và các cơ chế bảo vệ cơ bản, nhưng vẫn cần hardening nếu vận hành thật.

Đã có:

- Rate limit `/chat/send`.
- CORS cấu hình theo origin.
- HMAC cho internal order API.
- Admin bypass header có HMAC timestamp.
- Handoff người thật.
- Health check và auto reindex best-effort khi Chroma trống.

Cần cải thiện:

- Lưu trữ Chroma bền vững trên production nếu dùng free-tier mất disk.
- Giám sát log/metrics.
- Streaming response hoặc WebSocket nếu cần UX realtime hơn.
- Bộ policy an toàn AI rõ hơn cho dữ liệu cá nhân và khiếu nại.
- Kết quả eval định lượng sau khi chạy đủ baseline/multiagent.

### Chi phí vận hành nên trình bày thế nào?

Không nên cam kết `$0/tháng` nếu đang dùng DeepSeek/Gemini/Render/Aiven theo quota thay đổi. Nên nói: chi phí phụ thuộc tier API và hạ tầng; đề tài thiết kế theo hướng cấu hình provider để dễ thay đổi giữa free tier, paid tier hoặc self-hosted.

### SME khác có tái sử dụng được không?

Có thể tái sử dụng kiến trúc, nhưng cần thay:

- Knowledge base: sản phẩm, chính sách, FAQ.
- Schema/order API nếu domain khác không dùng bảng `banh`, `orders`, `order_items`.
- Router prompt và teencode dictionary theo domain.
- Handoff workflow theo đội CSKH thực tế.

### Hệ thống đã có gửi mail qua Resend chưa?

Có. Code hiện hỗ trợ ba driver mail: `smtp`, `gmail_api`, `resend`. Driver được chọn bằng `MAIL_DRIVER`. Nếu đặt `MAIL_DRIVER=resend`, hệ thống dùng `RESEND_API_KEY` và `MAIL_FROM_ADDRESS` để gọi Resend API.

Các luồng đang dùng mail chung:

- Gửi email xác thực khi đăng ký.
- Gửi phản hồi yêu cầu liên hệ từ admin.
- Gửi thông báo liên quan đến yêu cầu đặt lại mật khẩu.
- Gửi hóa đơn PDF sau khi đơn được xác nhận hoặc thanh toán thành công.

Điểm cần nói rõ khi bảo vệ: Resend đã có ở code, nhưng môi trường chạy thực tế phải cấu hình đúng biến môi trường. Nếu `.env` vẫn để `MAIL_DRIVER=gmail_api`, hệ thống sẽ chạy theo Gmail API chứ không dùng Resend.

### Vì sao cần `invoice_email_sent_at`?

Đây là cờ chống gửi trùng hóa đơn. Khi `send_order_invoice_email()` gửi email thành công, hệ thống cập nhật `orders.invoice_email_sent_at`. Những lần cập nhật trạng thái hoặc callback thanh toán sau đó sẽ kiểm tra cờ này để tránh gửi lại cùng một hóa đơn nhiều lần.

### Resend còn hạn chế gì?

Nên nêu thận trọng:

- README cần bổ sung hướng dẫn cấu hình Resend.
- Chưa có test mock riêng cho payload và response của Resend API.
- Chưa có webhook để theo dõi delivered/bounced/complained.
- Nếu muốn demo thật, cần domain/from address đã được xác minh trong Resend dashboard.

---

## F. Hạn chế nên chủ động nêu

| Hạn chế | Cách nói khi bảo vệ |
|---|---|
| Chưa có kết quả eval commit | Repo đã có dataset 150 mẫu và script đo, cần chạy thực nghiệm trước ngày bảo vệ để lấy số liệu. |
| Normalizer còn rule-based | Đủ cho teencode phổ biến, chưa phải mô hình phục hồi dấu tổng quát. |
| DemoEngine chỉ fallback nhóm intent chính | Dùng để chống crash demo, không thay thế engine thật. |
| Handoff retry không luôn tạo ticket | Direct complaint/handoff tạo ticket; low-confidence/retry đánh dấu phiên để admin tiếp nhận. |
| Phụ thuộc provider LLM/embedding | Có thể đổi provider qua config, nhưng production vẫn cần quản lý quota và key. |
| Resend chưa có webhook trạng thái | Code gửi mail đã có, nhưng chưa theo dõi delivered/bounced bằng webhook nên phần vận hành email vẫn cần hoàn thiện. |
