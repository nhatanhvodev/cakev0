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
