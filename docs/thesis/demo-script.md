# Kịch bản demo - Bảo vệ khóa luận

> Hướng dẫn demo hệ thống AI CSKH Gấu Bakery trước hội đồng.
> Website: `https://cake-i8l0.onrender.com/cakev0/`
> Engine đề xuất: `ENGINE=multiagent`. Dùng `ENGINE=demo` khi cần fallback chống lỗi LLM/API trong buổi demo.

---

## Chuẩn bị trước demo

1. Mở website Gấu Bakery và kiểm tra widget chat góc phải dưới.
2. Mở tab admin `admin/admin.php?tab=chat#chat` để demo handoff nếu có tài khoản admin.
3. Kiểm tra AI service `/health`: phải trả `status=ok`, engine đúng và `products_indexed` không âm.
4. Nếu demo Telegram, mở sẵn Telegram group nhận thông báo.
5. Chuẩn bị một tài khoản khách đã đăng nhập để demo: tra cứu đơn, tạo đơn COD, yêu thích.
6. Nếu không chắc LLM/API ổn định, đổi `ENGINE=demo` trước buổi bảo vệ và giải thích đây là cơ chế fallback.

Lưu ý: một số kịch bản cần DB và đăng nhập. Nếu đang ở môi trường khách vãng lai, ưu tiên demo FAQ, catalog, policy, custom quote và handoff.

---

## Kịch bản 1: FAQ và chính sách giao hàng

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"shop mở cửa mấy giờ vậy"** | Bot trả lời thông tin giờ mở cửa/FAQ. |
| 2 | Gõ: **"ship bao lau"** | Normalizer map `ship -> giao hàng`; router nhận `policy_shipping`; bot trả lời chính sách giao hàng. |

Điểm nhấn:

- Normalizer hiện là dictionary teencode, không phải mô hình phục hồi dấu tổng quát.
- Retrieval dùng collection phù hợp thay vì search toàn bộ tri thức.

---

## Kịch bản 2: Tìm sản phẩm - Catalog Search

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"có bánh kem dâu không"** | Bot trả lời sản phẩm liên quan và hiển thị product cards. |
| 2 | Gõ: **"cái đó giá bao nhiêu"** | Bot dùng lịch sử hội thoại để hiểu ngữ cảnh sản phẩm vừa hỏi. |

Điểm nhấn:

- Hybrid search: ChromaDB cho ngữ nghĩa, BM25 cho từ khóa tên bánh.
- Router fallback sang LLM nếu câu hiện tại không có keyword rõ.

---

## Kịch bản 3: Gợi ý sản phẩm

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"sinh nhật bé trai nên mua bánh gì"** | Bot gợi ý 3-5 sản phẩm phù hợp. |

Điểm nhấn:

- Intent `product_recommend` đi qua Retrieval Agent.
- Nếu có sản phẩm đang khuyến mãi, code có `_rerank_by_promotion()` để ưu tiên trong danh sách.

---

## Kịch bản 4: Chính sách thanh toán và đổi trả

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"thanh toán chuyển khoản QR (SePay) được không"** | Bot trả lời chính sách thanh toán (COD và SePay VietQR). |
| 2 | Gõ: **"đổi bánh được ko a"** | Normalizer map `ko -> không`, router nhận `policy_return`. |

Điểm nhấn:

- Các policy được tách collection riêng.
- Trả lời nên bám policy, không bịa quy định ngoài tài liệu.

---

## Kịch bản 5: Khuyến mãi và mã giảm giá

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"có khuyến mãi gì không"** | Bot liệt kê sản phẩm đang khuyến mãi nếu có. |
| 2 | Gõ: **"có mã giảm giá nào không"** | Bot trả mã coupon công khai, ví dụ `WELCOME10` nếu DB đã chạy migration. |

Điểm nhấn:

- `promotion` khác `coupon_inquiry`.
- Coupon chỉ hiện nếu mã đang active và còn hiệu lực trong `cart_coupons`.

---

## Kịch bản 6: Đánh giá và so sánh sản phẩm

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"bánh tiramisu đánh giá thế nào"** | Bot tóm tắt rating và vài nhận xét nếu có. |
| 2 | Gõ: **"so sánh tiramisu và mousse chocolate"** | Bot so sánh giá, loại, kích cỡ nếu resolve được sản phẩm. |

Điểm nhấn:

- Đây là action intent, không chỉ retrieval text.
- Bot vừa query DB vừa trả product cards.

---

## Kịch bản 7: Tư vấn thành phần/dị ứng

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"bánh nào không có trứng"** | Bot lọc sản phẩm theo cột `co_trung`. |
| 2 | Gõ: **"có bánh không gluten không"** | Bot lọc theo `co_gluten` và kèm disclaimer an toàn. |

Điểm nhấn:

- Dữ liệu thành phần nằm trong bảng `banh`: `co_trung`, `co_sua`, `co_gluten`, `co_hat`.
- Câu trả lời có lưu ý gọi hotline nếu dị ứng nặng.

---

## Kịch bản 8: Sản phẩm yêu thích

Điều kiện: khách đã đăng nhập.

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"lưu bánh kem dâu vào yêu thích"** | Bot thêm sản phẩm vào bảng `favorites`. |
| 2 | Gõ: **"xem bánh yêu thích của tôi"** | Bot trả danh sách sản phẩm đã lưu. |

Nếu chưa đăng nhập, bot yêu cầu đăng nhập trước.

---

## Kịch bản 9: Tra cứu đơn hàng

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Đăng nhập tài khoản có đơn hàng. | Widget gửi `user_id` qua PHP proxy. |
| 2 | Gõ: **"đơn hàng của tôi đến đâu rồi"** | Bot trả các đơn gần nhất với trạng thái, tổng tiền, sản phẩm. |

Fallback nếu chưa đăng nhập:

- Gõ: **"kiểm tra đơn 0901234567"** để bot tra theo số điện thoại.

Điểm nhấn:

- Intent `order_status` đi vào Action Agent.
- Query DB realtime qua `orders_repo.lookup_orders()`.

---

## Kịch bản 10: Đặt bánh COD qua chat

Điều kiện: khách đã đăng nhập. Nếu chưa đăng nhập, bot trả link đăng nhập.

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"đặt 2 bánh Croissant"** | Bot tìm sản phẩm và hỏi tên người nhận. |
| 2 | Trả lời: **"Nguyễn Văn A"** | Bot hỏi số điện thoại. |
| 3 | Trả lời: **"0901234567"** | Bot hỏi địa chỉ giao. |
| 4 | Trả lời địa chỉ. | Bot tóm tắt đơn và yêu cầu xác nhận. |
| 5 | Gõ: **"đồng ý"** | Bot gọi PHP internal order API và tạo đơn COD. |

Điểm nhấn:

- `order_draft` được lưu trong `chat_sessions.metadata`.
- PHP internal order API dùng HMAC, validate payload, transaction và trừ tồn kho.

---

## Kịch bản 11: Báo giá bánh thiết kế riêng

Kịch bản này cho phép khách vãng lai.

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"đặt bánh sinh nhật thiết kế riêng"** | Bot bắt đầu luồng báo giá và hỏi số người dùng bánh. |
| 2 | Trả lời lần lượt: số người, vị, ngày cần, ghi chú, tên, SĐT. | Bot tóm tắt yêu cầu. |
| 3 | Gõ: **"đồng ý"** | Bot tạo lead trong `contact_requests`. |

Điểm nhấn:

- Khác `order_create`: không tạo đơn ngay, mà tạo yêu cầu báo giá cho nhân viên.
- Luồng này không bắt buộc đăng nhập.

---

## Kịch bản 12: Chitchat

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"chào shop"** | Bot chào lại ngắn gọn. |
| 2 | Gõ: **"cảm ơn nha"** | Bot đáp lịch sự, không gọi retrieval. |

Điểm nhấn:

- Keyword-first router nhận `chitchat`.
- Chitchat node dùng LLM generate trực tiếp với lịch sử gần nhất.

---

## Kịch bản 13: Khiếu nại và handoff cho người thật

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"bánh giao bị móp, bực quá, hoàn tiền cho tôi"** | Bot nhận khiếu nại và chuyển nhân viên. |
| 2 | Mở admin tab Hội thoại. | Phiên chat xuất hiện trong hàng chờ/open/handoff. |
| 3 | Admin bấm nhận phiên và trả lời. | Tin nhắn agent được lưu trong `chat_messages`. |
| 4 | Nếu có Telegram config. | Telegram group nhận alert handoff. |

Điểm nhấn:

- Direct `complaint`/`handoff_request` tạo support ticket.
- Admin workflow có `claim`, `close`, `reopen`.

---

## Kịch bản 14: Yêu cầu gặp nhân viên

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Gõ: **"cho tôi gặp nhân viên"** | Bot chuyển thẳng người thật. |

Điểm nhấn:

- Intent `handoff_request`.
- `handoff_node()` bỏ qua draft LLM vì khách đã yêu cầu rõ.

---

## Kịch bản 15: DemoEngine fallback

Mục đích: cho thấy hệ thống không crash khi LLM/API lỗi trong buổi demo.

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Chạy AI service với `ENGINE=demo` và cấu hình LLM key lỗi/thiếu. | MultiAgent lỗi sẽ bị DemoEngine bắt. |
| 2 | Gõ: **"có bánh gì ngon"** hoặc **"cho gặp nhân viên"** | Bot trả canned response theo keyword fallback. |

Điểm nhấn:

- DemoEngine là cơ chế an toàn cho demo, không phải engine đầy đủ.
- Canned response hiện cover các intent demo chính; intent mới có thể rơi về fallback FAQ.

---

## Kịch bản 16: Mail driver, Resend và hóa đơn PDF

Mục đích: chứng minh hệ thống đã có lớp gửi email đa driver và có thể chuyển sang Resend bằng cấu hình.

| Bước | Hành động | Kỳ vọng |
|---|---|---|
| 1 | Mở file cấu hình môi trường hoặc dashboard deploy. | Thấy `MAIL_DRIVER` quyết định driver mail đang dùng. |
| 2 | Giải thích nếu dùng Resend cần `MAIL_DRIVER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`. | Hội đồng thấy Resend là chức năng đã có trong code, không phải chỉ là ý tưởng. |
| 3 | Chạy `php tools/diagnose_mail.php email@example.com` trong môi trường có key thật. | Tool in driver hiện tại, kiểm tra cấu hình và thử gửi mail. |
| 4 | Với đơn đã xác nhận/thanh toán, mở code hoặc demo nghiệp vụ gửi hóa đơn. | Hệ thống gọi `send_custom_mail_with_attachments()` và đính kèm hóa đơn PDF. |
| 5 | Kiểm tra đơn đã gửi hóa đơn. | `orders.invoice_email_sent_at` được cập nhật để hạn chế gửi trùng. |

Điểm nhấn:

- Resend dùng chung abstraction mail với SMTP và Gmail API.
- Hóa đơn PDF dùng attachment; không chỉ gửi text email.
- Nếu môi trường hiện đang để `MAIL_DRIVER=gmail_api`, cần nói rõ demo đang chạy Gmail API, còn Resend được bật bằng cấu hình.
- Không nên gửi email thật trong buổi bảo vệ nếu chưa chuẩn bị domain/from address đã verify.

---

## Thứ tự demo đề xuất trong 25-30 phút

1. Chitchat.
2. FAQ/chính sách giao hàng.
3. Catalog search.
4. Gợi ý sản phẩm.
5. Chính sách thanh toán/đổi trả.
6. Khuyến mãi/coupon.
7. Dị ứng/thành phần.
8. Tra cứu đơn hàng.
9. Báo giá bánh thiết kế riêng.
10. Handoff + admin reply.
11. Mail/Resend/hóa đơn PDF nếu có môi trường email thật.
12. DemoEngine fallback nếu còn thời gian.

Nếu thời gian ngắn, bỏ qua order create hoặc mail thật vì cần đăng nhập, tồn kho, internal order API và cấu hình email ổn định.

---

## Xử lý sự cố trong demo

| Sự cố | Cách xử lý |
|---|---|
| LLM/API quota hoặc key lỗi | Chuyển `ENGINE=demo`, giải thích fallback. |
| `products_indexed` thấp hoặc Chroma trống | Gọi `POST /knowledge/index?source=all` bằng admin bypass hoặc restart nếu auto-reindex cấu hình đúng. |
| Website Render cold start | Chờ 30-60 giây, mở `/health` trước khi demo. |
| Bot yêu cầu đăng nhập | Chuyển sang tài khoản test hoặc dùng kịch bản không cần login. |
| Tạo đơn thất bại | Demo flow đến bước summary, giải thích internal API/HMAC/stock transaction. |
| Telegram không nhận notify | Demo ticket/hội thoại trong admin thay thế. |
| MySQL lỗi | Demo các kịch bản ít phụ thuộc DB: chitchat, FAQ/policy nếu Chroma đã index. |
| Mail gửi thất bại | Chạy `php tools/diagnose_mail.php`, kiểm tra `MAIL_DRIVER`, key Resend/Gmail, from address đã xác minh và log HTTP lỗi. |
