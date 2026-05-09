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

echo "mailer attachment helpers ok\n";
