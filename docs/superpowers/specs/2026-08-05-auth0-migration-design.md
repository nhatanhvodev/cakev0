# Thiết kế: Chuyển hệ thống xác thực sang Auth0

- **Ngày**: 2026-08-05
- **Nhánh dự kiến**: `auth0-migration`
- **Trạng thái**: Design — chờ duyệt trước khi lập plan

## 1. Mục tiêu

Thay toàn bộ cơ chế tự quản đăng nhập/đăng ký hiện tại (mật khẩu bcrypt trong bảng `users`/`admins`, session tự set, CSRF thủ công, remember-me token, email verification tự viết) bằng **Auth0** làm nhà cung cấp danh tính (Identity Provider) duy nhất, đồng thời **giữ nguyên toàn bộ tầng dữ liệu** đang khoá theo `users.id` (int).

### Kết quả mong muốn
- Auth0 sở hữu hoàn toàn: xác thực, mật khẩu, MFA, đăng nhập Google, luồng đăng ký, quên mật khẩu.
- User cũ đăng nhập bằng đúng mật khẩu cũ (không phải reset).
- Admin xác thực qua Auth0 với vai trò trong metadata.
- 23 file đọc `$_SESSION['user_id']` / `$_SESSION['admin_logged_in']` **không phải sửa logic** — chỉ đổi nơi nạp session.

### Non-goals (ngoài phạm vi)
- Không viết lại tầng dữ liệu order/review/favorite/chat.
- Không verify JWT thủ công ở từng request (dùng session cookie do Auth0 SDK quản — pattern chuẩn cho PHP server-rendered).
- Không đổi giao diện trang chủ/checkout/admin panel (trừ nút login/logout).
- Không di trú dữ liệu nghiệp vụ (orders...) — chúng đã khoá theo int id sẵn có.

## 2. Quyết định đã chốt (từ brainstorm)

| Chủ đề | Quyết định |
|--------|-----------|
| Độ sâu | Auth0 là authority xác thực; **cầu nối** về `$_SESSION['user_id']` (int) để giữ data layer |
| Admin | Qua Auth0 + `app_metadata.role = admin`; bảng `admins` nghỉ |
| Phương thức | Email/password (migrate) + Google social + MFA/OTP |
| Trang login | Auth0 Universal Login (hosted, redirect); bỏ `login.php` custom |
| Migrate user | Bulk import bcrypt qua Management API (giữ mật khẩu) |

## 3. Kiến trúc

### 3.1. Nguyên tắc cầu nối (bridge)
Auth0 SDK (`auth0/auth0-php`) quản một session cookie riêng sau OIDC callback. App **không** đọc trực tiếp cookie đó ở 23 file. Thay vào đó, một lớp bootstrap auth mới sẽ:

1. Sau callback (hoặc mỗi page load nếu session app trống), lấy danh tính từ Auth0 SDK.
2. **Resolve** danh tính đó về một dòng trong bảng `users` (mirror), khoá theo `auth0_id`. Nếu chưa có thì provision (tạo mới).
3. Set các biến session mà app đang dùng: `$_SESSION['user_id']` (int local), `$_SESSION['username']`, `$_SESSION['role']`, và với admin thì `$_SESSION['admin_logged_in']`, `$_SESSION['admin_id']`.

Nhờ bước 3, mọi file hiện có (chat HMAC ký theo int id, `account.php`, checkout, admin guard) **chạy nguyên xi**.

### 3.2. Bảng `users` thành mirror
- Thêm cột `auth0_id VARCHAR(64) UNIQUE NULL` — khoá liên kết Auth0 sub (vd `auth0|abc123`, `google-oauth2|...`).
- Cột `password` **ngừng dùng** cho xác thực (giữ tạm để rollback, không đọc nữa; xoá ở giai đoạn dọn dẹp sau).
- `email` là điểm khớp khi bulk import (Auth0 sub được ghi ngược lại vào `auth0_id`).
- Các cột nghiệp vụ (`phone`, `avatar`, `username`) vẫn do app quản qua `account.php` như cũ.

### 3.3. Bảng nghỉ việc
- `admins` — thay bằng role trong Auth0 metadata. Giữ bảng (không xoá ngay) để rollback; ngừng ghi/đọc.
- `login_tokens` (remember-me) — Auth0 quản session/refresh, không cần.
- `pending_registrations` + `verify-registration.php` — Auth0 lo email verification.
- `login_logs` — **giữ nguyên** (audit nội bộ); ghi log tại callback thay vì tại `login.php`.

## 4. Thành phần & file

### File mới
| File | Vai trò |
|------|--------|
| `includes/auth0.php` | Khởi tạo `Auth0` SDK singleton từ env; helper `auth0_client()` |
| `includes/auth_bridge.php` | `sync_session_from_auth0()`: resolve/provision `users` row, set `$_SESSION`; `current_user_id()`, `is_admin()` |
| `pages/auth/callback.php` | Nhận redirect từ Auth0, đổi code lấy token, gọi bridge, redirect về đích |
| `pages/auth/login.php` | Khởi tạo `$auth0->login()` → redirect Universal Login (thay `login.php` cũ) |
| `pages/auth/logout.php` | Clear session app + `$auth0->logout()` (thay `logout.php` cũ) |
| `scripts/migrate_users_to_auth0.php` | CLI: đọc `users`+`admins`, bulk import bcrypt qua Management API, ghi `auth0_id` ngược lại |
| `database/migrations/2026_08_05_auth0.sql` | Thêm cột `users.auth0_id` + index |

### File sửa
| File | Thay đổi |
|------|---------|
| `composer.json` | Thêm `auth0/auth0-php` |
| `includes/header.php` | Nút Đăng nhập/Đăng xuất trỏ tới `pages/auth/login.php` / `logout.php` |
| `pages/login.php`, `register.php`, `logout.php`, `verify-registration.php`, `forgot-password.php` | Redirect 301 sang luồng Auth0 (giữ file mỏng để không vỡ link cũ) |
| `admin/bootstrap.php` | Guard đọc role từ session do bridge set (không đổi tên biến) |
| `config/bootstrap.php` hoặc điểm bootstrap chung | Nạp `auth_bridge` sớm để `$_SESSION['user_id']` sẵn sàng cho mọi page |

### Không sửa (bằng chứng đã khảo sát)
`api/chat/send.php`, `api/chat/history.php` (HMAC ký theo `$_SESSION['user_id']`), `pages/account.php`, `pages/checkout.php`, `pages/order-detail.php`, `pages/favorites.php`, và các file còn lại trong 23 file — vì biến session giữ nguyên tên & kiểu.

## 5. Luồng xác thực

### 5.1. Đăng nhập
1. User bấm "Đăng nhập" → `pages/auth/login.php` → `$auth0->login(callbackUrl)` → redirect Auth0 Universal Login.
2. User chọn email/password hoặc Google; Auth0 áp MFA nếu bật.
3. Auth0 redirect về `pages/auth/callback.php?code=...&state=...`.
4. Callback: `$auth0->exchange()` → có id_token + user profile.
5. `sync_session_from_auth0()`: tìm `users WHERE auth0_id = sub`; nếu không có, tìm theo `email` và gán `auth0_id`; nếu vẫn không có, tạo dòng mới. Set `$_SESSION`. Đọc `role` từ `app_metadata` → nếu `admin`, set cờ admin.
6. Ghi `login_logs` (success). Redirect về `index.php` hoặc `admin/index.php` theo role.

### 5.2. Đăng ký
- "Đăng ký ngay" trỏ tới cùng `pages/auth/login.php` với `screen_hint=signup` → Auth0 hiện form đăng ký, tự gửi email verification. Không còn `pending_registrations`.

### 5.3. Đăng xuất
- `pages/auth/logout.php`: ghi `login_logs` (logout), `session_destroy()`, rồi `$auth0->logout(returnTo)` để clear cả session Auth0.

### 5.4. Admin
- Role gán trong Auth0 (`app_metadata.role=admin`), thiết lập lúc import hoặc qua Auth0 Dashboard.
- Bridge đọc role, set `$_SESSION['admin_logged_in']=true` + `role='admin'`. `admin/bootstrap.php` guard chạy nguyên.
- MFA bắt buộc cho tài khoản admin (rule/policy trên Auth0).

## 6. Di trú tài khoản (bulk import bcrypt)

`scripts/migrate_users_to_auth0.php` (chạy 1 lần, thủ công):

1. Đọc từng dòng `users`: email, username, `password` (bcrypt `$2y$...`), phone.
2. Gọi Management API `POST /api/v2/users`:
   ```
   {
     "connection": "Username-Password-Authentication",
     "email": "<email>",
     "email_verified": true,
     "custom_password_hash": { "algorithm": "bcrypt", "hash": { "value": "$2y$10$..." } },
     "user_metadata": { "username": "<username>", "phone": "<phone>" }
   }
   ```
3. Nhận `user_id` (sub) trả về → `UPDATE users SET auth0_id = ? WHERE id = ?`.
4. Với bảng `admins`: import tương tự nhưng thêm `app_metadata: { role: "admin" }`. Nếu admin dùng email trùng user thì gán role lên user đó thay vì tạo mới.
5. Idempotent: bỏ qua dòng đã có `auth0_id`; log kết quả từng dòng.

Bí mật Management API (client credentials M2M) chỉ dùng trong script, không nhúng vào app runtime.

## 7. Ảnh hưởng chat / AI service

Không đổi. `chat_user_identity_header((int)$_SESSION['user_id'])` vẫn ký HMAC theo local int id. Vì bridge đảm bảo `$_SESSION['user_id']` luôn là int id của dòng `users` tương ứng, AI service không cần biết gì về Auth0. `INTERNAL_API_SECRET` và HMAC identity giữ nguyên (xem obs 2968/2970).

## 8. Cấu hình / biến môi trường

Thêm vào `.env` / Render (đọc qua `env_value()`):

```
AUTH0_DOMAIN=<tenant>.auth0.com
AUTH0_CLIENT_ID=...
AUTH0_CLIENT_SECRET=...
AUTH0_COOKIE_SECRET=<random 32+ bytes>       # ký session cookie của SDK
AUTH0_CALLBACK_URL=<app_origin>/cakev0/pages/auth/callback.php
AUTH0_LOGOUT_RETURN_URL=<app_origin>/cakev0/index.php
# chỉ cho script migrate, KHÔNG set trên app runtime:
AUTH0_MGMT_CLIENT_ID=...
AUTH0_MGMT_CLIENT_SECRET=...
```

Callback URL tận dụng `absolute_url()`/`app_origin()` đã có (config.php). Local dev: `http://localhost:8080/cakev0/...`; prod: `https://cake-i8l0.onrender.com/cakev0/...` — cả hai đăng ký trong Allowed Callback URLs trên Auth0.

## 9. Bảo mật

- SDK tự lo state/nonce (chống CSRF trên OIDC) — bỏ CSRF token thủ công ở luồng auth.
- `AUTH0_CLIENT_SECRET` và `AUTH0_COOKIE_SECRET` chỉ trong env, không commit.
- Cột `users.password` ngừng đọc; xoá ở giai đoạn dọn dẹp để tránh lộ hash cũ.
- MFA bắt buộc cho admin; social login qua Auth0 giảm bề mặt tự quản mật khẩu.
- Callback chỉ nhận URL đích nội bộ (whitelist path), không redirect mở.
- Sentinel hash admin cũ (`admin123`) trong `login.php` bị loại bỏ hẳn.

## 10. Rollout & rollback

- **Rollout**: triển khai sau khi script migrate chạy xong và xác nhận vài tài khoản mẫu login được trên Auth0 (staging). Cột `password`/bảng `admins` giữ nguyên trong giai đoạn này.
- **Rollback**: vì bảng cũ + hash còn nguyên, revert code về nhánh `main` là khôi phục login cũ; không mất dữ liệu.
- **Dọn dẹp** (giai đoạn sau, spec riêng): xoá cột `password`, drop `admins`/`login_tokens`/`pending_registrations`, gỡ `verify-registration.php`.

## 11. Kiểm thử

Theo pattern repo (script standalone, `tests/bootstrap.php`, `assert_*`):
- `tests/auth_bridge_test.php`: resolve theo `auth0_id`; provision khi thiếu; khớp theo email rồi gán `auth0_id`; set đúng biến session; role admin set cờ admin.
- `tests/auth_migration_test.php`: build payload import đúng (bcrypt hash nguyên vẹn, admin có `app_metadata.role`); idempotent bỏ qua dòng đã có `auth0_id`.
- Xác thực luồng callback bằng mock profile (không gọi Auth0 thật trong unit test).
- Kiểm thử end-to-end login/logout/admin thủ công trên staging Auth0.

## 12. YAGNI / ngoài phạm vi lần này

- Không tự viết trang login embedded (đã chọn Universal Login).
- Không đồng bộ 2 chiều profile Auth0 ↔ users theo thời gian thực (chỉ provision lúc callback; sửa profile vẫn ở `account.php`).
- Không migrate `login_logs`/orders.
- Không làm SSO doanh nghiệp / SAML.
- Dọn dẹp schema (drop cột/bảng) tách thành công việc sau, không gộp vào lần này.
