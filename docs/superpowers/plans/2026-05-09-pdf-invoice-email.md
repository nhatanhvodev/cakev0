# PDF Invoice Email Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate a server-side PDF invoice and email it as an attachment when a `VNPAY` order is paid successfully or when a `COD` order is confirmed by admin, without sending duplicates.

**Architecture:** Add a focused PDF renderer service, a focused invoice-email service, and attachment support in the existing mailer for both SMTP and Gmail API. Persist duplicate-send state on the `orders` table via `invoice_email_sent_at`, then wire trigger points into `vnpay/vnpay_return.php` and the bulk status-update block in `admin/admin.php`.

**Tech Stack:** PHP procedural app, Composer, `dompdf/dompdf`, existing `tests/bootstrap.php` CLI test harness, `mysqli`, PHPMailer SMTP path, Gmail API raw MIME path.

---

## File Structure

- `composer.json`
  - add the PDF library dependency
- `composer.lock`
  - lock dependency resolution
- `database/migrations/2026-05-09_add_invoice_email_tracking.sql`
  - add `orders.invoice_email_sent_at`
- `database/banh_store.sql`
  - mirror schema change in the dump
- `includes/invoice_pdf.php`
  - generate invoice filename
  - render invoice HTML
  - render PDF binary
- `includes/invoice_mailer.php`
  - load invoice-ready order data
  - guard duplicate sends
  - call PDF renderer + mailer
  - mark `invoice_email_sent_at`
- `includes/mailer.php`
  - add attachment support to SMTP and Gmail API paths
- `tests/invoice_pdf_test.php`
  - focused tests for invoice filename and HTML rendering
- `tests/mailer_attachment_test.php`
  - focused tests for Gmail API multipart message building with attachment
- `vnpay/vnpay_return.php`
  - trigger invoice email when payment succeeds
- `admin/admin.php`
  - trigger invoice email when COD order is confirmed
- `README.md`
  - short note about PDF invoice email behavior

### Task 1: Add the PDF Dependency and Invoice Email Tracking Schema

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `database/migrations/2026-05-09_add_invoice_email_tracking.sql`
- Modify: `database/banh_store.sql`

- [ ] **Step 1: Add the PDF dependency**

Run:

```bash
composer require dompdf/dompdf
```

Expected:
- `composer.json` includes `dompdf/dompdf`
- `composer.lock` is updated

- [ ] **Step 2: Create the migration**

```sql
-- database/migrations/2026-05-09_add_invoice_email_tracking.sql
ALTER TABLE `orders`
  ADD COLUMN `invoice_email_sent_at` DATETIME NULL AFTER `status`;
```

- [ ] **Step 3: Mirror the schema change in the SQL dump**

Update the `orders` table definition in `database/banh_store.sql` so it contains:

```sql
`invoice_email_sent_at` datetime DEFAULT NULL,
```

positioned next to the `status` column in a way that matches the dump style.

- [ ] **Step 4: Verify the dependency and schema text changes**

Run:

```bash
rg -n "dompdf/dompdf|invoice_email_sent_at" composer.json composer.lock database/migrations/2026-05-09_add_invoice_email_tracking.sql database/banh_store.sql
```

Expected:
- a match for `dompdf/dompdf`
- a match for `invoice_email_sent_at` in both SQL files

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock database/migrations/2026-05-09_add_invoice_email_tracking.sql database/banh_store.sql
git commit -m "feat: add invoice email tracking schema"
```

### Task 2: Add Focused PDF Renderer Tests

**Files:**
- Create: `tests/invoice_pdf_test.php`
- Test: `tests/invoice_pdf_test.php`

- [ ] **Step 1: Write the failing PDF helper test**

```php
<?php
// tests/invoice_pdf_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/invoice_pdf.php';

$order = [
    'id' => 123,
    'created_at' => '2026-05-09 10:30:00',
    'recipient_name' => 'Nguyen Van A',
    'phone' => '0901234567',
    'address' => '123 Duong ABC, Quan 1',
    'payment_method' => 'VNPAY',
    'coupon_code' => 'SAVE10',
    'coupon_discount' => 15000,
    'total_amount' => 185000,
];

$items = [
    ['ten_banh' => 'Banh Tiramisu', 'quantity' => 2, 'price' => 50000],
    ['ten_banh' => 'Banh Su Kem', 'quantity' => 1, 'price' => 100000],
];

assert_same('hoa-don-123.pdf', build_invoice_filename(123), 'invoice filename should include order id');

$html = render_invoice_html($order, $items);
assert_true(str_contains($html, 'Hóa đơn #123'), 'invoice HTML should show invoice number');
assert_true(str_contains($html, 'Nguyen Van A'), 'invoice HTML should show recipient name');
assert_true(str_contains($html, 'Banh Tiramisu'), 'invoice HTML should show product names');
assert_true(str_contains($html, '185.000'), 'invoice HTML should show total amount');

echo "invoice pdf helpers ok\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
php tests/invoice_pdf_test.php
```

Expected:
- fail because `includes/invoice_pdf.php` does not exist yet

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/invoice_pdf_test.php
git commit -m "test: add invoice pdf helper tests"
```

### Task 3: Implement the Invoice PDF Renderer

**Files:**
- Create: `includes/invoice_pdf.php`
- Test: `tests/invoice_pdf_test.php`

- [ ] **Step 1: Implement the minimal helper API**

```php
<?php
// includes/invoice_pdf.php
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/bootstrap.php';

function build_invoice_filename(int $orderId): string
{
    return 'hoa-don-' . $orderId . '.pdf';
}

function format_invoice_money(float $amount): string
{
    return number_format($amount, 0, ',', '.');
}

function render_invoice_html(array $order, array $items): string
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; }
            h1, h2, h3 { margin: 0 0 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th.text-end, td.text-end { text-align: right; }
            .meta { margin-bottom: 14px; }
            .total { margin-top: 14px; text-align: right; font-weight: 700; }
        </style>
    </head>
    <body>
        <h1>Gấu Bakery</h1>
        <h2>Hóa đơn #<?= (int) $order['id'] ?></h2>
        <div class="meta">
            <div>Ngày đặt: <?= htmlspecialchars((string) $order['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>Người nhận: <?= htmlspecialchars((string) $order['recipient_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>Số điện thoại: <?= htmlspecialchars((string) $order['phone'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>Địa chỉ: <?= htmlspecialchars((string) $order['address'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>Phương thức thanh toán: <?= htmlspecialchars((string) $order['payment_method'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th class="text-end">SL</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['ten_banh'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end"><?= (int) $item['quantity'] ?></td>
                    <td class="text-end"><?= format_invoice_money((float) $item['price']) ?></td>
                    <td class="text-end"><?= format_invoice_money((float) $item['price'] * (int) $item['quantity']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($order['coupon_code']) && (float) ($order['coupon_discount'] ?? 0) > 0): ?>
            <div>Mã giảm giá: <?= htmlspecialchars((string) $order['coupon_code'], ENT_QUOTES, 'UTF-8') ?></div>
            <div>Giảm giá: <?= format_invoice_money((float) $order['coupon_discount']) ?> VNĐ</div>
        <?php endif; ?>
        <div class="total">Tổng thanh toán: <?= format_invoice_money((float) $order['total_amount']) ?> VNĐ</div>
    </body>
    </html>
    <?php

    return (string) ob_get_clean();
}

function render_invoice_pdf(array $order, array $items): string
{
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(render_invoice_html($order, $items), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}
```

- [ ] **Step 2: Run the test to verify it passes**

Run:

```bash
php tests/invoice_pdf_test.php
```

Expected:
- `invoice pdf helpers ok`

- [ ] **Step 3: Lint the renderer**

Run:

```bash
php -l includes/invoice_pdf.php
```

Expected:
- `No syntax errors detected in includes/invoice_pdf.php`

- [ ] **Step 4: Commit**

```bash
git add includes/invoice_pdf.php tests/invoice_pdf_test.php
git commit -m "feat: add invoice pdf renderer"
```

### Task 4: Add Focused Attachment MIME Tests

**Files:**
- Create: `tests/mailer_attachment_test.php`
- Test: `tests/mailer_attachment_test.php`

- [ ] **Step 1: Write the failing attachment test**

```php
<?php
// tests/mailer_attachment_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$raw = gmail_api_build_raw_message_with_attachment(
    'customer@example.com',
    'Hóa đơn đơn hàng #123 từ Gấu Bakery',
    '<p>Vui lòng xem hóa đơn đính kèm.</p>',
    'sender@example.com',
    'Gau Bakery',
    [
        [
            'filename' => 'hoa-don-123.pdf',
            'mime' => 'application/pdf',
            'content' => '%PDF-1.4 sample',
        ],
    ]
);

$decoded = base64_decode(strtr($raw, '-_', '+/'));
assert_true(str_contains($decoded, 'multipart/mixed'), 'raw message should be multipart/mixed');
assert_true(str_contains($decoded, 'hoa-don-123.pdf'), 'raw message should include attachment filename');
assert_true(str_contains($decoded, 'application/pdf'), 'raw message should include PDF mime type');

echo "mailer attachment helpers ok\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
php tests/mailer_attachment_test.php
```

Expected:
- fail because `gmail_api_build_raw_message_with_attachment()` does not exist yet

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/mailer_attachment_test.php
git commit -m "test: add mail attachment helper tests"
```

### Task 5: Extend the Mailer for Attachments

**Files:**
- Modify: `includes/mailer.php`
- Test: `tests/mailer_attachment_test.php`

- [ ] **Step 1: Add a Gmail API multipart raw-message builder**

```php
function gmail_api_build_raw_message_with_attachment(
    string $to,
    string $subject,
    string $body,
    string $fromAddress,
    ?string $fromName,
    array $attachments
): string {
    $boundary = 'mixed_' . bin2hex(random_bytes(12));
    $domain = substr(strrchr($fromAddress, '@') ?: '@localhost', 1) ?: 'localhost';

    $headers = [
        'MIME-Version: 1.0',
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'From: ' . mail_format_address($fromAddress, $fromName ?? env_value('MAIL_FROM_NAME', 'Gau Bakery')),
        'To: ' . mail_format_address($to),
        'Subject: ' . mail_encode_header($subject),
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];

    $parts = [];
    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: text/html; charset=UTF-8';
    $parts[] = 'Content-Transfer-Encoding: base64';
    $parts[] = '';
    $parts[] = chunk_split(base64_encode($body), 76, "\r\n");

    foreach ($attachments as $attachment) {
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: ' . $attachment['mime'] . '; name="' . mail_sanitize_header($attachment['filename']) . '"';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = 'Content-Disposition: attachment; filename="' . mail_sanitize_header($attachment['filename']) . '"';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($attachment['content']), 76, "\r\n");
    }

    $parts[] = '--' . $boundary . '--';
    $parts[] = '';

    return gmail_api_base64url_encode(implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts));
}
```

- [ ] **Step 2: Add attachment-aware send functions**

Implement:

```php
function send_custom_mail_with_attachments(
    string $to,
    string $subject,
    string $body,
    array $attachments,
    ?string $fromName = null
): bool
```

Requirements:
- for `gmail_api`, call the new multipart builder
- for SMTP, use PHPMailer `addStringAttachment($content, $filename, PHPMailer::ENCODING_BASE64, $mime)`
- keep `send_custom_mail()` intact for non-attachment calls

- [ ] **Step 3: Run the attachment test**

Run:

```bash
php tests/mailer_attachment_test.php
```

Expected:
- `mailer attachment helpers ok`

- [ ] **Step 4: Lint the mailer**

Run:

```bash
php -l includes/mailer.php
```

Expected:
- `No syntax errors detected in includes/mailer.php`

- [ ] **Step 5: Commit**

```bash
git add includes/mailer.php tests/mailer_attachment_test.php
git commit -m "feat: add invoice mail attachments"
```

### Task 6: Implement the Invoice Email Service

**Files:**
- Create: `includes/invoice_mailer.php`
- Modify: `tests/invoice_pdf_test.php`

- [ ] **Step 1: Add a focused duplicate-send guard test**

Append a pure helper test to `tests/invoice_pdf_test.php`:

```php
assert_true(should_send_invoice_email(['invoice_email_sent_at' => null]), 'null sent timestamp should allow sending');
assert_true(!should_send_invoice_email(['invoice_email_sent_at' => '2026-05-09 12:00:00']), 'existing sent timestamp should block sending');
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
php tests/invoice_pdf_test.php
```

Expected:
- fail because `should_send_invoice_email()` does not exist yet

- [ ] **Step 3: Implement the invoice mailer service**

```php
<?php
// includes/invoice_mailer.php
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/invoice_pdf.php';
require_once __DIR__ . '/mailer.php';

function should_send_invoice_email(array $order): bool
{
    return empty($order['invoice_email_sent_at']);
}

function load_invoice_order_payload(mysqli $conn, int $orderId): ?array
{
    $stmt = $conn->prepare(
        "SELECT o.*, u.email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT b.ten_banh, oi.quantity, oi.price
         FROM order_items oi
         JOIN banh b ON b.id = oi.banh_id
         WHERE oi.order_id = ?"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['order' => $order, 'items' => $items];
}

function send_order_invoice_email(mysqli $conn, int $orderId): bool
{
    $payload = load_invoice_order_payload($conn, $orderId);
    if (!$payload) {
        return false;
    }

    $order = $payload['order'];
    $items = $payload['items'];
    $email = (string) ($order['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('Invoice Mail Error: Missing or invalid recipient email for order #' . $orderId);
        return false;
    }

    if (!should_send_invoice_email($order)) {
        return true;
    }

    $pdf = render_invoice_pdf($order, $items);
    $filename = build_invoice_filename($orderId);
    $subject = 'Hóa đơn đơn hàng #' . $orderId . ' từ Gấu Bakery';
    $body = '<p>Đơn hàng của bạn đã được xác nhận hoặc thanh toán thành công. Vui lòng xem hóa đơn PDF đính kèm.</p>';

    $sent = send_custom_mail_with_attachments($email, $subject, $body, [
        [
            'filename' => $filename,
            'mime' => 'application/pdf',
            'content' => $pdf,
        ],
    ]);

    if (!$sent) {
        error_log('Invoice Mail Error: Failed to send invoice for order #' . $orderId);
        return false;
    }

    $stmt = $conn->prepare("UPDATE orders SET invoice_email_sent_at = NOW() WHERE id = ? AND invoice_email_sent_at IS NULL");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();

    return true;
}
```

- [ ] **Step 4: Re-run the PDF helper test**

Run:

```bash
php tests/invoice_pdf_test.php
```

Expected:
- `invoice pdf helpers ok`

- [ ] **Step 5: Lint the invoice service**

Run:

```bash
php -l includes/invoice_mailer.php
```

Expected:
- `No syntax errors detected in includes/invoice_mailer.php`

- [ ] **Step 6: Commit**

```bash
git add includes/invoice_mailer.php tests/invoice_pdf_test.php
git commit -m "feat: add invoice email service"
```

### Task 7: Trigger Invoice Email on VNPAY Success

**Files:**
- Modify: `vnpay/vnpay_return.php`
- Modify: `includes/invoice_mailer.php`

- [ ] **Step 1: Include the invoice mailer**

Add near the top:

```php
require_once("../includes/invoice_mailer.php");
```

- [ ] **Step 2: Trigger invoice send only after successful payment state transition**

Inside the `vnp_ResponseCode == '00'` success branch, after the order update/cart work/usage increment logic and before commit completes, add:

```php
$shouldSendInvoice = ($previousStatus !== 'paid');
```

After the transaction commits successfully, add:

```php
if (!empty($order_id) && !empty($shouldSendInvoice)) {
    send_order_invoice_email($conn, (int) $order_id);
}
```

Important:
- do not roll back payment success if invoice sending fails
- do not send again if the order was already `paid`

- [ ] **Step 3: Lint the VNPAY handler**

Run:

```bash
php -l vnpay/vnpay_return.php
```

Expected:
- `No syntax errors detected in vnpay/vnpay_return.php`

- [ ] **Step 4: Commit**

```bash
git add vnpay/vnpay_return.php includes/invoice_mailer.php
git commit -m "feat: email invoice after vnpay success"
```

### Task 8: Trigger Invoice Email on COD Admin Confirmation

**Files:**
- Modify: `admin/admin.php`
- Modify: `includes/invoice_mailer.php`

- [ ] **Step 1: Include the invoice mailer**

Add near the admin bootstrap requires:

```php
require_once '../includes/invoice_mailer.php';
```

- [ ] **Step 2: Detect the COD confirmation transition**

Inside the bulk order status update block around lines 457-500, change the metadata query so it loads:

```php
SELECT payment_method, status FROM orders WHERE id = ? LIMIT 1
```

Then, before the update, compute:

```php
$previousStatus = strtolower((string) ($orderMeta['status'] ?? ''));
$shouldSendInvoice = $isCodOrder
    && $status === 'approved'
    && $previousStatus !== 'approved';
```

After a successful update for that order, add:

```php
if ($shouldSendInvoice) {
    send_order_invoice_email($conn, $id);
}
```

Important:
- only trigger for COD-style orders
- only trigger when moving into `approved`
- do not resend if already approved before
- do not block admin status update on send failure

- [ ] **Step 3: Lint the admin file**

Run:

```bash
php -l admin/admin.php
```

Expected:
- `No syntax errors detected in admin/admin.php`

- [ ] **Step 4: Commit**

```bash
git add admin/admin.php includes/invoice_mailer.php
git commit -m "feat: email invoice after cod confirmation"
```

### Task 9: Final Documentation and Verification

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add concise README notes**

Add bullets such as:

```md
- Hóa đơn PDF được gửi qua email sau khi đơn VNPAY thanh toán thành công hoặc khi admin xác nhận đơn COD.
- Mỗi đơn chỉ tự động gửi hóa đơn một lần, được theo dõi bằng `orders.invoice_email_sent_at`.
```

- [ ] **Step 2: Run the focused verification suite**

Run:

```bash
php tests/auth_helpers_test.php
php tests/registration_helpers_test.php
php tests/invoice_pdf_test.php
php tests/mailer_attachment_test.php
php -l includes/invoice_pdf.php
php -l includes/invoice_mailer.php
php -l includes/mailer.php
php -l vnpay/vnpay_return.php
php -l admin/admin.php
```

Expected:
- all tests print their success messages
- all `php -l` checks report no syntax errors

- [ ] **Step 3: Do the manual verification pass**

Manual checks to record:
- `VNPAY` success path sends invoice only once
- COD `approved` path sends invoice only once
- email arrives with short body and attached `hoa-don-<id>.pdf`
- attached PDF opens successfully and matches order totals

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: add invoice email behavior notes"
```
