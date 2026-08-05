# Gấu Bakery

Website bán bánh online cho tiệm **Gấu Bakery**. Dự án gồm **hai ứng dụng** trong cùng một repo:

1. **Web PHP** (thư mục gốc) — PHP 8.2 thuần + MySQL 8, không dùng framework. Bao gồm cả trang khách hàng (storefront) và trang quản trị (admin). Đây là ứng dụng chính.
2. **`ai-service/`** — một service Python FastAPI riêng, chatbot đa tác tử (multiagent) cho chăm sóc khách hàng: hỏi đáp FAQ/chính sách, tra cứu đơn, tạo đơn qua chat, chuyển tiếp nhân viên (handoff) và kênh Messenger. Deploy độc lập; web PHP gọi sang qua HTTP.

Hai ứng dụng được tách rời: web PHP proxy các yêu cầu chat sang AI service, còn AI service gọi ngược lại vào API nội bộ của web PHP để tạo đơn. Cả hai **dùng chung một database MySQL**.

## Tổng quan

Ứng dụng thương mại điện tử cho tiệm bánh, tập trung vào trải nghiệm đặt bánh trực tuyến. Frontend tổ chức theo từng trang PHP, mỗi trang tự xử lý request + hiển thị; logic dùng chung nằm trong `includes/`.

Các nhóm chức năng chính:

- Trang chủ hiển thị sản phẩm nổi bật, bán chạy, tìm kiếm nhanh và đánh giá khách hàng.
- Danh sách sản phẩm, chi tiết sản phẩm, ảnh gallery, đánh giá sản phẩm.
- Đăng nhập / đăng ký / quên mật khẩu qua **Auth0** (Universal Login, xác minh email do Auth0 gửi, hỗ trợ đăng nhập Google); hồ sơ tài khoản, lịch sử đơn hàng.
- Giỏ hàng, mã giảm giá (coupon), checkout, ghi chú đơn hàng.
- Thanh toán bằng tiền mặt (COD) và chuyển khoản QR tự động qua SePay.
- Danh sách yêu thích.
- Hóa đơn PDF gửi qua email khi thanh toán/xác nhận đơn thành công.
- Chatbot CSKH (AI): hỏi đáp chính sách, tra cứu đơn, tạo đơn qua chat, chuyển nhân viên.
- Trang chính sách, liên hệ, giới thiệu.
- Trang quản trị: sản phẩm, đơn hàng, khuyến mãi, coupon, review, liên hệ, người dùng, yêu cầu đổi mật khẩu, sản phẩm bán chạy, hội thoại chat, thống kê doanh thu và xuất Excel.

## Kiến trúc tổng thể

```text
Khách hàng ──▶  Web PHP (storefront + admin)  ──HTTP──▶  ai-service (FastAPI)
                     │        ▲                                │
                     │        └──── tạo đơn (API nội bộ) ──────┘
                     ▼                                         ▼
                        MySQL 8 (database dùng chung)
```

- **Base path `/cakev0/`** — ứng dụng chạy dưới đường dẫn con `/cakev0/`, nhiều URL được hardcode kèm tiền tố này (ví dụ `/cakev0/assets/...`, `/cakev0/api/internal/orders/create.php`). Khi thêm link/redirect/asset phải giữ tiền tố. Production (Render) cũng chạy layout này tại `https://cake-i8l0.onrender.com/cakev0/`.
- **Tích hợp chat**: `api/chat/*.php` là các endpoint mà widget chat gọi tới; chúng proxy sang AI service tại `AI_SERVICE_URL` (helper trong `includes/chat_proxy_helpers.php`).
- **API nội bộ tạo đơn**: `api/internal/orders/*` là nơi AI service gọi ngược để tạo đơn, bảo vệ bằng khóa dùng chung `INTERNAL_API_SECRET` (cùng giá trị ở hai phía). Xem `includes/internal_order_api.php`.
- **Xác thực Auth0**: đăng nhập/đăng ký/đăng xuất đi qua Auth0 Universal Login (OIDC). `pages/auth/login.php` chuyển hướng sang Auth0, `pages/auth/callback.php` đổi mã và đồng bộ phiên, `pages/auth/logout.php` đăng xuất. Auth0 giữ credential; bảng `users` liên kết qua cột `auth0_id`, quyền admin đọc từ claim role của Auth0. Callback chặn đăng nhập khi email chưa xác minh. Xem `includes/auth0.php`, `includes/auth_bridge.php`, `includes/auth0_management.php`.
- **Thanh toán SePay**: chỉ dùng SePay VietQR (kèm COD); trang QR ở `sepay/`, webhook `api/sepay/webhook.php` tự xác nhận đơn khi SePay ghi nhận giao dịch có nội dung `DH<order_id>`. (Đã bỏ VNPAY và chuyển khoản ngân hàng thủ công.)

Chi tiết riêng của AI service (engine multiagent, biến môi trường, eval...) xem [`ai-service/README.md`](ai-service/README.md).

## Công nghệ sử dụng

**Web PHP**

- PHP 8.2 + Apache
- MySQL 8
- HTML, CSS, JavaScript (vanilla)
- Composer
- Docker / Docker Compose
- PHPMailer (gửi email)
- dompdf (xuất hóa đơn PDF)
- PhpSpreadsheet (xuất báo cáo Excel)

**AI service**

- Python 3.10+ / FastAPI
- LangChain + LangGraph (engine multiagent)
- DeepSeek (chat LLM) + Gemini (embeddings)
- ChromaDB (vector store cho retrieval)

## Cấu trúc thư mục

```text
cakev0/
|-- admin/         # Trang quản trị (modular index.php + legacy admin.php)
|   |-- index.php  #   front controller: dispatch handlers + render views
|   |-- handlers/  #   xử lý POST / GET-delete theo domain
|   |-- views/     #   tabs / partials / components
|   `-- admin.php  #   admin monolith cũ (legacy, còn giữ)
|-- ai-service/    # Chatbot FastAPI (deploy riêng — xem README riêng)
|-- api/           # Endpoint AJAX: chat proxy, order nội bộ, giỏ hàng, thông báo
|-- assets/        # Ảnh, CSS, JS, upload chung
|-- config/        # bootstrap, config app, kết nối DB, coupons, uploadthing
|-- database/      # File SQL dump, migrations, backups
|-- docker/        # Cấu hình Apache cho Docker
|-- includes/      # header/footer, checkout, order, invoice, mail, auth, chat proxy...
|-- pages/         # Các trang người dùng
|-- tests/         # Test PHP (script độc lập)
|-- tools/         # Script chẩn đoán (mail, gmail, email provider & log Auth0)
|-- vendor/        # Composer dependencies
|-- sepay/         # Trang QR thanh toán SePay
|-- index.php      # Trang chủ
|-- Dockerfile
|-- docker-compose.yml
`-- render.yaml    # Blueprint Render cho ai-service
```

## Chức năng nổi bật

### Phía khách hàng

- Xem sản phẩm theo danh mục: bánh kem, bánh mặn, bánh mì, bánh ngọt.
- Tìm kiếm sản phẩm nhanh ngay trên trang chủ, xem sản phẩm bán chạy.
- Xem chi tiết sản phẩm, ảnh gallery, giá khuyến mãi, đánh giá.
- Thêm vào giỏ hàng, cập nhật số lượng, áp dụng coupon.
- Lưu sản phẩm yêu thích.
- Đăng nhập / đăng ký / quên mật khẩu qua Auth0 (Universal Login, xác minh email, đăng nhập Google), cập nhật thông tin cá nhân.
- Tạo đơn hàng và theo dõi trạng thái đơn.
- Thanh toán với SePay QR hoặc COD, nhận hóa đơn PDF qua email.
- Chat CSKH với trợ lý AI ngay trên web.

### Phía quản trị (admin/index.php)

- Thêm, sửa, xóa sản phẩm và ảnh sản phẩm; ẩn/hiện sản phẩm.
- Quản lý đơn hàng và cập nhật trạng thái hàng loạt.
- Quản lý khuyến mãi theo thời gian và mã coupon.
- Duyệt review khách hàng, quản lý sản phẩm bán chạy.
- Quản lý liên hệ từ form contact, xử lý yêu cầu đổi mật khẩu.
- Theo dõi người dùng và lịch sử đơn liên quan.
- Xem hội thoại chat CSKH.
- Thống kê và xuất báo cáo doanh thu ra file Excel.

## Cài đặt nhanh với Docker (khuyến nghị)

Đây là cách chạy thuận tiện nhất — chạy đủ cả 4 service (web, DB, phpMyAdmin, AI):

```bash
docker compose up --build
```

Sau khi chạy:

- Website: `http://localhost:8080/cakev0/` — lưu ý **base path `/cakev0/`**
- phpMyAdmin: `http://localhost:8081/`
- MySQL từ máy host: `127.0.0.1:3307`
- AI service: `http://localhost:8000` (`/health` để kiểm tra trạng thái)

Container DB tự import file `database/banh_store.sql` ở lần khởi tạo đầu tiên.

Trong mạng compose, web PHP gọi AI service qua service name `http://ai-service:8000`; AI service gọi ngược web PHP tại `http://app/cakev0/api/internal/orders/create.php`.

## Chạy thủ công không dùng Docker

1. Cài dependency PHP:

```bash
composer install
```

2. Tạo database MySQL tên `banh_store` và import dữ liệu mẫu:

```bash
mysql -u root -p banh_store < database/banh_store.sql
```

3. Cấu hình web server (Apache có bật `mod_rewrite`) trỏ vào thư mục project, phục vụ dưới đường dẫn `/cakev0/`.

4. Tạo file `.env` (và `.env.local` nếu cần) theo máy của bạn — xem phần dưới.

5. Chạy AI service riêng theo hướng dẫn trong [`ai-service/README.md`](ai-service/README.md).

## Biến môi trường (web PHP)

Web PHP đọc cấu hình từ `.env` rồi `.env.local` (xem `config/bootstrap.php`). Biến đặt sẵn trong môi trường thật (Docker/Render) sẽ được ưu tiên hơn `.env`.

Các biến chính:

```env
APP_ENV=
APP_DEBUG=
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_BASE_PATH=/cakev0
APP_ORIGIN=

DB_HOST=
DB_PORT=
DB_USER=
DB_PASS=
DB_NAME=
DB_CHARSET=utf8mb4

# Tích hợp AI service
AI_SERVICE_URL=
INTERNAL_API_SECRET=

# Xác thực Auth0 (Universal Login)
AUTH0_DOMAIN=
AUTH0_CLIENT_ID=
AUTH0_CLIENT_SECRET=
AUTH0_COOKIE_SECRET=
AUTH0_CALLBACK_URL=
AUTH0_LOGOUT_RETURN_URL=
# Management API (M2M) — gửi lại email xác minh, import user, cấu hình email provider
AUTH0_MGMT_CLIENT_ID=
AUTH0_MGMT_CLIENT_SECRET=

SEPAY_WEBHOOK_API_KEY=
SEPAY_ACCOUNT_NUMBER=
SEPAY_BANK_CODE=
SEPAY_ACCOUNT_NAME=

UPLOADTHING_API_KEY=
UPLOADTHING_APP_ID=

MAIL_DRIVER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
MAIL_TIMEOUT=300
MAIL_FORCE_IPV4=true
MAIL_DEBUG=false

# Chỉ dùng khi MAIL_DRIVER=gmail_api (xem bên dưới)
GMAIL_CLIENT_ID=
GMAIL_CLIENT_SECRET=
GMAIL_REFRESH_TOKEN=
GMAIL_USER_ID=me
```

> `AI_SERVICE_URL` phải trỏ tới AI service đang chạy — nếu không đặt, chức năng chat sẽ lỗi. `INTERNAL_API_SECRET` phải **trùng** với giá trị đặt ở AI service.

### Thanh toán SePay

- Webhook production: `https://cake-i8l0.onrender.com/cakev0/api/sepay/webhook.php`.
- SePay gửi header `Authorization: Apikey <SEPAY_WEBHOOK_API_KEY>`; endpoint từ chối nếu key trống hoặc sai.
- Khi test trên SePay, dùng "Mô phỏng chuyển khoản" với đúng số tiền và nội dung `DH<order_id>` để đơn tự chuyển sang `paid`.

### Mail trên Render qua Gmail API

Nếu SMTP Gmail trên Render bị timeout ở port 587, dùng driver HTTP:

```env
MAIL_DRIVER=gmail_api
MAIL_FROM_ADDRESS=your-gmail@gmail.com
MAIL_FROM_NAME=Gau Bakery
GMAIL_CLIENT_ID=...
GMAIL_CLIENT_SECRET=...
GMAIL_REFRESH_TOKEN=...
GMAIL_USER_ID=me
```

`MAIL_FROM_ADDRESS` phải là tài khoản Gmail đã cấp refresh token, hoặc một alias gửi mail đã được Gmail xác minh. Scope OAuth cần quyền `gmail.send`. Cấu hình SMTP vẫn được giữ lại để chạy local bằng `MAIL_DRIVER=smtp`.

## Cơ sở dữ liệu

Database local mặc định là `banh_store` (production trên Aiven là `defaultdb` — đừng hardcode tên DB). File dump: `database/banh_store.sql`.

Một số bảng chính (tên miền nghiệp vụ bằng tiếng Việt):

- `users` (có cột `auth0_id` liên kết tài khoản Auth0), `admins`
- `banh` (sản phẩm/bánh), `product_images`, `product_reviews`
- `cart`, `cart_coupons`, `favorites`
- `orders`, `order_items`
- `promotions`, `reviews`, `contact_requests`
- `login_logs`; `password_reset_requests`, `login_tokens` (còn giữ từ luồng auth local cũ, nay credential do Auth0 quản lý)

## Chạy test

**PHP** — test là các **script độc lập** (không phải PHPUnit), chạy trực tiếp từng file, exit khác 0 khi fail. Không có runner tổng — chạy từng file một:

```bash
php tests/auth_helpers_test.php
```

`tests/bootstrap.php` cung cấp helper `assert_true()` / `assert_same()` và nạp `config/bootstrap.php`. Test pass in ra `... ok`.

**AI service** — dùng pytest:

```bash
cd ai-service && pytest
pytest tests/test_router.py            # một file
```

## Bảo mật hiện có

- Xác thực do **Auth0** đảm nhận (Universal Login): Auth0 giữ và băm mật khẩu, áp chính sách mật khẩu và phát hiện mật khẩu rò rỉ (Breached Password Detection), gửi email xác minh và email đặt lại mật khẩu. Ứng dụng không lưu mật khẩu người dùng (cột `users.password` để trống).
- Đăng nhập bị chặn cho tới khi email được xác minh: `pages/auth/callback.php` kiểm tra claim `email_verified`, chưa xác minh thì không tạo phiên và chuyển sang trang nhắc xác minh (có nút gửi lại email xác minh qua Auth0 Management API).
- Hóa đơn PDF gửi qua email sau khi đơn SePay thanh toán thành công hoặc khi admin xác nhận đơn COD; mỗi đơn chỉ gửi hóa đơn một lần (theo dõi bằng `orders.invoice_email_sent_at`).
- Luồng OIDC dùng state/nonce + cookie mã hóa của Auth0 SDK; phiên ứng dụng được tạo từ callback sau khi đổi mã thành công.
- Chống CSRF cho các form quan trọng còn lại (thao tác admin) bằng token sinh từ `random_bytes()`, kiểm tra với `hash_equals()`. Admin modular bổ sung kiểm tra CSRF cho cả các link GET xóa (`&csrf=<token>`) — vốn thiếu ở admin legacy.
- Chống SQL Injection bằng prepared statement (`prepare()` + `bind_param()`).
- Phân quyền admin: role đọc từ claim của Auth0, ánh xạ vào session `admin_logged_in` và `role = admin`.
- Escape output bằng `htmlspecialchars()` để giảm nguy cơ XSS.
- Kiểm tra định dạng email bằng `filter_var(..., FILTER_VALIDATE_EMAIL)`.
- Ghi nhật ký đăng nhập qua bảng `login_logs`.
- Giới hạn upload ảnh theo phần mở rộng cho phép (`jpg`, `jpeg`, `png`, `webp`), lưu bằng tên file ngẫu nhiên.

Lưu ý: đây là mô tả cơ chế đang có trong mã nguồn, chưa phải mức hardening hoàn chỉnh. Vẫn nên tăng cường thêm: cookie `Secure`/`SameSite`, rate limiting cho đăng nhập, CSP/security headers, kiểm tra MIME upload chặt hơn.

## Điểm vào chính của ứng dụng

- Trang chủ: `index.php`
- Khu vực khách hàng: `pages/`
- Khu vực quản trị: `admin/index.php` (modular, ưu tiên) — `admin/admin.php` là bản legacy còn giữ lại
- Thanh toán SePay: `sepay/` và `api/sepay/`
- Chatbot AI: `ai-service/` (service riêng), web gọi qua `api/chat/`

## Deploy

- **Web PHP**: Render (runtime Docker, `cake-i8l0.onrender.com`), tạo/quản lý **thủ công** trên dashboard (KHÔNG nằm trong `render.yaml`). Cần đặt `AI_SERVICE_URL` + `INTERNAL_API_SECRET`, kết nối MySQL production, SePay, UploadThing, và mail (Gmail API trên Render).
- **AI service**: dùng Blueprint `render.yaml` (`gau-bakery-ai`, Singapore, free plan). Chi tiết env xem `ai-service/README.md`.
- **Database**: Aiven MySQL (`defaultdb`, bật SSL).

## Link Website Demo

https://cake-i8l0.onrender.com/cakev0/
