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

$runtimeDir = ensure_invoice_pdf_runtime_dir();
assert_true(is_dir($runtimeDir), 'invoice PDF runtime directory should exist');
assert_true(is_writable($runtimeDir), 'invoice PDF runtime directory should be writable');

$options = build_invoice_pdf_options();
assert_same($runtimeDir, $options->get('tempDir'), 'invoice PDF temp dir should stay inside the repo-local runtime directory');
assert_same($runtimeDir, $options->get('fontCache'), 'invoice PDF font cache should stay inside the repo-local runtime directory');

$html = render_invoice_html($order, $items);
assert_true(str_contains($html, 'Hóa đơn #123'), 'invoice HTML should show invoice number');
assert_true(str_contains($html, 'Nguyen Van A'), 'invoice HTML should show recipient name');
assert_true(str_contains($html, 'Banh Tiramisu'), 'invoice HTML should show product names');
assert_true(str_contains($html, '185.000'), 'invoice HTML should show total amount');

$pdf = render_invoice_pdf($order, $items);
assert_true(str_starts_with($pdf, '%PDF'), 'invoice PDF renderer should return PDF bytes');

assert_true(should_send_invoice_email(['invoice_email_sent_at' => null]), 'null sent timestamp should allow sending');
assert_true(!should_send_invoice_email(['invoice_email_sent_at' => '2026-05-09 12:00:00']), 'existing sent timestamp should block sending');

echo "invoice pdf helpers ok\n";
