<?php

if (!function_exists('sepay_config')) {
    function sepay_config(): array
    {
        return [
            'api_key' => (string) env_value('SEPAY_WEBHOOK_API_KEY', ''),
            'account' => (string) env_value('SEPAY_ACCOUNT_NUMBER', ''),
            'bank'    => (string) env_value('SEPAY_BANK_CODE', ''),
            'name'    => (string) env_value('SEPAY_ACCOUNT_NAME', ''),
        ];
    }
}

if (!function_exists('sepay_payment_content')) {
    function sepay_payment_content(int $orderId): string
    {
        return 'DH' . $orderId;
    }
}

if (!function_exists('sepay_extract_order_id')) {
    function sepay_extract_order_id(string $content, ?string $code = null): ?int
    {
        foreach ([$code, $content] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (preg_match('/DH0*(\d+)/i', $candidate, $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }
}

if (!function_exists('sepay_verify_api_key')) {
    function sepay_verify_api_key(?string $authHeader, string $expectedKey): bool
    {
        if ($expectedKey === '' || $authHeader === null) {
            return false;
        }
        $prefix = 'Apikey ';
        if (strncmp($authHeader, $prefix, strlen($prefix)) !== 0) {
            return false;
        }
        $provided = substr($authHeader, strlen($prefix));
        return hash_equals($expectedKey, $provided);
    }
}

if (!function_exists('sepay_build_qr_url')) {
    function sepay_build_qr_url(array $cfg, int $orderId, int $amount): string
    {
        $params = http_build_query([
            'acc'    => $cfg['account'] ?? '',
            'bank'   => $cfg['bank'] ?? '',
            'amount' => $amount,
            'des'    => sepay_payment_content($orderId),
        ]);
        return 'https://qr.sepay.vn/img?' . $params;
    }
}
