# Plan fix bảo mật — 2026-08-03

Nguồn: audit bảo mật production (web PHP + `ai-service`). 1 lỗ HIGH (broken access
control lộ dữ liệu khách hàng, 2 vector + biến thể `/chat/history`), 3 lỗ LOW.

Quyết định đã chốt:
- **Tra đơn theo SĐT của guest**: bỏ. Chỉ user đã đăng nhập xem đơn của chính mình;
  guest được mời đăng nhập / chuyển nhân viên.
- **Auth `/chat/send`**: PHP proxy ký danh tính bằng HMAC(`INTERNAL_API_SECRET`);
  AI service verify, dùng `user_id` đã ký cho mọi quyết định truy cập dữ liệu, bỏ
  `user_id` thô từ body/query.

---

## Fix 1 — HIGH: IDOR / Broken Access Control (order lookup + chat identity spoof)

### Vấn đề
- `POST /chat/send` không auth, tin `req.user_id` từ body (`chat.py:160`). AI service
  public trên Render → gọi thẳng, spoof `user_id` bất kỳ để dump lịch sử đơn.
- `order_status` (`action.py:152-171`) tra đơn theo `phone` bắt từ free-text →
  guest xem đơn của SĐT bất kỳ.
- `GET /chat/history` (`chat.py:205-263`) tin `user_id` query param; `id` tuần tự →
  brute-force khớp owner để đọc lịch sử chat người khác.

### Thiết kế: danh tính user có chữ ký (mirror pattern `X-Admin-Bypass` sẵn có)
Định dạng header: `X-User-Identity: <unix_ts>:<user_id>:<hmac_sha256(secret, "user:<ts>:<user_id>")>`
Cửa sổ replay giống admin bypass (`ADMIN_BYPASS_MAX_AGE = 300`).

### Các bước

**1. PHP proxy — thêm helper ký danh tính**
File: `includes/chat_proxy_helpers.php`
- Thêm `chat_user_identity_header(int $userId): ?string` — trả `null` nếu
  `INTERNAL_API_SECRET` rỗng; ngược lại ký `user:<ts>:<userId>` giống
  `chat_admin_bypass_header()` (dòng 24-32), trả `'X-User-Identity: '.$ts.':'.$userId.':'.$hmac`.

**2. `send.php` — gắn header khi có user đăng nhập**
File: `api/chat/send.php`
- Sau khi lấy `$userId` (dòng 22), nếu `$userId !== null` thì thêm
  `chat_user_identity_header($userId)` vào mảng `CURLOPT_HTTPHEADER` (dòng 28).
- Vẫn KHÔNG gửi `user_id` trong body (proxy đã đúng — chỉ header là nguồn tin cậy).
  Nếu muốn giữ body `user_id` cho tương thích, AI sẽ bỏ qua nó cho mục đích auth.

**3. `history.php` — gắn header thay vì tin query `user_id`**
File: `api/chat/history.php`
- Bỏ `$query['user_id']` (dòng 62-63). Thay bằng: nếu `isset($_SESSION['user_id'])`,
  thêm `chat_user_identity_header((int)$_SESSION['user_id'])` vào `$headers` (dòng 69).
- Giữ nguyên nhánh guest_token (dòng 64-65) và admin bypass (dòng 59-75).

**4. AI service — verify danh tính, bỏ tin input thô**
File: `ai-service/app/api/chat.py`
- Thêm `_verify_user_identity(header_val: str | None) -> int | None` — mirror
  `_verify_admin_bypass` (dòng 80-97): parse `ts:user_id:sig`, check replay window,
  so `hmac(secret, f"user:{ts}:{user_id}")` bằng `compare_digest`. Trả `user_id` hợp lệ
  hoặc `None`.
- `chat_send` (dòng 137): thêm param `x_user_identity: str | None = Header(default=None)`.
  Tính `trusted_uid = _verify_user_identity(x_user_identity)`.
  - `get_or_create_session(..., user_id=trusted_uid, ...)` — dùng `trusted_uid`, KHÔNG
    `req.user_id` (dòng 146).
  - `context["user_id"] = trusted_uid` (thay dòng 160). Guest: `trusted_uid=None`,
    scope bằng `guest_token`.
- `chat_history` (dòng 205): thêm `x_user_identity` header; `trusted_uid = _verify_user_identity(...)`.
  Truyền `trusted_uid` vào `_session_owner_matches(session_row, trusted_uid, guest_token, None)`
  (dòng 239-241) thay cho query `user_id`. Bỏ param `user_id` khỏi query (hoặc giữ nhưng
  không dùng cho auth).

**5. `order_status` — chỉ tra đơn của chính chủ**
File: `ai-service/app/engines/multiagent/action.py` (dòng 152-171)
- `user_id = state.get("context", {}).get("user_id")` (đã là `trusted_uid`).
- Nếu `not user_id`: trả "Bạn vui lòng đăng nhập để tra cứu đơn hàng của mình nhé,
  hoặc mình chuyển bạn tới nhân viên hỗ trợ." (không tra theo phone nữa).
- Nếu có `user_id`: `lookup_orders(conn, order_id=order_id, user_id=user_id)` —
  bỏ tham số `phone`. `order_id` vẫn AND-scope theo `user_id`.
- Bỏ / giữ `extract_phone` tùy: không truyền vào lookup nữa.

**6. `lookup_orders` — bắt buộc user_id (defense in depth)**
File: `ai-service/app/db/orders_repo.py`
- Đầu hàm: nếu `user_id is None` → `return []` (không bao giờ cho tra chỉ bằng phone/order_id).
  Đảm bảo dù caller khác gọi sai vẫn không lộ chéo.

### Test (pytest, `ai-service/tests/`)
- `test_chat_api.py`: `/chat/send` với body `user_id` giả + KHÔNG header → order_status
  trả thông báo "đăng nhập", KHÔNG trả đơn.
- Header `X-User-Identity` hợp lệ (ts hiện tại) → `context["user_id"]` = giá trị đã ký.
- Header sai chữ ký / quá hạn (ts cũ > 300s) → coi như guest (None).
- `test_orders_repo.py`: `lookup_orders(user_id=None, phone="0900000000")` → `[]`.
- `/chat/history` với `user_id` query khớp owner nhưng KHÔNG header → 403.

---

## Fix 2 — LOW: Debug endpoint public lộ cấu hình
File: `ai-service/app/main.py` (dòng 157 `/debug/config`, 168 `/debug/llm-test`)
- Thêm setting `expose_debug: bool = False` trong `app/config.py`.
- Đầu 2 handler: nếu `not settings.expose_debug` → `raise HTTPException(404)`.
- Production để mặc định `False` (không set env). Dev đặt `EXPOSE_DEBUG=true` khi cần.

---

## Fix 3 — LOW: So sánh chữ ký VNPAY dùng `hash_equals`
File: `vnpay/vnpay_return.php` (dòng 100)
- `if ($secureHash == $vnp_SecureHash)` → `if (hash_equals($secureHash, (string) $vnp_SecureHash))`.
- Chống type-juggling + timing (hardening).

---

## Fix 4 — LOW (hardening): ownership cho `/chat/handoff` + gỡ admin legacy
- `ai-service/app/api/chat.py` `/chat/handoff` (dòng 266): hiện ai cũng tạo được
  ticket/handoff cho `session_id` bất kỳ. Thêm check ownership: verify `X-User-Identity`
  hoặc `guest_token` khớp session trước khi `create_ticket` / `update_session`.
  (Không lộ dữ liệu, chỉ chống spam state — ưu tiên thấp.)
- `admin/admin.php` legacy: theo tài liệu có link `?delete_*_id` không CSRF. Xác nhận
  KHÔNG còn route tới được trong production; nếu vậy **xóa file** khỏi repo. Nếu còn
  dùng, port nốt sang modular admin (đã có contract CSRF).

---

## Thứ tự triển khai
1. Fix 1 (HIGH) — làm trọn gói PHP proxy + AI verify + order_status + repo guard + test.
2. Fix 2, Fix 3 (LOW, nhanh, độc lập).
3. Fix 4 (hardening, khi có thời gian).

## Lưu ý deploy
- `INTERNAL_API_SECRET` phải trùng giữa web PHP và ai-service (đã có). Toàn bộ Fix 1
  phụ thuộc secret này — nếu rỗng, header không được ký và order_status luôn coi là guest
  (fail-closed, an toàn).
- Không đổi schema DB → không cần migration.
