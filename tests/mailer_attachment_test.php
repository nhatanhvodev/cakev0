<?php
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

$padding = (4 - (strlen($raw) % 4)) % 4;
$decoded = base64_decode(strtr($raw . str_repeat('=', $padding), '-_', '+/'));
assert_true(is_string($decoded), 'raw message should decode');
assert_true(str_contains($decoded, 'multipart/mixed'), 'raw message should be multipart/mixed');
assert_true(str_contains($decoded, 'hoa-don-123.pdf'), 'raw message should include attachment filename');
assert_true(str_contains($decoded, 'application/pdf'), 'raw message should include PDF mime type');

$unicodeRaw = gmail_api_build_raw_message_with_attachment(
    'customer@example.com',
    'Hoa don dac biet',
    '<p>Attachment</p>',
    'sender@example.com',
    'Gau Bakery',
    [
        [
            'filename' => 'hoa-don-đặc-biệt.pdf',
            'mime' => 'application/pdf',
            'content' => '%PDF-1.4 special',
        ],
    ]
);

$unicodePadding = (4 - (strlen($unicodeRaw) % 4)) % 4;
$unicodeDecoded = base64_decode(strtr($unicodeRaw . str_repeat('=', $unicodePadding), '-_', '+/'));
assert_true(is_string($unicodeDecoded), 'unicode raw message should decode');
assert_true((bool) preg_match('/filename="[ -~]+"/', $unicodeDecoded), 'raw message should include ASCII-safe quoted filename fallback');
assert_true(str_contains($unicodeDecoded, "filename*=UTF-8''hoa-don-%C4%91%E1%BA%B7c-bi%E1%BB%87t.pdf"), 'raw message should include RFC 2231 filename parameter');
assert_true(str_contains($unicodeDecoded, "name*=UTF-8''hoa-don-%C4%91%E1%BA%B7c-bi%E1%BB%87t.pdf"), 'raw message should include RFC 2231 name parameter');

echo "mailer attachment helpers ok\n";
