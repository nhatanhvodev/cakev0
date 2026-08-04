# SePay Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thay hoàn toàn 2 phương thức `VNPAY` + `Chuyển khoản` thủ công bằng 1 luồng SePay QR tự động (webhook + auto-poll), giữ COD.

**Architecture:** Checkout tạo đơn `pending` rồi redirect sang trang QR SePay; trang auto-poll trạng thái. SePay bắn webhook khi tiền vào → server verify + khớp đơn theo nội dung `DH<id>` → `markOrderPaid`. Logic xử lý gom vào `includes/sepay_helpers.php` (thuần, test được không cần HTTP); các file HTTP chỉ wiring.

**Tech Stack:** PHP 8.2, MySQL 8 (mysqli), không framework. Test standalone (`tests/*_test.php`, `assert_true`/`assert_same`). SePay VietQR (`qr.sepay.vn/img`) + webhook JSON.

## Global Constraints

- Giữ base path `/cakev0/` trong mọi URL/redirect/asset.
- DB access dùng prepared statements; escape output `htmlspecialchars()`.
- Chuỗi user-facing + comment + commit bằng tiếng Việt.
- Không đổi schema cũ; bảng mới tạo qua ensure-function (production Aiven không chạy migration tay).
- Bảo mật: webhook verify API key `hash_equals` **fail-closed** khi env rỗng; verify `transferAmount >= total_amount`; idempotent theo `sepay_id`; endpoint trạng thái chỉ trả cho chủ đơn (`user_id` khớp session).
- Mã nội dung CK: `DH<order_id>`. QR hết hạn: 15 phút.
- Không thêm dependency Composer mới.

---

### Task 1: Helper thuần SePay (config, mã nội dung, tách order id, verify key, dựng QR)

**Files:**
- Create: `includes/sepay_helpers.php`
- Test: `tests/sepay_helpers_test.php`

**Interfaces:**
- Consumes: `env_value()` từ `config/bootstrap.php` (đã load qua `tests/bootstrap.php`).
- Produces:
  - `sepay_config(): array` → `['api_key'=>string,'account'=>string,'bank'=>string,'name'=>string]`
  - `sepay_payment_content(int $orderId): string` → `"DH{$orderId}"`
  - `sepay_extract_order_id(string $content, ?string $code = null): ?int`
  - `sepay_verify_api_key(?string $authHeader, string $expectedKey): bool`
  - `sepay_build_qr_url(array $cfg, int $orderId, int $amount): string`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/sepay_helpers_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

// payment content
assert_same('DH69', sepay_payment_content(69), 'content DH69');

// extract order id — content thuần
assert_same(69, sepay_extract_order_id('DH69', null), 'extract DH69');
// lẫn text + số 0 đệm
assert_same(69, sepay_extract_order_id('Thanh toan don hang DH0069 gau bakery', null), 'extract lẫn text');
// ưu tiên field code
assert_same(42, sepay_extract_order_id('noi dung linh tinh', 'DH42'), 'ưu tiên code');
// rác → null
assert_same(null, sepay_extract_order_id('chuyen tien an trua', null), 'không khớp → null');

// verify api key
assert_true(sepay_verify_api_key('Apikey SECRET123', 'SECRET123'), 'key đúng');
assert_true(!sepay_verify_api_key('Apikey SAI', 'SECRET123'), 'key sai');
assert_true(!sepay_verify_api_key('Apikey SECRET123', ''), 'expected rỗng → fail-closed');
assert_true(!sepay_verify_api_key(null, 'SECRET123'), 'header null → false');
assert_true(!sepay_verify_api_key('SECRET123', 'SECRET123'), 'thiếu tiền tố Apikey → false');

// build qr url
$cfg = ['api_key'=>'k','account'=>'0123456789','bank'=>'MBBank','name'=>'GAU BAKERY'];
$url = sepay_build_qr_url($cfg, 69, 250000);
assert_true(str_contains($url, 'acc=0123456789'), 'qr có acc');
assert_true(str_contains($url, 'bank=MBBank'), 'qr có bank');
assert_true(str_contains($url, 'amount=250000'), 'qr có amount');
assert_true(str_contains($url, 'des=DH69'), 'qr có des DH69');

echo "sepay_helpers_test ... ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/sepay_helpers_test.php`
Expected: FAIL (fatal — `sepay_payment_content()` chưa tồn tại).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// includes/sepay_helpers.php

if (!function_exists('sepay_config')) {
    function sepay_config(): array
    {
        return [
            'api_key' => (string) env_value('SEPAY_WEBHOOK_API_KEY', ''),
            'account' => (string) env_value('SEPAY_ACCOUNT_NUMBER', ''),
            'bank'    => (string) env_value('SEPAY_BANK_CODE', ''),
            'name'    => (string) env_value('SEPAY_ACCOUNT_NAME', ''),
        ];
    }
}

if (!function_exists('sepay_payment_content')) {
    function sepay_payment_content(int $orderId): string
    {
        return 'DH' . $orderId;
    }
}

if (!function_exists('sepay_extract_order_id')) {
    function sepay_extract_order_id(string $content, ?string $code = null): ?int
    {
        foreach ([$code, $content] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (preg_match('/DH0*(\d+)/i', $candidate, $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }
}

if (!function_exists('sepay_verify_api_key')) {
    function sepay_verify_api_key(?string $authHeader, string $expectedKey): bool
    {
        if ($expectedKey === '' || $authHeader === null) {
            return false;
        }
        $prefix = 'Apikey ';
        if (strncmp($authHeader, $prefix, strlen($prefix)) !== 0) {
            return false;
        }
        $provided = substr($authHeader, strlen($prefix));
        return hash_equals($expectedKey, $provided);
    }
}

if (!function_exists('sepay_build_qr_url')) {
    function sepay_build_qr_url(array $cfg, int $orderId, int $amount): string
    {
        $params = http_build_query([
            'acc'    => $cfg['account'] ?? '',
            'bank'   => $cfg['bank'] ?? '',
            'amount' => $amount,
            'des'    => sepay_payment_content($orderId),
        ]);
        return 'https://qr.sepay.vn/img?' . $params;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/sepay_helpers_test.php`
Expected: `sepay_helpers_test ... ok`

> Lưu ý: `http_build_query` mã hoá `des=DH69` nguyên trạng (chữ+số không bị encode); assert `str_contains('des=DH69')` đúng.

- [ ] **Step 5: Commit**

```bash
git add includes/sepay_helpers.php tests/sepay_helpers_test.php
git commit -m "feat(sepay): helper thuần config/mã nội dung/tách order id/verify key/QR"
```

---

### Task 2: `ensureSepayInfrastructure` + `markOrderPaid` (logic DB, gom từ VNPAY return)

**Files:**
- Modify: `includes/sepay_helpers.php` (thêm 2 hàm)
- Test: `tests/sepay_mark_paid_test.php`

**Interfaces:**
- Consumes: `$conn` (mysqli) từ `config/connect.php`; `incrementCouponUsage()` (`config/coupons.php`), `notifyOrderStatusChanged()` (`includes/notifications.php`), `send_order_invoice_email()` (`includes/invoice_mailer.php`).
- Produces:
  - `ensureSepayInfrastructure(mysqli $conn): void`
  - `markOrderPaid(mysqli $conn, int $orderId): array` → `['changed'=>bool,'previous'=>string]`
  - `sepay_order_status(mysqli $conn, int $orderId, int $userId): ?string`

- [ ] **Step 1: Write the failing test** (cần DB local `banh_store`)

```php
<?php
// tests/sepay_mark_paid_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

ensureSepayInfrastructure($conn);

// tạo đơn pending tối thiểu
$conn->query("INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, created_at) VALUES (0,'Test','0','x','','SePay',250000,NULL,0,'pending',NOW())");
$orderId = (int) $conn->insert_id;

// lần 1: pending → paid
$r1 = markOrderPaid($conn, $orderId);
assert_same(true, $r1['changed'], 'lần 1 chuyển paid');
assert_same('pending', $r1['previous'], 'previous = pending');

$status = sepay_order_status($conn, $orderId, 0);
assert_same('paid', $status, 'trạng thái paid, đúng chủ đơn');

// idempotent: lần 2 không đổi
$r2 = markOrderPaid($conn, $orderId);
assert_same(false, $r2['changed'], 'lần 2 không đổi (idempotent)');

// ownership: sai user_id → null
assert_same(null, sepay_order_status($conn, $orderId, 99999), 'sai chủ đơn → null');

// dọn
$conn->query("DELETE FROM orders WHERE id = " . $orderId);

echo "sepay_mark_paid_test ... ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/sepay_mark_paid_test.php`
Expected: FAIL (`ensureSepayInfrastructure()` chưa tồn tại).

- [ ] **Step 3: Write minimal implementation** (thêm vào `includes/sepay_helpers.php`)

```php
if (!function_exists('ensureSepayInfrastructure')) {
    function ensureSepayInfrastructure(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS sepay_transactions (
                sepay_id   VARCHAR(50) PRIMARY KEY,
                order_id   INT NOT NULL,
                amount     BIGINT NOT NULL,
                content    VARCHAR(255) NULL,
                raw        TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('sepay_order_status')) {
    function sepay_order_status(mysqli $conn, int $orderId, int $userId): ?string
    {
        $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $orderId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (string) $row['status'] : null;
    }
}

if (!function_exists('markOrderPaid')) {
    function markOrderPaid(mysqli $conn, int $orderId): array
    {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT user_id, coupon_code, status FROM orders WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                $conn->rollback();
                return ['changed' => false, 'previous' => ''];
            }

            $previousStatus = (string) $order['status'];
            if ($previousStatus === 'paid') {
                $conn->rollback();
                return ['changed' => false, 'previous' => 'paid'];
            }

            $userId = (int) ($order['user_id'] ?? 0);
            $couponCode = (string) ($order['coupon_code'] ?? '');

            $stmt = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            // trừ giỏ theo item (giống VNPAY cũ) — chỉ khi đơn thuộc user thật
            if ($userId > 0) {
                $stmt = $conn->prepare("SELECT banh_id, quantity FROM order_items WHERE order_id = ?");
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $upd = $conn->prepare("UPDATE cart SET quantity = quantity - ? WHERE user_id = ? AND banh_id = ?");
                $del = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND banh_id = ? AND quantity <= 0");
                foreach ($items as $item) {
                    $qty = (int) $item['quantity'];
                    $banhId = (int) $item['banh_id'];
                    $upd->bind_param("iii", $qty, $userId, $banhId);
                    $upd->execute();
                    $del->bind_param("ii", $userId, $banhId);
                    $del->execute();
                }
                $upd->close();
                $del->close();
            }

            if ($couponCode !== '') {
                incrementCouponUsage($conn, $couponCode);
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return ['changed' => false, 'previous' => ''];
        }

        // side-effect ngoài transaction
        notifyOrderStatusChanged($conn, $userId, $orderId, $previousStatus, 'paid');
        if (!send_order_invoice_email($conn, $orderId)) {
            error_log('SePay: gửi hóa đơn thất bại cho đơn #' . $orderId);
        }

        return ['changed' => true, 'previous' => $previousStatus];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/sepay_mark_paid_test.php`
Expected: `sepay_mark_paid_test ... ok`
(Cần MySQL local `banh_store` chạy. Nếu môi trường không có DB, chạy trong Docker: `docker compose exec web php tests/sepay_mark_paid_test.php`.)

- [ ] **Step 5: Commit**

```bash
git add includes/sepay_helpers.php tests/sepay_mark_paid_test.php
git commit -m "feat(sepay): ensureSepayInfrastructure + markOrderPaid idempotent + trạng thái đơn"
```

---

### Task 3: Core xử lý webhook (thuần, không HTTP) + wiring `api/sepay/webhook.php`

**Files:**
- Modify: `includes/sepay_helpers.php` (thêm `sepay_process_webhook`)
- Create: `api/sepay/webhook.php`
- Test: `tests/sepay_webhook_test.php`

**Interfaces:**
- Consumes: `sepay_verify_api_key`, `sepay_extract_order_id`, `markOrderPaid`, `ensureSepayInfrastructure`.
- Produces:
  - `sepay_process_webhook(mysqli $conn, array $payload, ?string $authHeader, array $cfg): array` → `['code'=>int,'body'=>array]`. Quy tắc:
    - sai key → `['code'=>401,...]`
    - `transferType != 'in'` → `['code'=>200,'body'=>['skipped'=>'not_incoming']]`
    - không tách được order id → `200 ['skipped'=>'no_order']`
    - trùng `sepay_id` → `200 ['skipped'=>'duplicate']`
    - đơn không tồn tại → `200 ['skipped'=>'order_not_found']`
    - thiếu tiền (`transferAmount < total`) → `200 ['skipped'=>'amount_mismatch']`
    - hợp lệ → ghi `sepay_transactions`, `markOrderPaid`, `200 ['success'=>true,'order_id'=>N]`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/sepay_webhook_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

ensureSepayInfrastructure($conn);
$cfg = ['api_key'=>'K123','account'=>'0123','bank'=>'MBBank','name'=>'GAU'];

$conn->query("INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, created_at) VALUES (0,'Test','0','x','','SePay',250000,NULL,0,'pending',NOW())");
$orderId = (int) $conn->insert_id;
$conn->query("DELETE FROM sepay_transactions WHERE order_id = " . $orderId);

$base = [
    'id' => 'TXN-' . $orderId,
    'transferType' => 'in',
    'transferAmount' => 250000,
    'content' => 'DH' . $orderId,
    'code' => null,
];

// sai key → 401
$r = sepay_process_webhook($conn, $base, 'Apikey WRONG', $cfg);
assert_same(401, $r['code'], 'sai key → 401');

// transferType out → skip
$out = $base; $out['transferType'] = 'out';
$r = sepay_process_webhook($conn, $out, 'Apikey K123', $cfg);
assert_same(200, $r['code'], 'out → 200');
assert_same('not_incoming', $r['body']['skipped'], 'out → skip');

// thiếu tiền → không paid
$low = $base; $low['transferAmount'] = 100000;
$r = sepay_process_webhook($conn, $low, 'Apikey K123', $cfg);
assert_same('amount_mismatch', $r['body']['skipped'], 'thiếu tiền → skip');
assert_same('pending', sepay_order_status($conn, $orderId, 0), 'vẫn pending');

// hợp lệ → paid
$r = sepay_process_webhook($conn, $base, 'Apikey K123', $cfg);
assert_same(200, $r['code'], 'hợp lệ → 200');
assert_same(true, $r['body']['success'], 'success');
assert_same('paid', sepay_order_status($conn, $orderId, 0), 'đã paid');

// trùng sepay_id → duplicate
$r = sepay_process_webhook($conn, $base, 'Apikey K123', $cfg);
assert_same('duplicate', $r['body']['skipped'], 'trùng id → skip');

$conn->query("DELETE FROM sepay_transactions WHERE order_id = " . $orderId);
$conn->query("DELETE FROM orders WHERE id = " . $orderId);
echo "sepay_webhook_test ... ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/sepay_webhook_test.php`
Expected: FAIL (`sepay_process_webhook()` chưa tồn tại).

- [ ] **Step 3: Write minimal implementation** (thêm vào `includes/sepay_helpers.php`)

```php
if (!function_exists('sepay_process_webhook')) {
    function sepay_process_webhook(mysqli $conn, array $payload, ?string $authHeader, array $cfg): array
    {
        if (!sepay_verify_api_key($authHeader, (string) ($cfg['api_key'] ?? ''))) {
            return ['code' => 401, 'body' => ['error' => 'unauthorized']];
        }

        $transferType = (string) ($payload['transferType'] ?? '');
        if ($transferType !== 'in') {
            return ['code' => 200, 'body' => ['skipped' => 'not_incoming']];
        }

        $content = (string) ($payload['content'] ?? '');
        $code = isset($payload['code']) ? (string) $payload['code'] : null;
        $orderId = sepay_extract_order_id($content, $code);
        if ($orderId === null) {
            return ['code' => 200, 'body' => ['skipped' => 'no_order']];
        }

        $sepayId = (string) ($payload['id'] ?? '');
        $amount = (int) ($payload['transferAmount'] ?? 0);

        ensureSepayInfrastructure($conn);

        // chống trùng: insert trước, trùng key → bỏ qua
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO sepay_transactions(sepay_id, order_id, amount, content, raw) VALUES (?, ?, ?, ?, ?)"
        );
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param("sisss", $sepayId, $orderId, $amount, $content, $raw);
        $stmt->execute();
        $inserted = $stmt->affected_rows;
        $stmt->close();
        if ($inserted === 0) {
            return ['code' => 200, 'body' => ['skipped' => 'duplicate']];
        }

        // đọc tổng tiền đơn
        $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            return ['code' => 200, 'body' => ['skipped' => 'order_not_found']];
        }

        if ($amount < (int) round((float) $order['total_amount'])) {
            return ['code' => 200, 'body' => ['skipped' => 'amount_mismatch']];
        }

        markOrderPaid($conn, $orderId);
        return ['code' => 200, 'body' => ['success' => true, 'order_id' => $orderId]];
    }
}
```

> Lưu ý idempotency: dedupe bằng `INSERT IGNORE` chạy TRƯỚC khi `markOrderPaid`. Với case `amount_mismatch` bản ghi đã insert → webhook retry cùng id sẽ `duplicate`. Chấp nhận (thiếu tiền coi như đã ghi nhận, admin xử lý tay). Nếu muốn cho retry sau khi khách chuyển bù, xoá dòng `sepay_transactions` đó — ghi chú trong README.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/sepay_webhook_test.php`
Expected: `sepay_webhook_test ... ok`

- [ ] **Step 5: Write wiring `api/sepay/webhook.php`**

```php
<?php
// api/sepay/webhook.php — nhận webhook SePay, không giao diện
require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../config/coupons.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/invoice_mailer.php';
require_once __DIR__ . '/../../includes/sepay_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// header Authorization (một số server để HTTP_AUTHORIZATION)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

$result = sepay_process_webhook($conn, $payload, $authHeader, sepay_config());
http_response_code($result['code']);
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 6: Verify wiring bằng curl** (server local hoặc Docker)

Run:
```bash
curl -s -X POST http://localhost:8080/cakev0/api/sepay/webhook.php \
  -H "Authorization: Apikey WRONG" -H "Content-Type: application/json" \
  -d '{"id":"t1","transferType":"in","transferAmount":1000,"content":"DH1"}' -w "\n%{http_code}\n"
```
Expected: body `{"error":"unauthorized"}` + `401`.

- [ ] **Step 7: Commit**

```bash
git add includes/sepay_helpers.php api/sepay/webhook.php tests/sepay_webhook_test.php
git commit -m "feat(sepay): xử lý webhook (verify/khớp đơn/chống trùng/verify tiền) + endpoint"
```

---

### Task 4: Endpoint trạng thái `api/sepay/status.php` (cho auto-poll)

**Files:**
- Create: `api/sepay/status.php`

**Interfaces:**
- Consumes: `sepay_order_status()` (Task 2), session `$_SESSION['user_id']`.
- Produces: JSON `{"status":"pending|paid|failed"}` hoặc `404`.

- [ ] **Step 1: Write wiring**

```php
<?php
// api/sepay/status.php — trả trạng thái đơn cho poll, chỉ chủ đơn
session_start();
require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../includes/sepay_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$orderId = (int) ($_GET['order'] ?? 0);
if ($userId <= 0 || $orderId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$status = sepay_order_status($conn, $orderId, $userId);
if ($status === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode(['status' => $status]);
```

- [ ] **Step 2: Verify chưa đăng nhập → 401**

Run:
```bash
curl -s "http://localhost:8080/cakev0/api/sepay/status.php?order=1" -w "\n%{http_code}\n"
```
Expected: `{"error":"unauthorized"}` + `401`.

- [ ] **Step 3: Commit**

```bash
git add api/sepay/status.php
git commit -m "feat(sepay): endpoint trạng thái đơn cho auto-poll (chỉ chủ đơn)"
```

---

### Task 5: Trang QR `sepay/payment.php` + auto-poll

**Files:**
- Create: `sepay/payment.php`

**Interfaces:**
- Consumes: `sepay_config()`, `sepay_build_qr_url()`, `sepay_payment_content()`, `sepay_order_status()`; poll `/cakev0/api/sepay/status.php`.

- [ ] **Step 1: Viết trang** (đăng nhập bắt buộc, chỉ chủ đơn, hết hạn 15 phút)

```php
<?php
// sepay/payment.php — hiện VietQR + auto-poll trạng thái
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /cakev0/pages/login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order'] ?? 0);

$stmt = $conn->prepare("SELECT total_amount, status, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo 'Không tìm thấy đơn hàng.';
    exit;
}

$cfg = sepay_config();
$amount = (int) round((float) $order['total_amount']);
$qrUrl = sepay_build_qr_url($cfg, $orderId, $amount);
$content = sepay_payment_content($orderId);
$status = (string) $order['status'];
$expireTs = strtotime($order['created_at']) + 15 * 60; // hết hạn 15 phút
$remaining = max(0, $expireTs - time());
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán QR (SePay) | Gấu Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:#fff7ef;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
        .card{background:#fff;padding:32px;border-radius:20px;box-shadow:0 18px 40px rgba(74,29,31,.12);text-align:center;max-width:420px;width:90%}
        h2{color:#4a1d1f;margin:0 0 4px}
        .qr{width:240px;height:240px;object-fit:contain;margin:12px auto;border:1px solid #eee;border-radius:12px}
        .row{display:flex;justify-content:space-between;font-size:14px;color:#4a1d1f;margin:6px 0}
        .row b{color:#c44536}
        .muted{font-size:12px;color:#777}
        .btn{display:inline-block;padding:12px 25px;background:#6a2d22;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;margin-top:16px}
        .ok{color:#28a745;font-size:56px}
        #paidBox{display:none}
        #expireBox{display:none}
    </style>
</head>
<body>
    <div class="card">
        <div id="pendingBox">
            <h2>Quét mã để thanh toán</h2>
            <p class="muted">Đơn hàng #<?= (int) $orderId ?></p>
            <img class="qr" src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR SePay">
            <div class="row"><span>Ngân hàng</span><span><?= htmlspecialchars($cfg['bank']) ?></span></div>
            <div class="row"><span>Số tài khoản</span><span><?= htmlspecialchars($cfg['account']) ?></span></div>
            <div class="row"><span>Chủ tài khoản</span><span><?= htmlspecialchars($cfg['name']) ?></span></div>
            <div class="row"><span>Nội dung</span><span><b><?= htmlspecialchars($content) ?></b></span></div>
            <div class="row"><span>Số tiền</span><b><?= number_format($amount, 0, ',', '.') ?> VNĐ</b></div>
            <p class="muted">Chuyển đúng nội dung <b><?= htmlspecialchars($content) ?></b> — đơn tự xác nhận. Còn <span id="countdown"></span></p>
        </div>
        <div id="paidBox">
            <div class="ok">✅</div>
            <h2>Thanh toán thành công!</h2>
            <p>Đơn hàng #<?= (int) $orderId ?> đã được ghi nhận.</p>
            <a class="btn" href="/cakev0/index.php">Về trang chủ</a>
        </div>
        <div id="expireBox">
            <h2>QR đã hết hạn</h2>
            <p class="muted">Vui lòng đặt lại đơn để lấy mã mới.</p>
            <a class="btn" href="/cakev0/pages/cart.php">Về giỏ hàng</a>
        </div>
    </div>
    <script>
        var orderId = <?= (int) $orderId ?>;
        var remaining = <?= (int) $remaining ?>;
        var initialStatus = <?= json_encode($status) ?>;
        var pending = document.getElementById('pendingBox');
        var paid = document.getElementById('paidBox');
        var expired = document.getElementById('expireBox');
        var countdown = document.getElementById('countdown');
        var timer = null, poll = null;

        function showPaid(){ pending.style.display='none'; expired.style.display='none'; paid.style.display='block'; clearInterval(poll); clearInterval(timer); }
        function showExpired(){ pending.style.display='none'; paid.style.display='none'; expired.style.display='block'; clearInterval(poll); clearInterval(timer); }

        if (initialStatus === 'paid') { showPaid(); }
        else if (remaining <= 0) { showExpired(); }
        else {
            timer = setInterval(function(){
                remaining--;
                var m = Math.floor(remaining/60), s = remaining%60;
                countdown.textContent = m + ':' + (s<10?'0':'') + s;
                if (remaining <= 0) showExpired();
            }, 1000);
            poll = setInterval(function(){
                fetch('/cakev0/api/sepay/status.php?order=' + orderId, {headers:{'Accept':'application/json'}})
                    .then(function(r){ return r.ok ? r.json() : null; })
                    .then(function(d){ if (d && d.status === 'paid') showPaid(); })
                    .catch(function(){});
            }, 4000);
        }
    </script>
</body>
</html>
```

- [ ] **Step 2: Verify thủ công**

Tạo 1 đơn `pending` qua checkout (Task 6) hoặc SQL tay, mở `http://localhost:8080/cakev0/sepay/payment.php?order=<id>` khi đã đăng nhập → thấy QR, đồng hồ đếm ngược, nội dung `DH<id>`.

- [ ] **Step 3: Commit**

```bash
git add sepay/payment.php
git commit -m "feat(sepay): trang QR thanh toán + auto-poll 4s, hết hạn 15 phút"
```

---

### Task 6: Sửa checkout — thay radio + nhánh POST, gỡ VNPAY

**Files:**
- Modify: `pages/checkout.php` (radio ~538-561; nhánh POST ~152-207; `require vnpay/config.php`)
- Modify: `includes/checkout_helpers.php:27-34` (`shouldClearCartAfterOrderPlacement`)
- Modify: `tests/checkout_helpers_test.php` (nếu có; nếu không, tạo `tests/checkout_clear_cart_test.php`)

**Interfaces:**
- Consumes: `sepay_payment_content` không cần ở checkout; chỉ redirect.
- Produces: đơn `pending` với `payment_method='SePay'`, redirect `sepay/payment.php?order=<id>`.

- [ ] **Step 1: Cập nhật test `shouldClearCartAfterOrderPlacement`**

```php
<?php
// tests/sepay_clear_cart_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/checkout_helpers.php';

assert_true(shouldClearCartAfterOrderPlacement('Tiền mặt'), 'COD xóa giỏ ngay');
assert_true(!shouldClearCartAfterOrderPlacement('SePay'), 'SePay KHÔNG xóa giỏ ngay (webhook trừ)');
assert_true(!shouldClearCartAfterOrderPlacement('VNPAY'), 'VNPAY (đã bỏ) không xóa');
echo "sepay_clear_cart_test ... ok\n";
```

- [ ] **Step 2: Run → fail** (`'SePay'` hiện chưa xử lý; `'Chuyển khoản'` cũ vẫn true nhưng ta bỏ nó — test SePay false sẽ pass sẵn, test cần cập nhật hàm để danh sách đúng)

Run: `php tests/sepay_clear_cart_test.php`
Expected: PASS phần SePay/VNPAY (vì không nằm trong list), nhưng ta vẫn siết list về chỉ COD ở bước sau.

- [ ] **Step 3: Sửa `shouldClearCartAfterOrderPlacement`** — chỉ COD xóa giỏ ngay

```php
if (!function_exists('shouldClearCartAfterOrderPlacement')) {
    function shouldClearCartAfterOrderPlacement(?string $paymentMethod): bool
    {
        $paymentMethod = trim((string) $paymentMethod);

        // Chỉ COD (Tiền mặt) xóa giỏ ngay. SePay để webhook trừ theo item.
        return in_array($paymentMethod, ['Tiền mặt'], true);
    }
}
```

- [ ] **Step 4: Run test → pass**

Run: `php tests/sepay_clear_cart_test.php`
Expected: `sepay_clear_cart_test ... ok`

- [ ] **Step 5: Thay khối radio** trong `pages/checkout.php` (bỏ `Chuyển khoản` + `VNPAY`, thêm `SePay`; xoá luôn khối `.qr-box` tĩnh)

Thay dòng 544-561 bằng:

```php
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="SePay" id="sepay">
                        <span><i class="fa-solid fa-qrcode" style="color: #8b4513;"></i> Chuyển khoản QR (SePay)</span>
                    </label>
                </div>
```

(Giữ nguyên option COD `Tiền mặt` ở trên. Xoá hẳn khối `<div class="qr-box" id="qr">...</div>` tĩnh.)

- [ ] **Step 6: Thay nhánh POST** trong `pages/checkout.php` — bỏ `require vnpay/config.php` + toàn bộ khối dựng URL VNPAY (dòng ~152-199), thay bằng redirect SePay

Thay đoạn `if ($payment === 'VNPAY') { ... } else { ... }` (dòng ~152-207) bằng:

```php
            // Thông báo & Chuyển trang
            if ($payment === 'SePay') {
                header('Location: /cakev0/sepay/payment.php?order=' . (int) $order_id);
                exit;
            } else {
                $_SESSION['toast'] = ['msg' => "Đặt hàng thành công! Mã đơn: #$order_id", 'type' => 'success'];
                header("Location: /cakev0/index.php");
                exit;
            }
```

Đồng thời sửa điều kiện coupon dòng ~141:
```php
            if ($payment !== 'SePay' && !empty($couponCodeForOrder) && $discountAmount > 0) {
                incrementCouponUsage($conn, (string) $couponCodeForOrder);
            }
```
(SePay tăng coupon trong `markOrderPaid`; COD tăng ngay tại đây.)

- [ ] **Step 7: Gỡ JS toggle QR cũ** trong `pages/checkout.php` (đoạn ~620-640 tham chiếu `vnpayRadio`/`#bank`/`#qr`). Xoá listener liên quan `#qr`, `vnpayRadio`, `#bank`.

- [ ] **Step 8: Verify thủ công** — đặt đơn chọn "Chuyển khoản QR (SePay)" → redirect sang trang QR; chọn COD → về trang chủ, giỏ trống.

- [ ] **Step 9: Commit**

```bash
git add pages/checkout.php includes/checkout_helpers.php tests/sepay_clear_cart_test.php
git commit -m "feat(sepay): checkout dùng SePay QR + COD, gỡ VNPAY/chuyển khoản khỏi luồng"
```

---

### Task 7: Xóa hẳn VNPAY + cập nhật env/docs

**Files:**
- Delete: thư mục `vnpay/` (`config.php`, `vnpay_return.php`, `vnpay_create_payment.php`)
- Modify: `.env`, `.env.local` (nếu có), `.env.example` (nếu có) — xoá key `VNPAY_*`, thêm `SEPAY_*`
- Modify: `README.md`, `CLAUDE.md` (mục thanh toán/deploy nếu nhắc VNPAY)

- [ ] **Step 1: Kiểm tra không còn tham chiếu vnpay** ngoài thư mục `vnpay/`

Run: `grep -rin "vnpay\|vnp_" --include=*.php pages includes api admin index.php`
Expected: chỉ còn (nếu có) trong file docs; KHÔNG còn ở checkout/luồng runtime. Nếu còn → sửa hết trước khi xoá.

- [ ] **Step 2: Xoá thư mục + config VNPAY**

```bash
git rm -r vnpay/
```

- [ ] **Step 3: Cập nhật env** — xoá `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL`, `VNPAY_RETURN_URL`, `VNPAY_MERCHANT_URL`, `VNPAY_TRANSACTION_API_URL`; thêm:

```
# SePay
SEPAY_WEBHOOK_API_KEY=
SEPAY_ACCOUNT_NUMBER=
SEPAY_BANK_CODE=
SEPAY_ACCOUNT_NAME=
```

- [ ] **Step 4: Cập nhật README/CLAUDE** — đổi mô tả thanh toán từ VNPAY sang SePay QR; ghi chú webhook URL + cách test bằng "Mô phỏng chuyển khoản".

- [ ] **Step 5: Chạy lại toàn bộ test SePay**

Run:
```bash
php tests/sepay_helpers_test.php && php tests/sepay_clear_cart_test.php
```
(Test cần DB chạy trong Docker: `sepay_mark_paid_test.php`, `sepay_webhook_test.php`.)
Expected: tất cả `... ok`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore(sepay): xoá hẳn VNPAY, cập nhật env + docs sang SePay"
```

---

### Task 8: Kiểm thử đầu-cuối trên SePay Test mode + deploy

**Files:** không đổi code (checklist vận hành).

- [ ] **Step 1: SePay dashboard** — bật Test mode, tạo tài khoản/VA test, tạo webhook trỏ `https://cake-i8l0.onrender.com/cakev0/api/sepay/webhook.php` auth API Key.
- [ ] **Step 2: Env Render** — điền `SEPAY_WEBHOOK_API_KEY`, `SEPAY_ACCOUNT_NUMBER`, `SEPAY_BANK_CODE`, `SEPAY_ACCOUNT_NAME`; xoá `VNPAY_*`; redeploy.
- [ ] **Step 3: Đặt đơn thật** trên production, chọn SePay → thấy trang QR.
- [ ] **Step 4: "Mô phỏng chuyển khoản"** trong SePay với đúng số tiền + nội dung `DH<id>` → webhook bắn → trang QR tự nhảy "thành công" trong ~4s.
- [ ] **Step 5: Kiểm tra** đơn `paid`, giỏ trống, email hóa đơn gửi, coupon +1 (nếu có).
- [ ] **Step 6: Test âm** — mô phỏng sai nội dung → đơn vẫn pending; sai số tiền (thiếu) → vẫn pending. Xác nhận log webhook trong `sepay_transactions`.
- [ ] **Step 7: Merge** `sepay-migration` → `main`, push.

---

## Self-Review

**Spec coverage:**
- §2 kiến trúc → Task 3/4/5/6 ✓
- §3.1 helpers → Task 1/2/3 ✓
- §3.2 webhook → Task 3 ✓
- §3.3 status → Task 4 ✓
- §3.4 payment page → Task 5 ✓
- §3.5 checkout → Task 6 ✓
- §4 bảng `sepay_transactions` + ensure → Task 2 ✓
- §5 env → Task 7 ✓
- §6 dashboard → Task 8 ✓
- §7 bảo mật (hash_equals fail-closed, amount, idempotent, IDOR) → Task 1/2/3/4 ✓
- §8 test → Task 1/2/3/6 ✓
- §9 YAGNI: không VA/hoàn tiền/AI order/thẻ quốc tế — không có task, đúng ✓

**Type consistency:** `sepay_config` shape `[api_key,account,bank,name]` dùng nhất quán Task 1→3→5; `markOrderPaid` trả `[changed,previous]` dùng ở Task 2 test; `sepay_process_webhook` trả `[code,body]` dùng Task 3 test + webhook.php. `sepay_order_status` trả `?string` dùng Task 2/3/4. ✓

**Placeholder scan:** không TBD/TODO; mọi step có code/lệnh cụ thể. ✓
