<?php
// includes/auth0_management.php
// Helper goi Auth0 Management API (M2M): lay token + gui lai email xac minh.
// Env: AUTH0_DOMAIN, AUTH0_MGMT_CLIENT_ID, AUTH0_MGMT_CLIENT_SECRET.
// Scope M2M can: create:user_tickets / update:users (cho verification-email job).

require_once __DIR__ . '/../config/bootstrap.php';

if (!function_exists('auth0_mgmt_http')) {
    function auth0_mgmt_http(string $method, string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($resp) ? $resp : '', 'error' => $err];
    }
}

if (!function_exists('auth0_management_token')) {
    function auth0_management_token(): ?string
    {
        $domain = (string) env_value('AUTH0_DOMAIN', '');
        $clientId = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
        $clientSecret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');
        if ($domain === '' || $clientId === '' || $clientSecret === '') {
            error_log('Auth0 Mgmt: thieu AUTH0_DOMAIN / AUTH0_MGMT_CLIENT_ID / AUTH0_MGMT_CLIENT_SECRET');
            return null;
        }

        $resp = auth0_mgmt_http('POST', "https://{$domain}/oauth/token",
            ['Content-Type: application/json'],
            json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'audience' => "https://{$domain}/api/v2/",
            ])
        );
        $token = (string) (json_decode($resp['body'], true)['access_token'] ?? '');
        if ($token === '') {
            error_log('Auth0 Mgmt: lay token that bai HTTP ' . $resp['status'] . ' - ' . substr($resp['body'], 0, 200));
            return null;
        }
        return $token;
    }
}

if (!function_exists('auth0_send_verification_email')) {
    // Gui lai email xac minh cho user Auth0 (user_id = claim `sub`, vd "auth0|abc").
    function auth0_send_verification_email(string $userId): bool
    {
        if ($userId === '') {
            return false;
        }
        $domain = (string) env_value('AUTH0_DOMAIN', '');
        $token = auth0_management_token();
        if ($domain === '' || $token === null) {
            return false;
        }

        $payload = ['user_id' => $userId];
        $clientId = (string) env_value('AUTH0_CLIENT_ID', '');
        if ($clientId !== '') {
            $payload['client_id'] = $clientId;
        }

        $resp = auth0_mgmt_http('POST', "https://{$domain}/api/v2/jobs/verification-email",
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );

        if ($resp['status'] >= 200 && $resp['status'] < 300) {
            return true;
        }
        error_log('Auth0 Mgmt: verification-email that bai HTTP ' . $resp['status'] . ' - ' . substr($resp['body'], 0, 200));
        return false;
    }
}
