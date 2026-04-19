<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(env_value('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

// VNPAY Sandbox Configuration
$vnp_TmnCode = env_value('VNPAY_TMN_CODE', 'GO1IEGFS');
$vnp_HashSecret = env_value('VNPAY_HASH_SECRET', 'FALJ15YAAF9B9AEWXHVGBVQL6WO4SONA');
$vnp_Url = env_value('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

$vnp_Returnurl = env_value('VNPAY_RETURN_URL', absolute_url('vnpay/vnpay_return.php'));
$vnp_apiUrl = env_value('VNPAY_MERCHANT_URL', 'http://sandbox.vnpayment.vn/merchant_webapi/merchant.html');
$apiUrl = env_value('VNPAY_TRANSACTION_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction');

// Config input format
// Expire
$startTime = date("YmdHis");
$expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));
