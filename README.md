# Gấu Bakery Web

Website bán bánh online viết bằng PHP thuần, phục vụ cả phía khách hàng và quản trị viên. Dự án hỗ trợ danh mục sản phẩm, giỏ hàng, yêu thích, đặt hàng, theo dõi đơn, thanh toán qua VNPAY, gửi email và quản trị nội dung ngay trong trang `admin/admin.php`.

## Tổng quan

Project này là một ứng dụng web thương mại điện tử cho tiệm bánh, tập trung vào trải nghiệm đặt bánh trực tuyến. Phần frontend được tổ chức theo từng trang PHP, còn backend xử lý trực tiếp bằng PHP + MySQL, không dùng framework lớn.

Các nhóm chức năng chính:

- Trang chủ hiển thị sản phẩm nổi bật, tìm kiếm nhanh và đánh giá khách hàng.
- Danh sách sản phẩm, chi tiết sản phẩm, hình ảnh phụ, đánh giá sản phẩm.
- Đăng ký, đăng nhập, quên mật khẩu, hồ sơ tài khoản, lịch sử đơn hàng.
- Giỏ hàng, mã giảm giá, checkout, ghi chú đơn hàng.
- Thanh toán bằng tiền mặt, chuyển khoản và VNPAY Sandbox.
- Danh sách yêu thích.
- Trang chính sách, liên hệ, giới thiệu.
- Trang quản trị: sản phẩm, đơn hàng, khuyến mãi, review, liên hệ, người dùng, thống kê doanh thu và xuất Excel.

## Công nghệ sử dụng

- PHP 8.2 + Apache
- MySQL 8
- HTML, CSS, JavaScript
- Composer
- Docker / Docker Compose
- PHPMailer
- UploadThing PHP SDK
- PhpSpreadsheet

## Cấu trúc thư mục

```text
cakev0/
|-- admin/         # Trang quản trị
|-- assets/        # Ảnh, CSS, JS, upload chung
|-- config/        # Bootstrap, config app, kết nối DB, UploadThing
|-- database/      # File SQL dump, migrations, backups
|-- docker/        # Cấu hình Apache cho Docker
|-- includes/      # Header, footer, mail helper
|-- pages/         # Các trang người dùng
|-- vendor/        # Composer dependencies
|-- vnpay/         # Tích hợp VNPAY
|-- index.php      # Trang chủ
|-- Dockerfile
|-- docker-compose.yml
|-- render.yaml
```

## Chức năng nổi bật

### Phía khách hàng

- Xem sản phẩm theo danh mục: bánh kem, bánh mặn, bánh mì, bánh ngọt.
- Tìm kiếm sản phẩm nhanh ngay trên trang chủ.
- Xem chi tiết sản phẩm, ảnh gallery, giá khuyến mãi, đánh giá.
- Thêm vào giỏ hàng, cập nhật số lượng, áp dụng coupon.
- Lưu sản phẩm yêu thích.
- Đăng ký, đăng nhập, quên mật khẩu, cập nhật thông tin cá nhân.
- Tạo đơn hàng và theo dõi trạng thái đơn.
- Thanh toán với VNPAY Sandbox hoặc COD.

### Phía quản trị

- Thêm, sửa, xóa sản phẩm và ảnh sản phẩm.
- Quản lý đơn hàng và cập nhật trạng thái hàng loạt.
- Quản lý khuyến mãi theo thời gian.
- Duyệt review khách hàng.
- Quản lý liên hệ từ form contact.
- Theo dõi người dùng và lịch sử đơn liên quan.
- Xuất báo cáo doanh thu ra file Excel.

## Cơ sở dữ liệu

Database mặc định là `banh_store`. File dump nằm tại:

```text
database/banh_store.sql
```

Một số bảng chính:

- `users`
- `admins`
- `banh`
- `product_images`
- `product_reviews`
- `cart`
- `cart_coupons`
- `favorites`
- `orders`
- `order_items`
- `promotions`
- `reviews`
- `contact_requests`
- `password_reset_requests`
- `login_logs`
- `login_tokens`

## Yêu cầu môi trường

- PHP 8.2 trở lên
- MySQL 8.0
- Composer
- Apache có bật `mod_rewrite`
- Docker Desktop nếu muốn chạy bằng container

## Cài đặt nhanh với Docker

Đây là cách chạy thuận tiện nhất vì repo đã có sẵn `Dockerfile` và `docker-compose.yml`.

```bash
docker compose up --build
```

Sau khi chạy:

- Website: `http://localhost:8080/cakev0/`
- phpMyAdmin: `http://localhost:8081/`
- MySQL host từ máy local: `127.0.0.1:3307`

Container DB sẽ tự import file `database/banh_store.sql` ở lần khởi tạo đầu tiên.

## Chạy thủ công không dùng Docker

1. Cài dependency PHP:

```bash
composer install
```

2. Tạo database MySQL tên `banh_store`.

3. Import dữ liệu mẫu:

```bash
mysql -u root -p banh_store < database/banh_store.sql
```

4. Cấu hình web server trỏ vào thư mục project.

5. Bật `mod_rewrite` nếu dùng Apache.

6. Cập nhật file `.env` theo máy của bạn.

## Biến môi trường cần cấu hình

Project đọc cấu hình từ `.env` và `.env.local`.

Các biến chính:

```env
APP_ENV=
APP_DEBUG=
APP_TIMEZONE=
APP_BASE_PATH=
APP_ORIGIN=

DB_HOST=
DB_PORT=
DB_USER=
DB_PASS=
DB_NAME=
DB_CHARSET=

VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_URL=
VNPAY_RETURN_URL=
VNPAY_MERCHANT_URL=
VNPAY_TRANSACTION_API_URL=

UPLOADTHING_API_KEY=
UPLOADTHING_APP_ID=

MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
```

## Điểm vào chính của ứng dụng

- Trang chủ: `index.php`
- Khu vực khách hàng: `pages/`
- Khu vực quản trị: `admin/admin.php`
- Thanh toán VNPAY: `vnpay/`

## Deploy

Repo đã có sẵn `render.yaml` để deploy bằng Render với runtime Docker. Khi deploy production, cần cấu hình lại:

- `APP_ORIGIN`
- Kết nối MySQL production
- Thông tin VNPAY thật hoặc sandbox tùy môi trường
- UploadThing credentials
- SMTP credentials

## Ghi chú phát triển

- Project dùng PHP thuần nên logic giao diện và xử lý request đang nằm trực tiếp trong nhiều file `.php`.
- `vendor/` hiện đã có trong repo, nhưng vẫn nên chạy `composer install` để đồng bộ dependency.
- Các file upload được dùng trong `assets/uploads/` và `pages/uploads/`.
- Hệ thống đang có hỗ trợ xuất Excel thông qua `phpoffice/phpspreadsheet`.

## Hướng cải thiện đề xuất

- Tách logic xử lý khỏi file view để dễ bảo trì hơn.
- Thêm `.env.example` để onboarding nhanh hơn.
- Bổ sung test cho luồng checkout và thanh toán.
- Chuẩn hóa router và helper dùng chung để giảm lặp code.
- Tách phần admin thành module riêng nếu project tiếp tục mở rộng.

## Tác giả

Bạn có thể cập nhật thêm tên tác giả, thông tin liên hệ, link demo hoặc ảnh chụp màn hình vào phần này khi cần.
