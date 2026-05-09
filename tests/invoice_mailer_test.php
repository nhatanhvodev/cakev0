<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/invoice_mailer.php';

assert_same(
    'gau_bakery_invoice_email_order_123',
    invoice_email_delivery_lock_name(123),
    'invoice delivery lock names should be stable per order'
);

assert_true(should_send_invoice_email(['invoice_email_sent_at' => null]), 'null sent timestamp should allow sending');
assert_true(!should_send_invoice_email(['invoice_email_sent_at' => '2026-05-09 12:00:00']), 'existing sent timestamp should block sending');

echo "invoice mailer helpers ok\n";
