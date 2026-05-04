<?php

if (!function_exists('isCheckoutCsrfInvalid')) {
    function isCheckoutCsrfInvalid(?string $sessionToken, ?string $postedToken): bool
    {
        $sessionToken = (string) $sessionToken;
        $postedToken = (string) $postedToken;

        return $sessionToken === ''
            || $postedToken === ''
            || !hash_equals($sessionToken, $postedToken);
    }
}

if (!function_exists('buildCheckoutRedirectUrl')) {
    function buildCheckoutRedirectUrl(string $couponInput = ''): string
    {
        $redirectUrl = '/cakev0/pages/checkout.php';
        if ($couponInput !== '') {
            $redirectUrl .= '?coupon=' . urlencode($couponInput);
        }

        return $redirectUrl;
    }
}

if (!function_exists('shouldClearCartAfterOrderPlacement')) {
    function shouldClearCartAfterOrderPlacement(?string $paymentMethod): bool
    {
        $paymentMethod = trim((string) $paymentMethod);

        return in_array($paymentMethod, ['Tiền mặt', 'Chuyển khoản'], true);
    }
}
