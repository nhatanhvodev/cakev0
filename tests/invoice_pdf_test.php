<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/invoice_pdf.php';
require_once __DIR__ . '/../includes/invoice_mailer.php';

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
assert_true(should_send_invoice_email(['invoice_email_sent_at' => null]), 'null sent timestamp should allow sending');
assert_true(!should_send_invoice_email(['invoice_email_sent_at' => '2026-05-09 12:00:00']), 'existing sent timestamp should block sending');

echo "invoice pdf helpers ok\n";
