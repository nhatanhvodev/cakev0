# Thiết kế: Chuyển thanh toán VNPAY + Chuyển khoản thủ công → SePay

- **Ngày**: 2026-08-04
- **Trạng thái**: Đã duyệt thiết kế, chờ review spec
- **Phạm vi**: Thay thế hoàn toàn 2 phương thức `VNPAY` và `Chuyển khoản` (thủ công) bằng 1 luồng **SePay QR** (chuyển khoản tự khớp qua webhook). Giữ nguyên `COD`.

## 1. Bối cảnh & lý do

Hiện `pages/checkout.php` hỗ trợ 3 phương thức: `VNPAY`, `Chuyển khoản`, `COD`.

- VNPAY (sandbox, TmnCode `GO1IEGFS`) lỗi mã 71 "Website chưa được phê duyệt" — credential demo dùng chung không đăng ký được cho domain production. Không sửa được bằng code.
- "Chuyển khoản" là thủ công: khách tự chuyển, admin tự đối soát, không tự xác nhận.

SePay gắn với tài khoản ngân hàng thật, hiện VietQR cho khách quét, khi tiền vào thì bắn **webhook** về server để tự khớp đơn và đánh dấu `paid`. Gộp cả 2 phương thức cũ vào 1 luồng SePay QR tự động.

**Quyết định đã chốt:**
- Phương thức sau chuyển đổi: **SePay QR + COD** (còn 2 lựa chọn).
- Xác nhận thanh toán: **auto-poll** — trang QR tự hỏi server, tự nhảy "thành công" khi tiền tới.
- Mã nội dung chuyển khoản: **`DH<order_id>`** (vd `DH69`).
- QR hết hạn: **15 phút**.
- VNPAY: **xóa hẳn** thư mục `vnpay/` + config + env liên quan.

## 2. Kiến trúc luồng

```
Checkout (POST)
 ├─ COD          → tạo đơn pending, xóa giỏ, về trang chủ (giữ nguyên)
 └─ SePay QR     → tạo đơn pending → redirect sepay/payment.php?order=<id>
                       │
                       ├─ Hiện VietQR (acc + bank + amount + nội dung "DH<id>")
                       ├─ Đếm ngược 15 phút
                       └─ JS auto-poll api/sepay/status.php?order=<id> mỗi 4s
                                            ▲
 SePay server ──webhook POST──► api/sepay/webhook.php
     (khi tiền vào tài khoản)      ├─ verify API key (hash_equals, fail-closed)
                                   ├─ chỉ xử lý transferType == "in"
                                   ├─ tách order_id từ content/code ("DH<id>")
                                   ├─ verify transferAmount >= total_amount
                                   ├─ chống trùng theo sepay_id
                                   └─ markOrderPaid(): pending→paid, xóa giỏ,
                                      +coupon, gửi hóa đơn, notify
```

Khách chuyển xong → webhook đánh `paid` → poll kế tiếp thấy `paid` → trang tự chuyển sang trạng thái "Thanh toán thành công".

## 3. Thành phần (units)

### 3.1 `includes/sepay_helpers.php` (logic thuần, testable không cần HTTP)
- `ensureSepayInfrastructure($conn)` — tạo bảng `sepay_transactions` nếu chưa có (pattern giống `ensureCartCouponInfrastructure`).
- `sepay_build_qr_url(array $cfg, int $orderId, int $amount): string` — dựng URL ảnh VietQR:
  `https://qr.sepay.vn/img?acc=<acc>&bank=<bank>&amount=<amount>&des=DH<orderId>`
- `sepay_payment_content(int $orderId): string` — trả `"DH<orderId>"`.
- `sepay_extract_order_id(string $content, ?string $code): ?int` — ưu tiên `code`, fallback regex `/DH0*(\d+)/i` trên `content`; trả `null` nếu không khớp.
- `sepay_verify_api_key(?string $header, string $expected): bool` — so `Apikey <key>` bằng `hash_equals`; false nếu `$expected` rỗng (fail-closed).
- `markOrderPaid(mysqli $conn, int $orderId): array` — **gom logic pending→paid** hiện đang inline trong `vnpay/vnpay_return.php`:
  - đọc `user_id, coupon_code, status`
  - `UPDATE orders SET status='paid'` (chỉ khi đang không phải 'paid')
  - trừ giỏ theo `order_items`, dọn giỏ
  - `incrementCouponUsage` nếu trước đó chưa paid và có coupon
  - `notifyOrderStatusChanged`, `send_order_invoice_email`
  - trả `['changed' => bool, 'previous' => string]`
  - **idempotent**: nếu đã 'paid' thì không lặp coupon/email.

### 3.2 `api/sepay/webhook.php`
- Nhận `POST` JSON từ SePay.
- `ensureSepayInfrastructure($conn)`.
- Verify header `Authorization` qua `sepay_verify_api_key()` — sai → `401`.
- Parse body; bỏ qua nếu `transferType != 'in'` → `200` (ack, không xử lý).
- `sepay_extract_order_id()` — không khớp → `200` ack + log.
- Chống trùng: `INSERT ... sepay_transactions(sepay_id ...)`; nếu trùng key → `200` ack, dừng.
- Đọc đơn; verify `transferAmount >= total_amount` — thiếu tiền → log + `200` ack, KHÔNG đánh paid.
- Gọi `markOrderPaid()`.
- Trả JSON `{"success": true}` `200`.
- Toàn bộ trong try/catch, luôn trả `200` cho trường hợp đã ack để SePay không retry vô hạn; chỉ trả `401` khi sai key.

### 3.3 `api/sepay/status.php`
- `GET ?order=<id>`, yêu cầu `session_start()` + `$_SESSION['user_id']`.
- Truy vấn `SELECT status FROM orders WHERE id=? AND user_id=?` — **chỉ chủ đơn** (chống IDOR).
- Không thấy → `404`. Thấy → `{"status": "pending|paid|failed"}`.

### 3.4 `sepay/payment.php`
- `session_start()`, bắt đăng nhập, đọc đơn theo `?order=<id>` + `user_id` (chỉ chủ đơn).
- Nếu đơn không `pending` → hiển thị trạng thái tương ứng (đã trả / hết hạn).
- Hiện: ảnh VietQR, số tiền, nội dung `DH<id>`, số tài khoản + tên + bank, đồng hồ đếm ngược 15 phút.
- JS `setInterval` 4s gọi `status.php`; `paid` → chuyển UI "thành công" + link về trang chủ; hết 15 phút → dừng poll, hiện "QR hết hạn, tạo lại đơn".
- Reuse toast + style tông nâu như `vnpay_return.php`.

### 3.5 Sửa `pages/checkout.php`
- Radio: bỏ `VNPAY` + `Chuyển khoản`, thêm 1 radio `value="SePay"` nhãn "Chuyển khoản QR (SePay)". Giữ `COD`.
- Nhánh POST:
  - `SePay` → sau khi tạo đơn pending (KHÔNG xóa giỏ ngay, giữ để webhook trừ theo item như VNPAY cũ; coupon tăng ở `markOrderPaid`), redirect `Location: /cakev0/sepay/payment.php?order=<id>`.
  - `COD` → giữ nguyên (xóa giỏ ngay, +coupon ngay, về trang chủ).
- Gỡ toàn bộ block dựng URL VNPAY (dòng ~152-199) và `require vnpay/config.php`.
- Cập nhật `includes/checkout_helpers.php::shouldClearCartAfterOrderPlacement()` — SePay không xóa giỏ ngay (giống VNPAY cũ), COD xóa ngay.

## 4. Dữ liệu

### Bảng mới `sepay_transactions` (additive, tạo qua ensure-function)
```sql
CREATE TABLE IF NOT EXISTS sepay_transactions (
    sepay_id   VARCHAR(50) PRIMARY KEY,   -- id giao dịch từ SePay, chống trùng
    order_id   INT NOT NULL,
    amount     BIGINT NOT NULL,
    content    VARCHAR(255) NULL,
    raw        TEXT NULL,                  -- payload gốc để đối soát
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id)
);
```
- Không thêm cột vào `orders`; trạng thái vẫn dùng `orders.status` (`pending`/`paid`/`failed`).
- Đơn cũ với `payment_method` = 'VNPAY'/'Chuyển khoản' giữ nguyên trong DB, hiển thị lịch sử không đổi.

### Migration
- File `database/migrations/2026_08_04_sepay_transactions.sql` (tài liệu hóa) + `ensureSepayInfrastructure()` tự tạo runtime (production Aiven, giống các ensure-function hiện có).

## 5. Cấu hình (env)

Thêm:
```
SEPAY_WEBHOOK_API_KEY=<api key webhook SePay>
SEPAY_ACCOUNT_NUMBER=<số tài khoản (test mode)>
SEPAY_BANK_CODE=<mã bank, vd MBBank / OCB>
SEPAY_ACCOUNT_NAME=<tên chủ tài khoản>
```
Xóa: `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL`, `VNPAY_RETURN_URL`, `VNPAY_MERCHANT_URL`, `VNPAY_TRANSACTION_API_URL`.

Đọc qua `env_value()` trong một helper `sepay_config()` trả mảng `['api_key','account','bank','name']`.

## 6. Việc thủ công trong SePay dashboard (Test mode)

1. Bật **Test mode**; tạo tài khoản/VA test → lấy **số tài khoản + mã bank**.
2. Tạo **Webhook** → URL `https://cake-i8l0.onrender.com/cakev0/api/sepay/webhook.php`, auth **API Key** → copy key.
3. Điền env Render (mục 5) → redeploy.
4. Test bằng nút **"Mô phỏng chuyển khoản"** trong dashboard (bắn webhook thật, không cần tiền/bank thật). QR test không quét được bằng app bank thật — đúng thiết kế test mode.

## 7. Bảo mật (theo chuẩn audit gần đây)

- Webhook: verify API key `hash_equals`, **fail-closed** khi env rỗng.
- Chỉ `transferType == 'in'`.
- `transferAmount >= total_amount` mới đánh paid (chống trả thiếu).
- Idempotency theo `sepay_id` (chống double coupon/hóa đơn khi SePay retry).
- `status.php` chỉ trả cho chủ đơn (`user_id` khớp session) — chống IDOR.
- Log payload webhook (bảng `raw`) để đối soát tranh chấp.

## 8. Kiểm thử

Standalone (`tests/`, pattern `assert_true`/`assert_same`):
- `tests/sepay_helpers_test.php`:
  - `sepay_extract_order_id`: "DH69" → 69; "Thanh toan DH0069 gau" → 69; ưu tiên `code`; rác → null.
  - `sepay_verify_api_key`: đúng key → true; sai/rỗng → false.
  - `sepay_build_qr_url`: chứa acc/bank/amount/des đúng.
- `tests/sepay_mark_paid_test.php` (cần DB test):
  - gọi `markOrderPaid` 2 lần trên cùng đơn → coupon chỉ +1, email chỉ 1 lần (idempotent).
  - đơn pending → paid, giỏ bị trừ.

## 9. Ngoài phạm vi (YAGNI)

- Không làm VA (virtual account) per-order — dùng 1 tài khoản + nội dung `DH<id>` là đủ khớp.
- Không làm hoàn tiền/đối soát tự động nâng cao.
- Không đụng luồng tạo đơn của AI service (`api/internal/orders/*`) — nếu sau này AI tạo đơn cần QR sẽ tính riêng.
- Không làm cổng thẻ quốc tế của SePay (Visa/Master) — chỉ VietQR.

## 10. Rủi ro & xử lý

- **Webhook trễ/không tới**: poll timeout 15 phút → khách thấy "hết hạn"; admin vẫn đối soát tay được (đơn còn pending). Có thể thêm nút "Kiểm tra lại" sau.
- **Khách chuyển sai nội dung**: không khớp `DH<id>` → webhook ack nhưng không đánh paid → log lại, admin xử lý tay. (Chấp nhận ở phạm vi này.)
- **Chuyển đúng nội dung nhưng thiếu tiền**: không đánh paid, log; admin xử lý.
