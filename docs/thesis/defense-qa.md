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

ChromaDB xử lý tìm kiếm ngữ nghĩa, hữu ích khi khách hỏi theo nhu cầu. BM25 bắt từ khóa chính xác như tên bánh, SePay, COD, gluten. Kết quả hai phía được hợp nhất bằng Reciprocal Rank Fusion, không cần chuẩn hóa score giữa cosine distance và BM25 score.

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

Các luồng đang dùng mail chung của ứng dụng:

- Gửi phản hồi yêu cầu liên hệ từ admin.
- Gửi thông báo trạng thái đơn cho khách.
- Gửi hóa đơn PDF sau khi đơn được xác nhận hoặc thanh toán thành công.

Lưu ý: email **xác minh tài khoản** và **đặt lại mật khẩu** nay do **Auth0** gửi qua email provider của tenant (Resend), không đi qua mailer của ứng dụng.

Điểm cần nói rõ khi bảo vệ: Resend đã có ở code, nhưng môi trường chạy thực tế phải cấu hình đúng biến môi trường. Nếu `.env` vẫn để `MAIL_DRIVER=gmail_api`, hệ thống sẽ chạy theo Gmail API chứ không dùng Resend.

### Hệ thống xác thực người dùng thế nào?

Xác thực do **Auth0** đảm nhận qua Universal Login (chuẩn OIDC). Ứng dụng không tự lưu mật khẩu: `pages/auth/login.php` chuyển hướng sang Auth0, `pages/auth/callback.php` đổi mã và tạo phiên, `pages/auth/logout.php` đăng xuất cả hai phía. Auth0 giữ credential, áp chính sách mật khẩu, phát hiện mật khẩu rò rỉ, gửi email xác minh và đặt lại mật khẩu. Bảng `users` liên kết với tài khoản Auth0 qua cột `auth0_id`; quyền admin đọc từ claim role của Auth0.

Dẫn chứng code: `includes/auth0.php`, `includes/auth_bridge.php` (`sync_session_from_auth0()`), `includes/auth0_management.php`.

### Đăng ký xong có bắt buộc xác minh email không?

Có. `pages/auth/callback.php` kiểm tra claim `email_verified`; nếu chưa xác minh thì không tạo phiên và đưa người dùng sang `pages/auth/verify-notice.php` (có nút gửi lại email xác minh qua Auth0 Management API). Tài khoản đăng nhập bằng Google được Auth0 đánh dấu đã xác minh nên không bị chặn.

### Hệ thống hỗ trợ những hình thức thanh toán nào?

COD và **SePay VietQR**. Khi checkout, hệ thống sinh mã QR SePay; webhook `api/sepay/webhook.php` tự chuyển đơn sang `paid` khi SePay ghi nhận giao dịch có nội dung `DH<order_id>` (xác thực bằng `SEPAY_WEBHOOK_API_KEY`). Bản trước dùng VNPAY và chuyển khoản ngân hàng thủ công đã được thay bằng SePay để tự động đối soát.

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

---

## G. Framework, mock API và tích hợp PHP ↔ Python

### Web PHP đang dùng framework nào?

Phần website **không dùng full-stack framework như Laravel/Symfony**. Đây là ứng dụng PHP theo kiểu server-rendered/traditional PHP, tổ chức bằng các file `pages/`, `api/`, `includes/`, `config/` và dùng Composer để kéo các thư viện cần thiết.

`composer.json` hiện có các package chính như `auth0/auth0-php`, `phpmailer/phpmailer`, `dompdf/dompdf`, `phpoffice/phpspreadsheet` và `hernol/uploadthing-php`. `config/bootstrap.php` tự nạp Composer autoloader và tự đọc `.env`/`.env.local`.

Cách trả lời ngắn khi bảo vệ: **PHP là core web/business application, không dùng MVC framework lớn; framework chính nằm ở AI service Python.**

Dẫn chứng code:

- `composer.json`
- `config/bootstrap.php`
- cấu trúc `pages/`, `api/`, `includes/`, `admin/`

### AI CSKH Python đang dùng framework nào?

AI service dùng **FastAPI** làm HTTP API, **Uvicorn** làm ASGI server, **Pydantic** để validate request/config, **LangGraph** để điều phối multi-agent, **LangChain** làm lớp tích hợp LLM/embedding, **ChromaDB + BM25** cho retrieval và **PyMySQL** để truy cập MySQL.

Dẫn chứng:

- `ai-service/requirements.txt`
- `ai-service/pyproject.toml`
- `ai-service/app/main.py`
- `ai-service/app/engines/multiagent/graph.py`

### Tại sao không viết toàn bộ AI bằng PHP?

Có thể gọi LLM từ PHP, nhưng hệ sinh thái Python mạnh hơn cho LangGraph, LangChain, ChromaDB, embedding, evaluation và scientific metrics. Vì vậy codebase giữ PHP cho website/e-commerce và tách AI thành service Python riêng.

Lợi ích của cách tách này:

- Không phải rewrite website PHP hiện có.
- AI có thể deploy/restart/scale độc lập.
- Có thể thay engine baseline/multiagent/demo mà không thay frontend.
- PHP giữ quyền kiểm soát các nghiệp vụ nhạy cảm như xác thực phiên và tạo đơn.

### Luồng một tin nhắn từ trình duyệt tới AI đi như thế nào?

Luồng web hiện tại là:

`gau-chat-widget.js` → `POST /cakev0/api/chat/send.php` → PHP proxy dùng cURL → `POST {AI_SERVICE_URL}/chat/send` → FastAPI → engine → DB/LLM/RAG → FastAPI trả JSON → PHP trả nguyên JSON → widget render câu trả lời.

Trong widget, `API` được đặt là `/cakev0/api/chat`; hàm `send()` gọi `send.php`. PHP `send.php` không tự sinh câu trả lời mà xây payload an toàn rồi forward sang FastAPI.

Dẫn chứng:

- `assets/js/gau-chat-widget.js` - `send()`
- `api/chat/send.php`
- `includes/chat_proxy_helpers.php`
- `ai-service/app/api/chat.py` - `chat_send()`

### Trình duyệt có gọi thẳng FastAPI không?

Không trong luồng chat website chính. Browser gọi endpoint PHP cùng origin; PHP mới gọi FastAPI server-to-server.

Cách này có ba lợi ích chính:

1. Browser không cần biết URL nội bộ của AI service.
2. PHP lấy danh tính từ session server-side và ký HMAC trước khi gửi sang Python.
3. Frontend không thể tự ý chèn `user_id` để đọc dữ liệu của tài khoản khác.

### PHP biết địa chỉ AI service bằng cách nào?

Qua biến môi trường `AI_SERVICE_URL`. Hàm `chat_ai_service_url()` đọc biến này và fallback về `http://localhost:8000` khi chạy local ngoài Docker.

Trong Docker Compose:

- PHP `app`: `AI_SERVICE_URL=http://ai-service:8000`
- Tên `ai-service` chính là DNS service name trong Docker network `bakery-net`.

Do đó container PHP không gọi `localhost:8000`; nó gọi container Python qua service name.

Dẫn chứng:

- `includes/chat_proxy_helpers.php`
- `docker-compose.yml`

### Hai service PHP và Python kết nối nhau trong Docker ra sao?

Cả `app`, `db`, `phpmyadmin` và `ai-service` cùng nằm trong Docker bridge network `bakery-net`. Docker DNS phân giải tên service:

- PHP → AI: `http://ai-service:8000`
- AI → PHP internal order API: `http://app/cakev0/api/internal/orders/create.php`
- PHP/Python → MySQL: host `db`, port `3306`

Đây là service-to-service communication trong private Docker network; port map `8080:80`, `8000:8000`, `3307:3306` chủ yếu để máy host truy cập khi development.

### PHP và Python có dùng chung database không?

Có. Cả hai cùng kết nối MySQL `banh_store`.

Python dùng DB cho chat session, message, ticket, đọc dữ liệu phục vụ agent. PHP vẫn là application chính cho user, sản phẩm, đơn hàng, admin và các nghiệp vụ web.

Điểm quan trọng khi trình bày: **dùng chung DB không có nghĩa AI được quyền tự ý sửa mọi bảng**. Với nghiệp vụ tạo đơn, AI gọi internal PHP API thay vì để LLM trực tiếp ghi transaction đơn hàng.

### Vì sao tạo đơn lại đi Python → PHP một lần nữa?

Đây là boundary nghiệp vụ có chủ đích. Python thu thập/hiểu ý người dùng, nhưng khi đủ dữ liệu thì gọi `INTERNAL_ORDER_API_URL`. PHP chịu trách nhiệm validate dữ liệu, xác thực HMAC, kiểm tra user/sản phẩm, transaction và tồn kho.

Luồng rút gọn:

`Chat → FastAPI Action Agent → order_create_service.py → PHP internal order API → MySQL transaction`.

Nhờ vậy LLM không trở thành nguồn quyết định trực tiếp cho các thao tác tài chính/tồn kho.

### Danh tính user được truyền từ PHP sang Python như thế nào?

PHP không tin `user_id` do JavaScript gửi lên. `send.php` lấy `$_SESSION['user_id']`, sau đó tạo header:

`X-User-Identity: <timestamp>:<user_id>:<hmac>`

HMAC được tính bằng `INTERNAL_API_SECRET`. FastAPI `_verify_user_identity()` kiểm tra timestamp, chữ ký và chỉ dùng `user_id` đã ký làm `trusted_uid`; trường `req.user_id` trong body không được tin cậy cho quyết định truy cập dữ liệu.

Đây là điểm bảo mật rất nên nhấn mạnh khi bảo vệ.

Dẫn chứng:

- `includes/chat_proxy_helpers.php` - `chat_user_identity_header()`
- `api/chat/send.php`
- `ai-service/app/api/chat.py` - `_verify_user_identity()`, `chat_send()`

### Khách chưa đăng nhập được định danh thế nào?

Widget tạo một `guest_token` ngẫu nhiên và giữ trong `localStorage.gau_chat_token`. Đồng thời `session_id` của cuộc hội thoại cũng được lưu ở `localStorage.gau_chat_session`.

PHP chỉ forward `guest_token` khi không có user đăng nhập. FastAPI dùng user đã ký hoặc guest token để scope quyền sở hữu chat session.

Dẫn chứng: `assets/js/gau-chat-widget.js`, `chat_build_forward_payload()` và `chat_history()`.

### Mock API AI trong project được thực hiện như thế nào?

Cần phân biệt ba mức:

**1. Unit test LLM:** `FakeLLM` nhận danh sách response định trước, ghi lại các call và trả kết quả tuần tự. Test không tốn quota DeepSeek/Gemini.

**2. API test:** FastAPI `TestClient` kết hợp `app.dependency_overrides[deps.get_engine]` để inject một `BaselineEngine` sử dụng `FakeLLM`, fake vector store và có thể `conn_factory=lambda: None`.

**3. Demo an toàn:** đặt `ENGINE=demo`. `DemoEngine` vẫn thử chạy `MultiAgentEngine` thật; nếu có exception từ LLM/pipeline thì mới dùng `keyword_fallback()` và canned response.

Dẫn chứng:

- `ai-service/app/llm.py` - `FakeLLM`
- `ai-service/tests/test_chat_api.py`
- `ai-service/app/engines/demo.py`
- `ai-service/app/deps.py`

### `ENGINE=demo` có phải mock hoàn toàn AI API không?

Không. Đây là **fallback wrapper**, không phải pure mock. `DemoEngine` khởi tạo `MultiAgentEngine` và ưu tiên chạy luồng thật. Chỉ khi luồng thật ném exception mới trả canned response theo keyword intent.

Vì vậy khi bảo vệ nên nói: `ENGINE=demo` là chế độ demo-safe để giảm nguy cơ lỗi quota/provider, còn unit test mới dùng mock deterministic bằng `FakeLLM`.

### PHP mock AI Python như thế nào trong test?

Repo hiện **không có một fake FastAPI HTTP server riêng** để PHP gọi vào trong unit test. Test PHP hiện có `tests/chat_proxy_helpers_test.php`, tập trung kiểm tra boundary trước khi forward:

- payload chỉ giữ field cho phép;
- `session_id` được cast sang int;
- `user_id` lấy từ authenticated session, không lấy từ client;
- field lạ bị loại;
- kiểm tra CSRF helper.

Khi test end-to-end PHP ↔ Python, hệ thống dùng `AI_SERVICE_URL` trỏ tới AI service thật/local và có thể bật `ENGINE=demo` để giảm phụ thuộc provider.

### Nếu hội đồng hỏi “mock endpoint AI mà không gọi provider thật” thì trả lời sao?

Câu trả lời chuẩn theo code hiện tại:

> Ở tầng Python, em mock tại dependency/LLM layer bằng `FakeLLM` và FastAPI `dependency_overrides`, nên endpoint `/chat/send` vẫn được test thật nhưng không gọi DeepSeek/Gemini. Ở tầng PHP, repo hiện kiểm thử payload/proxy helper; chưa xây một HTTP stub server riêng. Khi demo tích hợp, em dùng `ENGINE=demo` hoặc AI service local.

Không nên nói project đã có WireMock/MockServer/fake HTTP service vì code hiện không có.

### Nếu DeepSeek lỗi thì hệ thống có tự chuyển sang Gemini không?

Không tự động. `build_llm_client()` chọn provider theo cấu hình. `DeepSeekClient.generate()` và `GeminiClient.generate()` hiện retry cùng provider một lần sau khoảng nghỉ, nhưng không có cross-provider failover DeepSeek → Gemini.

Nếu `ENGINE=demo`, exception sau đó có thể được `DemoEngine` bắt và trả canned response. Nếu `ENGINE=multiagent`, lỗi provider vẫn có thể làm request thất bại.

Dẫn chứng: `ai-service/app/llm.py`, `ai-service/app/engines/demo.py`.

### Hệ thống chọn Baseline, Demo hay Multi-agent ở đâu?

Trong `ai-service/app/deps.py`:

- `ENGINE=baseline` → `BaselineEngine`
- `ENGINE=demo` → `DemoEngine`
- giá trị khác/default → `MultiAgentEngine`

Điểm này giúp cùng một API contract `/chat/send` nhưng thay engine phía sau mà frontend/PHP không cần đổi.

### Vì sao FastAPI dùng dependency injection cho engine?

`engine=Depends(deps_mod.get_engine)` giúp tách HTTP API khỏi implementation AI. Production inject engine theo config; test có thể override dependency bằng fake engine.

Đây là lý do `test_chat_api.py` có thể test routing/JSON contract mà không cần LLM thật hoặc MySQL thật.

### Khi AI service không kết nối được thì web xử lý thế nào?

`api/chat/send.php` dùng cURL timeout 30 giây. Nếu `curl_exec()` thất bại, PHP trả HTTP `502` cùng fallback message `Hệ thống chat đang bận...`.

Ở browser, `send()` có `try/catch`; nếu fetch/JSON lỗi thì widget hiển thị `Không kết nối được, thử lại sau nhé.`.

Đây là graceful degradation ở hai lớp: server proxy và UI.

### Chat hiện có streaming/WebSocket không?

Không. Luồng gửi message là synchronous HTTP request/response. Admin reply được widget phát hiện bằng polling history mỗi 4 giây.

Nếu hội đồng hỏi hướng nâng cấp: có thể dùng SSE/WebSocket để stream token và push human-agent reply realtime, nhưng đó chưa phải implementation hiện tại.

Dẫn chứng: `gau-chat-widget.js` - `setInterval(poll, 4000)`.

---

## H. Câu hỏi phản biện theo luồng codebase

### Một request `/chat/send` được xử lý bên Python theo thứ tự nào?

1. FastAPI middleware kiểm tra rate limit.
2. Pydantic parse `ChatSendRequest`.
3. `_verify_user_identity()` xác minh HMAC từ PHP nếu user đăng nhập.
4. `chat_repo.get_or_create_session()` lấy/tạo session.
5. Đọc history và lưu message của customer.
6. Gọi `engine.handle(history, message, context)`.
7. Engine chạy graph hoặc baseline/demo.
8. Lưu bot reply, metadata, intent và trạng thái handoff.
9. Trả `session_id`, `reply`, `handoff` cho PHP.

Dẫn chứng chính: `ai-service/app/main.py`, `ai-service/app/api/chat.py`.

### Multi-agent graph chạy theo flow nào?

Entry point là `normalize`, sau đó `router`. Router chia request thành bốn nhóm:

- retrieval;
- action;
- chitchat;
- handoff.

Retrieval có thể đi qua `rewrite` rồi quay lại retrieval khi `needs_retry=true`; các nhánh cuối cùng đều hội tụ tại `aggregate` rồi `END`.

Dẫn chứng: `ai-service/app/engines/multiagent/graph.py` - `build_graph()`.

### Retrieval retry tối đa bao nhiêu lần?

`after_retrieval()` chỉ chuyển sang `rewrite` khi `retry_count < 2`. Mỗi lần rewrite tăng `retry_count` thêm 1. Vì vậy graph không retry vô hạn.

Điểm thiết kế: giới hạn vòng lặp để kiểm soát latency/cost và sau đó aggregate/handoff theo tín hiệu của retrieval node.

### Vì sao `order_create` và `custom_cake_quote` được “pin intent”?

Đây là hai flow multi-turn. Khi người dùng đang nhập dở đơn hoặc yêu cầu bánh custom, câu tiếp theo như “2 cái”, “quận 7”, “thứ bảy” rất khó phân loại độc lập.

`MultiAgentEngine.handle()` đọc metadata session; nếu draft đang mở thì gắn trước intent `order_create` hoặc `custom_cake_quote` với confidence 1.0. Router giữ nguyên các `PINNED_INTENTS` cho tới khi flow hoàn tất hoặc user dùng từ khóa hủy/dừng.

Dẫn chứng: `graph.py` - `PINNED_INTENTS`, `_open_draft()`, `_open_custom_quote()`, `_has_exit_word()`.

### History hội thoại được lưu ở frontend hay backend?

Cả hai có state khác nhau:

- Frontend chỉ giữ `guest_token` và `session_id` trong localStorage để reconnect.
- Nội dung message chính được backend lưu trong `chat_sessions`/`chat_messages` ở MySQL.

Khi reload/trang khác, widget gọi `history.php`, render lại lịch sử và tiếp tục session cũ.

### Tại sao không lưu toàn bộ history trong localStorage?

Vì localStorage nằm ở client, dễ bị sửa/xóa và không phù hợp làm nguồn sự thật cho handoff/admin/audit. Backend cần history chung để AI lấy context và để nhân viên CSKH xem cùng một cuộc hội thoại.

LocalStorage chỉ giữ khóa nhận diện session phía browser.

### Khi admin trả lời, khách nhận message bằng cách nào?

Widget gọi `poll()` mỗi 4 giây vào `history.php`. Nó chỉ render thêm message mới có `sender === 'agent'` và `id > lastMsgId`.

Vì vậy human handoff hiện hoạt động theo polling, không cần WebSocket.

Dẫn chứng: `assets/js/gau-chat-widget.js` - `poll()`.

### FastAPI rate limit đang hoạt động thế nào?

Middleware trong `app/main.py` giới hạn `/chat/send` ở mức 20 request/60 giây theo ưu tiên key:

1. `session_id`;
2. `guest_token`;
3. IP (`x-forwarded-for` hoặc client IP).

State rate limit nằm trong memory của process Python.

### Hạn chế của rate limit hiện tại là gì?

Vì state là dictionary trong memory, nó không chia sẻ giữa nhiều worker/instance và bị reset khi process restart. Nếu scale ngang production, nên chuyển sang Redis hoặc rate limiter ở reverse proxy/API gateway.

Đây là câu trả lời tốt nếu hội đồng hỏi “production scale thì sao?”.

### CORS có vai trò gì nếu browser đã gọi PHP proxy?

Đối với widget website chính, browser gọi cùng origin PHP nên CORS của FastAPI không phải lớp chính của request đó. Tuy nhiên AI service vẫn có CORS config để kiểm soát trường hợp client khác cần gọi trực tiếp và để deployment không mặc định mở origin không cần thiết.

Trong production, `CORS_ORIGINS` được cấu hình theo origin website.

### Vì sao có cả HMAC user identity và HMAC admin bypass?

Hai header giải quyết hai trust boundary khác nhau:

- `X-User-Identity`: chứng minh request thuộc user đang đăng nhập ở PHP.
- `X-Admin-Bypass`: chứng minh request proxy từ luồng admin hợp lệ có quyền xem/điều khiển session CSKH.

Cả hai dùng timestamp + HMAC để tránh client tự giả mạo và giảm replay window.

### Nếu client tự gửi `user_id=1` tới FastAPI thì sao?

`chat_send()` không dùng `req.user_id` làm danh tính tin cậy. Nó gọi `_verify_user_identity(x_user_identity)` và dùng kết quả đó làm `trusted_uid`. Không có header hợp lệ thì request được xử lý như guest.

Đây là fix quan trọng chống IDOR/spoof user qua body.

### Tại sao PHP proxy lọc lại payload thay vì forward nguyên JSON?

`chat_build_forward_payload()` chỉ tạo payload mới từ các field cho phép. Nó loại field lạ và lấy `user_id` từ session đã xác thực.

Test `tests/chat_proxy_helpers_test.php` còn kiểm tra trực tiếp tình huống client gửi `user_id=999` và field `evil`; output vẫn dùng authenticated user id và bỏ field lạ.

### DB chat và DB nghiệp vụ có transaction giống nhau không?

Không phải mọi thao tác đều cùng một transaction xuyên PHP và Python. Chat session/message được quản lý bên Python repository. Nghiệp vụ tạo đơn được đóng gói trong transaction của PHP internal order API.

Không có distributed transaction giữa hai service; thay vào đó hệ thống phân boundary để mỗi nghiệp vụ quan trọng có transaction local rõ ràng.

### Nếu Python DB unavailable thì test API vẫn chạy được vì sao?

Trong test, `EngineDeps` có thể dùng `conn_factory=lambda: None`. `chat_send()` khi không có connection vẫn gọi engine và trả response, với `session_id=0`; history trả danh sách rỗng.

Đây là kỹ thuật test isolation, không phải mô tả rằng production luôn bỏ qua lỗi DB.

Dẫn chứng: `ai-service/tests/test_chat_api.py`.

### ChromaDB mất dữ liệu khi container restart thì sao?

`app/main.py` có startup hook `_auto_reindex_if_empty()`. Nếu collection `products` trống, service best-effort rebuild index từ nguồn dữ liệu.

Trong Docker Compose local còn mount volume `ai_chroma_data`; còn free-tier deployment không có persistent disk thì startup reindex là cơ chế phục hồi.

### Vì sao auto-reindex được viết “best-effort”?

Startup AI service không nên fail hoàn toàn chỉ vì reindex lỗi tạm thời. Hook bắt exception và log `auto-reindex skipped`; health endpoint vẫn có thể phản ánh số product indexed qua `_safe_count()`.

Trade-off: service lên được nhưng retrieval có thể suy giảm cho tới khi index được phục hồi.

### LLM response có được trả thẳng cho người dùng không?

Không phải mọi nhánh đều đơn giản “prompt → text”. Multi-agent có router, retrieval/action/handoff và aggregate. Với retrieval, citation được lọc theo tài liệu đã lấy; với action, response có thể chứa `products`, `order` hoặc type đặc biệt.

API chuẩn hóa kết quả bằng `EngineReply`, sau đó `chat_send()` lưu metadata và trả JSON cho widget.

### Vì sao frontend không cần biết đang chạy Baseline hay Multi-agent?

Vì cả các engine đều implement contract `handle(history, user_message, context) -> EngineReply`, còn FastAPI giữ contract HTTP cố định.

Đây là abstraction quan trọng: thay engine phục vụ nghiên cứu/evaluation mà không sửa PHP và JavaScript.

### Nếu muốn thay DeepSeek bằng provider khác thì sửa chỗ nào?

Thiết kế hiện tại đã có `LLMClient` protocol và `build_llm_client(settings)`. Để thêm provider mới, nên tạo client mới implement `generate(system, user)` rồi mở rộng factory theo config.

Phần router/graph/retrieval không nên phụ thuộc trực tiếp SDK của provider.

### Điểm coupling lớn nhất giữa PHP và Python là gì?

Các coupling chính là:

- HTTP contract của chat/admin/internal order API;
- HMAC secret/header format;
- schema MySQL dùng chung;
- một số tên bảng/field nghiệp vụ;
- URL cấu hình qua environment.

Do đó nếu đổi schema hoặc API contract cần version/migration đồng bộ hai service.

### Nếu cần scale hệ thống thì scale phần nào độc lập được?

AI service có thể scale độc lập với PHP vì giao tiếp qua HTTP. Tuy nhiên trước khi scale nhiều instance AI cần xử lý các state đang nằm local như in-memory rate limit và đảm bảo Chroma/index dùng storage phù hợp hoặc được rebuild/replicate nhất quán.

PHP và MySQL cũng có bottleneck riêng; tách service giúp nhìn rõ từng điểm cần scale thay vì tăng tài nguyên cho một monolith duy nhất.

### Câu trả lời 30 giây khi hội đồng hỏi “toàn hệ thống kết nối ra sao?”

> Website chính viết bằng PHP nhận request từ người dùng. Chat widget JavaScript không gọi LLM trực tiếp mà gọi PHP proxy. PHP lấy session đăng nhập, ký danh tính bằng HMAC rồi forward request qua HTTP tới FastAPI Python. FastAPI quản lý chat session, chạy LangGraph để router sang retrieval, action, chitchat hoặc handoff; retrieval dùng ChromaDB/BM25 và LLM, còn nghiệp vụ nhạy cảm như tạo đơn được gọi ngược về internal PHP API để PHP transaction với MySQL. Kết quả quay lại FastAPI → PHP → widget. Khi chuyển người thật, admin làm việc trên cùng dữ liệu chat và widget poll history để nhận câu trả lời của nhân viên.

Đây là câu tóm tắt nên học thuộc vì bao quát đúng boundary của codebase hiện tại.
