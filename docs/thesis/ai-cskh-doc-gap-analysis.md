# Phân tích tài liệu DOCX và đề xuất bổ sung AI CSKH

Nguồn phân tích:

- DOCX gốc: `docs/Võ Lý Nhật Anh_Hệ thống quản lý đặt bánh trực tuyến_Nhóm6.docx`
- Markdown chuyển bằng MarkItDown: `docs/thesis/markitdown-docx.md`
- Codebase hiện tại đọc qua CodeGraph và kiểm tra các file liên quan trong `ai-service`, `api/chat`, `includes`, `admin`, `database/migrations`.

## 1. Kết luận nhanh

Tài liệu DOCX hiện tại đủ khung cho một đồ án môn "Phân tích và thiết kế hệ thống" của website đặt bánh trực tuyến cơ bản, nhưng chưa đủ cho khóa luận tốt nghiệp với đề tài:

> Xây dựng website thương mại điện tử tích hợp AI chăm sóc khách hàng

Trạng thái cập nhật ngày 26/07/2026: bản Markdown mới `docs/thesis/markitdown-docx.md` đã được bổ sung các nhóm chức năng hiện có trong codebase, gồm AI CSKH, chat admin, Messenger, tạo đơn qua chat, coupon, yêu thích, đánh giá, VNPAY, hóa đơn PDF, email đa driver và Resend. File này vẫn giữ vai trò checklist phân tích gap để tiếp tục hoàn thiện bản khóa luận chính thức.

Các phần hiện đã có:

- Lời mở đầu, lời cảm ơn.
- Chương 1: khảo sát hiện trạng, yêu cầu chức năng/phi chức năng, biểu mẫu phỏng vấn, mô hình hóa yêu cầu.
- Chương 2: actor, use case, mô tả use case, activity diagram, class diagram, sequence diagram.
- Chương 3: sơ đồ menu và giao diện các chức năng.
- Chương 4: kết quả, ưu/khuyết điểm, hướng phát triển.

Các phần còn thiếu hoặc yếu nếu dùng làm khóa luận:

- Chưa đổi tên, mục tiêu và phạm vi theo đề tài AI CSKH.
- Chưa có tổng quan nghiên cứu/cơ sở lý thuyết về AI chatbot, RAG, multi-agent, vector database, xử lý tiếng Việt.
- Chưa có kiến trúc hệ thống sau khi tách thêm AI service.
- Chưa có thiết kế dữ liệu cho hội thoại, ticket hỗ trợ, FAQ, tri thức truy xuất, metadata phiên chat.
- Chưa có use case, activity diagram, sequence diagram cho AI CSKH.
- Chưa có chương cài đặt, triển khai, kiểm thử và đánh giá thực nghiệm AI.
- Chưa có chỉ số đánh giá AI như intent accuracy, grounded rate, handoff precision/recall, latency, task completion.
- Chưa có phần an toàn/bảo mật riêng cho AI: chống lộ dữ liệu, giới hạn tốc độ, kiểm soát hallucination, handoff người thật.
- Chưa có tài liệu tham khảo.

## 2. Codebase hiện có gì để đưa vào báo cáo

### 2.1. Website thương mại điện tử

Codebase hiện không chỉ là bản đặt bánh cơ bản. Có thể mô tả các phân hệ sau:

| Phân hệ | Nội dung nên đưa vào báo cáo |
|---|---|
| Sản phẩm | Quản lý bánh, loại bánh, giá, hình ảnh, mô tả, slug, gallery, best selling, tồn kho. |
| Khách hàng | Đăng ký, xác thực email, đăng nhập, cập nhật tài khoản, xem lịch sử đơn. |
| Giỏ hàng và đặt hàng | Thêm/xóa/cập nhật số lượng, áp mã giảm giá, tạo đơn. |
| Thanh toán | COD, chuyển khoản/QR, VNPAY, cập nhật trạng thái thanh toán. |
| Đánh giá | Đánh giá sản phẩm, duyệt đánh giá trong admin. |
| Khuyến mãi/coupon | Bảng promotions và cart_coupons, mã WELCOME10 công khai cho AI. |
| Yêu thích | Lưu và xem sản phẩm yêu thích. |
| Hóa đơn/email | Xác thực email đăng ký, phản hồi liên hệ, thông báo yêu cầu mật khẩu, sinh hóa đơn PDF, gửi email có attachment qua SMTP/Gmail API/Resend, đánh dấu `invoice_email_sent_at` để hạn chế gửi trùng. |
| Quản trị | Dashboard, quản lý sản phẩm, khách hàng, đơn hàng, khuyến mãi, đánh giá, báo cáo. |

### 2.2. AI CSKH đã có trong code

| Thành phần | Dẫn chứng code | Nội dung đưa vào báo cáo |
|---|---|---|
| AI service riêng | `ai-service/app/main.py`, `ai-service/app/api/chat.py` | FastAPI service xử lý chat, tách khỏi PHP backend. |
| Proxy PHP sang AI | `api/chat/send.php`, `api/chat/history.php`, `includes/chat_proxy_helpers.php` | Website gọi PHP proxy, proxy gọi AI service. |
| Chat widget | `assets/js/gau-chat-widget.js`, `assets/css/gau-chat-widget.css` | Khách chat trực tiếp trên website, có lưu session/guest token. |
| Admin chat | `admin/admin.php`, `assets/js/admin-chat.js`, `assets/css/admin-chat.css` | Nhân viên xem phiên chờ, nhận phiên, trả lời, đóng/mở lại hội thoại. |
| Engine baseline/multiagent/demo | `ai-service/app/deps.py`, `baseline.py`, `multiagent/graph.py`, `demo.py` | Có thể so sánh RAG đơn giản với multi-agent. |
| Router intent | `ai-service/app/engines/multiagent/router.py` | Phân loại 20 intent CSKH. |
| RAG hybrid | `ai-service/app/knowledge/vector_store.py`, `retrieval.py` | ChromaDB + BM25 + RRF, truy xuất products/policies/FAQ. |
| LLM provider | `ai-service/app/llm.py`, `app/config.py` | Hỗ trợ Gemini và DeepSeek; embedding dùng Gemini. |
| Xử lý tiếng Việt | `ai-service/app/nlp/normalizer.py`, `teencode.json` | Chuẩn hóa teencode/không dấu ở mức từ vựng. |
| Tra cứu đơn | `ai-service/app/db/orders_repo.py`, `multiagent/action.py` | Bot tra trạng thái đơn theo user, số điện thoại hoặc mã đơn. |
| Tạo đơn qua chat | `ai-service/app/services/order_create_service.py`, `includes/internal_order_api.php` | Slot-filling, tạo đơn COD qua internal API có HMAC. |
| Báo giá bánh thiết kế riêng | `custom_quote_service.py`, `custom_quote.py` | Thu thập dịp, số người, vị, ngày, ghi chú, tên, SĐT rồi tạo lead. |
| Handoff người thật | `multiagent/handoff.py`, `ticket_repo.py`, `notify.py` | Khiếu nại/confidence thấp/từ khóa thì tạo ticket, gửi Telegram, chuyển nhân viên. |
| Messenger | `ai-service/app/api/messenger.py` | Có webhook Facebook Messenger. |
| Đánh giá thực nghiệm | `ai-service/eval/run_eval.py`, `eval/metrics.py`, `eval/dataset/samples.jsonl` | Dataset 150 mẫu, tính intent accuracy, grounded rate, handoff metrics, latency, task completion. |

### 2.3. Mail, Resend và hóa đơn

| Thành phần | Dẫn chứng code | Nội dung đưa vào báo cáo |
|---|---|---|
| Mail driver | `includes/mailer.php` | `mail_driver()` hỗ trợ `smtp`, `gmail_api`, `resend`; nghiệp vụ gọi qua `send_custom_mail()` để không phụ thuộc trực tiếp vào provider. |
| Resend | `resend_config_missing()`, `resend_send_message()` | Cần cấu hình `MAIL_DRIVER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`; hệ thống gọi Resend API bằng JSON payload. |
| Attachment | `send_custom_mail_with_attachments()` | Resend và Gmail API đều hỗ trợ gửi email có file đính kèm; dùng cho hóa đơn PDF. |
| Hóa đơn PDF | `includes/invoice_pdf.php`, `includes/invoice_mailer.php` | Sinh hóa đơn PDF từ đơn hàng, gửi qua email và cập nhật `orders.invoice_email_sent_at` để tránh gửi trùng. |
| Diagnostic | `tools/diagnose_mail.php` | Kiểm tra driver hiện tại, biến môi trường, cURL và thử gửi email test. |
| Điểm cần hoàn thiện | README, tests, webhook | README chưa ghi hướng dẫn Resend; chưa có test mock riêng cho Resend; chưa có webhook delivered/bounced. |

## 3. Vị trí nên bổ sung vào tài liệu hiện tại

### 3.1. Trang bìa và lời mở đầu

Đổi tên đề tài từ:

> Hệ thống quản lý đặt bánh trực tuyến

thành:

> Xây dựng website thương mại điện tử tích hợp AI chăm sóc khách hàng

Nên sửa đoạn mở đầu để nhấn mạnh vấn đề CSKH:

> Bên cạnh nhu cầu đặt bánh trực tuyến, khách hàng thường cần được tư vấn về mẫu bánh, giá, kích thước, chính sách giao hàng, phương thức thanh toán và xử lý khiếu nại. Nếu cửa hàng chỉ phản hồi thủ công qua điện thoại, Zalo hoặc Facebook, thời gian chờ tăng cao, dễ bỏ sót thông tin và làm giảm tỷ lệ chốt đơn. Vì vậy, đề tài mở rộng hệ thống thương mại điện tử bằng một trợ lý AI chăm sóc khách hàng, có khả năng trả lời câu hỏi thường gặp, tư vấn sản phẩm, tra cứu đơn hàng, hỗ trợ tạo đơn và chuyển tiếp nhân viên khi cần.

### 3.2. Chương 1 - Khảo sát hiện trạng và yêu cầu hệ thống

Bổ sung vào `1.1.2. Đánh giá hiện trạng`:

- Khách hàng hỏi lặp lại nhiều câu như giờ mở cửa, phí giao hàng, VNPAY/COD, đổi trả, bánh phù hợp dịp sinh nhật.
- Nhân viên phản hồi thủ công dễ chậm vào giờ cao điểm.
- Tin nhắn từ nhiều kênh thiếu tập trung, khó theo dõi lịch sử hỗ trợ.
- Khiếu nại cần được phân loại và chuyển người thật sớm.
- Khách thiếu dấu/teencode khi chat, ví dụ "shop oi co banh sn ko", gây khó cho tìm kiếm từ khóa thông thường.

Bổ sung vào `1.2.1. Yêu cầu về chức năng`:

| Nhóm chức năng | Chức năng cần thêm |
|---|---|
| AI trả lời tự động | Trả lời FAQ, chính sách giao hàng, thanh toán, đổi trả. |
| AI tư vấn sản phẩm | Tìm bánh theo nhu cầu, gợi ý sản phẩm, ưu tiên sản phẩm khuyến mãi/bán chạy. |
| AI hỗ trợ giao dịch | Tra cứu trạng thái đơn, tạo đơn COD qua chat, báo giá bánh thiết kế riêng. |
| AI cá nhân hóa | Xem/lưu sản phẩm yêu thích, xem đánh giá sản phẩm, so sánh sản phẩm. |
| AI an toàn thực phẩm | Tư vấn bánh theo thành phần cần tránh như trứng, sữa, gluten, hạt. |
| Handoff | Chuyển hội thoại cho nhân viên khi khách khiếu nại, yêu cầu người thật hoặc bot thiếu tự tin. |
| Quản trị CSKH | Admin xem danh sách phiên chat, nhận xử lý, phản hồi, đóng/mở lại hội thoại. |
| Quản trị tri thức | Index lại FAQ, chính sách và catalog vào kho tri thức AI. |

Bổ sung vào `1.2.2. Yêu cầu phi chức năng`:

- Độ trễ phản hồi chat mục tiêu dưới 3-5 giây với truy vấn thông thường.
- Có rate limit cho `/chat/send` để tránh spam.
- Câu trả lời dựa trên tri thức đã index, hạn chế bịa thông tin ngoài catalog/chính sách.
- Có fallback hoặc handoff nếu AI không chắc chắn.
- Lưu lịch sử hội thoại để nhân viên tiếp nhận có ngữ cảnh.
- Bảo vệ API nội bộ tạo đơn bằng HMAC secret.
- CORS chỉ cho phép origin hợp lệ trong production.
- Dữ liệu nhạy cảm như số điện thoại, địa chỉ, lịch sử đơn phải được xử lý theo quyền truy cập.

Bổ sung phỏng vấn:

| Người được phỏng vấn | Câu hỏi nên thêm |
|---|---|
| Nhân viên CSKH | Những câu hỏi nào khách hỏi lặp lại nhiều nhất? Khi nào cần chuyển người thật? |
| Khách hàng | Khách có chấp nhận chatbot nếu bot trả lời nhanh, có thể chuyển nhân viên không? |
| Quản lý | Cần thống kê gì từ hội thoại: số phiên, intent phổ biến, khiếu nại, đơn tạo qua chat? |

### 3.3. Chương 2 - Phân tích hệ thống

Bổ sung actor:

- Trợ lý AI CSKH: xử lý yêu cầu chat tự động.
- Nhân viên hỗ trợ/Admin CSKH: tiếp nhận phiên handoff và trả lời khách.
- Hệ thống AI Service: module FastAPI xử lý LLM/RAG/action.
- Kênh ngoài: Facebook Messenger webhook.

Bổ sung use case:

| Use Case | Actor chính | Mô tả ngắn |
|---|---|---|
| UC-12 Tư vấn khách hàng bằng AI | Khách hàng | Khách hỏi FAQ/chính sách/sản phẩm, AI phân loại intent và trả lời. |
| UC-13 Gợi ý sản phẩm | Khách hàng | AI gợi ý bánh theo dịp, ngân sách, khẩu vị, sản phẩm bán chạy/khuyến mãi. |
| UC-14 Tra cứu đơn qua chat | Khách hàng | AI tra cứu đơn theo tài khoản, SĐT hoặc mã đơn. |
| UC-15 Tạo đơn COD qua chat | Khách hàng | AI thu thập sản phẩm, số lượng, người nhận, SĐT, địa chỉ và tạo đơn. |
| UC-16 Báo giá bánh thiết kế riêng | Khách hàng | AI thu thập yêu cầu và tạo lead để nhân viên báo giá. |
| UC-17 Chuyển nhân viên hỗ trợ | AI CSKH, Nhân viên hỗ trợ | AI tạo ticket khi có khiếu nại, yêu cầu người thật hoặc confidence thấp. |
| UC-18 Quản lý hội thoại CSKH | Nhân viên hỗ trợ | Xem phiên chờ, nhận phiên, trả lời, đóng/mở lại phiên. |
| UC-19 Cập nhật tri thức AI | Quản trị viên | Index FAQ, chính sách, catalog vào vector store. |

Mẫu mô tả use case nên thêm sau `2.4.11`:

| Thuộc tính | Nội dung |
|---|---|
| Use Case ID | UC-12 |
| Tên Use Case | Tư vấn khách hàng bằng AI |
| Tác nhân chính | Khách hàng |
| Tác nhân phụ | Trợ lý AI CSKH, AI Service, CSDL MySQL |
| Tổng quan | Khách gửi câu hỏi qua widget chat. Hệ thống lưu tin nhắn, phân loại intent, truy xuất tri thức phù hợp và trả lời bằng tiếng Việt. |
| Tiền điều kiện | Website và AI Service hoạt động; tri thức FAQ/chính sách/sản phẩm đã được index. |
| Hậu điều kiện | Câu trả lời được hiển thị cho khách; tin nhắn và intent được lưu vào lịch sử hội thoại. |
| Dòng sự kiện chính | 1. Khách mở widget chat. 2. Khách nhập câu hỏi. 3. PHP proxy chuyển yêu cầu đến AI Service. 4. AI chuẩn hóa câu tiếng Việt và phân loại intent. 5. Nếu là FAQ/chính sách/sản phẩm, AI truy xuất tri thức bằng RAG. 6. AI sinh câu trả lời và trả về website. 7. Website hiển thị câu trả lời và lưu session. |
| Dòng sự kiện phụ | Nếu confidence thấp hoặc khách khiếu nại, hệ thống tạo ticket và chuyển nhân viên hỗ trợ. Nếu AI Service lỗi, website hiển thị thông báo thử lại hoặc gọi hotline. |

Mẫu mô tả use case handoff:

| Thuộc tính | Nội dung |
|---|---|
| Use Case ID | UC-17 |
| Tên Use Case | Chuyển nhân viên hỗ trợ |
| Tác nhân chính | Trợ lý AI CSKH |
| Tác nhân phụ | Khách hàng, Nhân viên hỗ trợ |
| Tổng quan | Khi khách yêu cầu người thật, khiếu nại hoặc bot không đủ tự tin, hệ thống tạo ticket và đưa phiên vào hàng chờ admin. |
| Tiền điều kiện | Có phiên chat đang hoạt động. |
| Hậu điều kiện | Ticket hỗ trợ được tạo; nhân viên có thể nhận và trả lời khách. |
| Dòng sự kiện chính | 1. AI nhận diện intent `complaint` hoặc `handoff_request`. 2. Hệ thống ghi trạng thái phiên là `open/handoff`. 3. Tạo bản ghi `support_tickets`. 4. Gửi thông báo Telegram nếu cấu hình. 5. Admin mở tab Hội thoại, nhận phiên và trả lời. |

Bổ sung sơ đồ:

- Activity diagram cho `Tư vấn khách hàng bằng AI`.
- Activity diagram cho `Tạo đơn qua chat`.
- Activity diagram cho `Handoff`.
- Sequence diagram `Widget -> PHP Proxy -> FastAPI -> Router -> Retrieval/Action -> MySQL/ChromaDB -> Widget`.
- Sequence diagram `AI Service -> PHP Internal Order API -> MySQL` cho tạo đơn.

### 3.4. Chương 3 - Thiết kế hệ thống

Nên đổi Chương 3 từ chỉ mô tả giao diện sang:

> CHƯƠNG 3: THIẾT KẾ VÀ KIẾN TRÚC HỆ THỐNG

Cấu trúc đề xuất:

1. `3.1. Kiến trúc tổng thể`
   - PHP website.
   - AI Service FastAPI.
   - MySQL.
   - ChromaDB/BM25.
   - LLM provider.
   - Messenger/Telegram.
2. `3.2. Thiết kế cơ sở dữ liệu`
   - ERD cho ecommerce.
   - ERD cho AI CSKH.
3. `3.3. Thiết kế AI service`
   - BaselineEngine.
   - MultiAgentEngine.
   - Router/Retrieval/Action/Handoff/Aggregate.
   - Vietnamese normalizer.
4. `3.4. Thiết kế API`
   - `POST /chat/send`.
   - `GET /chat/history`.
   - `POST /chat/handoff`.
   - `GET /admin/sessions`.
   - `POST /admin/session-action`.
   - `POST /admin/reply`.
   - `POST /knowledge/index`.
   - `POST /api/internal/orders/create.php`.
5. `3.5. Thiết kế giao diện`
   - Giao diện website hiện có.
   - Chat widget khách hàng.
   - Admin hội thoại CSKH.

Bảng dữ liệu AI nên thêm:

| Bảng | Vai trò |
|---|---|
| `chat_sessions` | Lưu phiên hội thoại, user/guest token, trạng thái, intent, metadata. |
| `chat_messages` | Lưu từng tin nhắn khách, bot, agent; có content type và metadata. |
| `faq_entries` | Tri thức FAQ dùng để index. |
| `support_tickets` | Ticket khiếu nại/handoff, độ ưu tiên, draft response. |
| `chat_session_events` | Audit log khi admin nhận phiên, đóng/mở lại phiên. |
| `contact_requests` | Lead báo giá bánh thiết kế riêng. |
| `cart_coupons` | Coupon công khai cho AI trả lời, ví dụ `WELCOME10`. |
| `banh` | Bổ sung cột thành phần: `co_trung`, `co_sua`, `co_gluten`, `co_hat`. |

### 3.5. Chương mới nên thêm: Cài đặt, kiểm thử và thực nghiệm

Nếu làm khóa luận, nên tách thêm:

> CHƯƠNG 4: CÀI ĐẶT VÀ TRIỂN KHAI HỆ THỐNG

Nội dung:

- Môi trường: PHP/Apache, MySQL, Python FastAPI, Docker/Render.
- Cấu hình AI service: `ENGINE`, `LLM_PROVIDER`, `LLM_MODEL`, `GEMINI_API_KEY`, `DEEPSEEK_API_KEY`, `CHROMA_PERSIST_DIR`, `INTERNAL_API_SECRET`, `CORS_ORIGINS`.
- Quy trình chạy hệ thống bằng Docker Compose.
- Quy trình index tri thức.
- Cơ chế bảo mật API nội bộ và rate limit.

> CHƯƠNG 5: KIỂM THỬ VÀ ĐÁNH GIÁ AI CSKH

Nội dung:

| Chỉ số | Ý nghĩa |
|---|---|
| Intent accuracy | Tỷ lệ phân loại đúng intent trên dataset 150 mẫu. |
| Grounded rate | Tỷ lệ câu trả lời có citation hợp lệ từ tài liệu truy xuất. |
| Handoff precision/recall/F1 | Chất lượng quyết định chuyển nhân viên. |
| Latency trung bình/p95 | Thời gian phản hồi lượt đầu. |
| Task completion rate | Tỷ lệ hoàn thành tác vụ tạo đơn qua chat. |
| Cohen's Kappa | Độ đồng thuận giữa 2 người gán nhãn/chấm kết quả. |

Sau đó chuyển Chương 4 hiện tại thành:

> CHƯƠNG 6: TỔNG KẾT VÀ HƯỚNG PHÁT TRIỂN

## 4. Các điểm mâu thuẫn/cần sửa trong DOCX hiện tại

| Vị trí trong Markdown | Vấn đề | Đề xuất sửa |
|---|---|---|
| Dòng 234 | Tên đề tài vẫn là "Hệ Thống Quản Lý Đặt Bánh Trực Tuyến". | Đổi thành đề tài mới có AI CSKH. |
| Dòng 244 | Lời cảm ơn nhắc "hệ thống quản lý Homestay". | Sửa thành "hệ thống thương mại điện tử đặt bánh trực tuyến tích hợp AI CSKH". |
| Dòng 327 | Câu cũ mô tả hệ thống chưa có thanh toán online. | Không còn đúng vì tài liệu và code có VNPAY. Sửa thành "đã hỗ trợ COD/chuyển khoản/VNPAY, có thể mở rộng ví điện tử". |
| Dòng 449-452 | Actor chỉ có Quản lý và Khách hàng. | Thêm Nhân viên hỗ trợ, Trợ lý AI CSKH, AI Service. |
| Dòng 454-471 | Use case chưa có AI. | Thêm nhóm use case AI CSKH. |
| Dòng 725 | Mục Class diagram trong Markdown không có nội dung đọc được. | Kiểm tra DOCX gốc; nếu chỉ là hình thì thêm mô tả text và bảng lớp/thực thể. |
| Dòng 767-849 | Chương 3 chỉ là giao diện. | Bổ sung kiến trúc, CSDL, API, AI service, chat widget/admin chat. |
| Dòng 851-907 | Tổng kết chưa nhắc AI và đánh giá thực nghiệm. | Dời tổng kết xuống chương cuối; thêm chương kiểm thử/thực nghiệm trước. |

## 5. Nội dung tính năng AI nên thêm vào báo cáo

### 5.1. Năng lực chính của AI CSKH

- Trả lời câu hỏi thường gặp: giờ mở cửa, hotline, quy trình đặt bánh.
- Giải thích chính sách: giao hàng, thanh toán, đổi trả.
- Tìm kiếm sản phẩm theo tên, loại, hương vị, nhu cầu.
- Gợi ý sản phẩm theo dịp: sinh nhật, khai trương, kỷ niệm, tiệc nhỏ.
- Ưu tiên sản phẩm khuyến mãi hoặc bán chạy trong gợi ý.
- Tra cứu đơn hàng theo tài khoản, số điện thoại hoặc mã đơn.
- Tạo đơn COD qua chat với luồng hỏi từng thông tin.
- Nhận yêu cầu bánh thiết kế riêng và tạo lead cho nhân viên báo giá.
- Tư vấn mã giảm giá/coupon công khai.
- Xem đánh giá sản phẩm qua chat.
- So sánh 2 loại bánh.
- Lưu/xem sản phẩm yêu thích.
- Lọc sản phẩm theo thành phần cần tránh: trứng, sữa, gluten, hạt.
- Phát hiện khiếu nại và chuyển nhân viên.
- Quản lý hội thoại trong admin.

### 5.2. Đoạn mô tả kiến trúc có thể đưa vào báo cáo

Hệ thống được thiết kế theo kiến trúc lai giữa website thương mại điện tử PHP/MySQL và AI Service độc lập. Website chịu trách nhiệm hiển thị giao diện, quản lý sản phẩm, giỏ hàng, đơn hàng và thanh toán. AI Service được xây dựng bằng FastAPI, tiếp nhận tin nhắn từ website thông qua các API proxy của PHP. Sau khi nhận tin nhắn, AI Service lưu lịch sử hội thoại, chuẩn hóa tiếng Việt, phân loại ý định bằng Router Agent, sau đó chuyển đến agent phù hợp: Retrieval Agent để trả lời FAQ/chính sách/sản phẩm, Action Agent để tra cứu hoặc tạo đơn hàng, Chitchat Agent để trả lời hội thoại thông thường, và Handoff Agent để chuyển nhân viên khi có khiếu nại hoặc bot không đủ tự tin.

Kho tri thức của AI gồm dữ liệu sản phẩm từ MySQL, FAQ và các trang chính sách. Dữ liệu này được index vào ChromaDB, kết hợp truy xuất ngữ nghĩa và BM25, sau đó hợp nhất kết quả bằng Reciprocal Rank Fusion. Cách tiếp cận này giúp hệ thống vừa hiểu được ngữ nghĩa câu hỏi tiếng Việt, vừa bắt chính xác tên sản phẩm hoặc từ khóa đặc thù như VNPAY, COD, gluten, bánh kem dâu.

### 5.3. Đoạn mô tả đóng góp khóa luận

Đề tài không chỉ xây dựng một website bán bánh trực tuyến mà còn tích hợp một trợ lý AI CSKH có khả năng xử lý hội thoại tiếng Việt trong ngữ cảnh thương mại điện tử. Đóng góp chính gồm: xây dựng kiến trúc AI Service tách biệt với hệ thống PHP hiện có, thiết kế pipeline multi-agent cho các nhóm tác vụ CSKH, xây dựng bộ dữ liệu đánh giá 150 mẫu hội thoại tiếng Việt, so sánh kiến trúc Baseline RAG và Multi-Agent RAG, đồng thời triển khai cơ chế handoff để kết hợp giữa tự động hóa bằng AI và hỗ trợ của nhân viên thật.

## 6. Đề xuất thứ tự ưu tiên chỉnh sửa

1. Đổi tên đề tài và cập nhật lời mở đầu theo hướng AI CSKH.
2. Sửa các lỗi mâu thuẫn: Homestay, thanh toán online, actor Nhân viên xuất hiện ở tổng kết nhưng chưa phân tích.
3. Bổ sung yêu cầu chức năng/phi chức năng cho AI trong Chương 1.
4. Bổ sung actor/use case/diagram cho AI trong Chương 2.
5. Mở rộng Chương 3 thành thiết kế kiến trúc, CSDL, API và giao diện.
6. Thêm chương cài đặt/triển khai và chương kiểm thử/thực nghiệm.
7. Cập nhật Chương tổng kết để nhấn mạnh kết quả AI, hạn chế và hướng phát triển.
8. Thêm tài liệu tham khảo về RAG, multi-agent, LangGraph, ChromaDB, chatbot CSKH và xử lý tiếng Việt.

## 7. Ghi chú đồng bộ tài liệu phụ

Các file `docs/thesis/architecture-diagrams.md`, `docs/thesis/defense-qa.md`, `docs/thesis/demo-script.md` đã có nhiều nội dung tốt cho phần bảo vệ, nhưng nên đồng bộ lại với code hiện tại trước khi đưa vào báo cáo chính:

- Endpoint hiện tại trong code là `/chat/send`, không phải `/chat/message`.
- `app/config.py` hiện có `llm_provider`, `deepseek_api_key`, `gemini_api_key`, `llm_model`, `embedding_model`; cần ghi đúng default hiện hành nếu đưa vào báo cáo.
- Dataset thực tế `ai-service/eval/dataset/samples.jsonl` hiện có 150 mẫu, trong khi README dataset vẫn có đoạn nói "11 mẫu khởi động"; nên sửa README hoặc chỉ trình bày theo trạng thái 150 mẫu.
