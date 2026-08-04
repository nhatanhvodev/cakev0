<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/checkout_helpers.php';

assert_true(shouldClearCartAfterOrderPlacement('Tiền mặt'), 'COD xoa gio ngay');
assert_true(!shouldClearCartAfterOrderPlacement('SePay'), 'SePay khong xoa gio ngay');
assert_true(!shouldClearCartAfterOrderPlacement('VNPAY'), 'VNPAY khong xoa gio ngay');
assert_true(!shouldClearCartAfterOrderPlacement('Chuyển khoản'), 'chuyen khoan thu cong khong xoa gio ngay');

echo "sepay_clear_cart_test ... ok\n";
