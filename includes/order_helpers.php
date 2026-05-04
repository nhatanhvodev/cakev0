<?php

if (!function_exists('isCodPaymentMethod')) {
    function isCodPaymentMethod(?string $paymentMethod): bool
    {
        $normalized = strtolower(trim((string) $paymentMethod));
        if ($normalized === '') {
            return false;
        }

        return $normalized === 'tiền mặt'
            || $normalized === 'tien mat'
            || $normalized === 'cod'
            || str_contains($normalized, 'cod');
    }
}

if (!function_exists('canCustomerCancelOrder')) {
    function canCustomerCancelOrder(?string $paymentMethod, ?string $status): bool
    {
        $normalizedStatus = strtolower(trim((string) $status));

        return isCodPaymentMethod($paymentMethod)
            && in_array($normalizedStatus, ['pending', 'cod_not_deposited'], true);
    }
}
