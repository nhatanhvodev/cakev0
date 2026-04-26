<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(env_value('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

if (!function_exists('is_local_hostname')) {
    function is_local_hostname(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $normalizedHost = strtolower(trim($host, '[]'));
        return in_array($normalizedHost, ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('resolve_vnpay_return_url')) {
    function resolve_vnpay_return_url(): string
    {
        $defaultReturnUrl = absolute_url('vnpay/vnpay_return.php');
        $configuredReturnUrl = env_value('VNPAY_RETURN_URL', null);

        if ($configuredReturnUrl === null || $configuredReturnUrl === '') {
            return $defaultReturnUrl;
        }

        $configuredHost = parse_url($configuredReturnUrl, PHP_URL_HOST);
        $currentHost = parse_url(app_origin(), PHP_URL_HOST);

        if (is_local_hostname($configuredHost) && $currentHost && !is_local_hostname($currentHost)) {
            return $defaultReturnUrl;
        }

        return $configuredReturnUrl;
    }
}

// VNPAY Sandbox Configuration
$vnp_TmnCode = env_value('VNPAY_TMN_CODE', 'GO1IEGFS');
$vnp_HashSecret = env_value('VNPAY_HASH_SECRET', 'FALJ15YAAF9B9AEWXHVGBVQL6WO4SONA');
$vnp_Url = env_value('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

$vnp_Returnurl = resolve_vnpay_return_url();
$vnp_apiUrl = env_value('VNPAY_MERCHANT_URL', 'http://sandbox.vnpayment.vn/merchant_webapi/merchant.html');
$apiUrl = env_value('VNPAY_TRANSACTION_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction');

// Config input format
// Expire
$startTime = date("YmdHis");
$expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));
