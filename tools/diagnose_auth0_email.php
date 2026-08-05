<?php
// tools/diagnose_auth0_email.php
// Chay: php tools/diagnose_auth0_email.php
// Kiem tra: env M2M -> Management token -> GET /api/v2/emails/provider.
// Cho biet tenant Auth0 da cau hinh custom email provider chua (ly do mail
// reset/verify khong toi user that tren dev tenant).

require_once __DIR__ . '/../config/bootstrap.php';

function line(string $s): void { echo $s . PHP_EOL; }
function mask(?string $v): string {
    if ($v === null || $v === '') return '<empty>';
    $len = strlen($v);
    return $len <= 8 ? str_repeat('*', $len) : substr($v, 0, 4) . '...' . substr($v, -4) . " (len=$len)";
}

$domain = (string) env_value('AUTH0_DOMAIN', '');
$mgmtId = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
$mgmtSecret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');

line('=== Auth0 Email Provider Diagnostic ===');
line('AUTH0_DOMAIN            : ' . ($domain !== '' ? $domain : '<empty>'));
line('AUTH0_MGMT_CLIENT_ID    : ' . mask($mgmtId));
line('AUTH0_MGMT_CLIENT_SECRET: ' . mask($mgmtSecret));
line('');
line('--- SMTP creds co san trong env (de lam email provider) ---');
line('MAIL_FROM_ADDRESS       : ' . (string) env_value('MAIL_FROM_ADDRESS', '<empty>'));
line('MAIL_USERNAME           : ' . mask(env_value('MAIL_USERNAME')));
line('MAIL_PASSWORD (app pwd) : ' . mask(env_value('MAIL_PASSWORD')));
line('RESEND_API_KEY          : ' . mask(env_value('RESEND_API_KEY')));
line('');

if ($domain === '' || $mgmtId === '' || $mgmtSecret === '') {
    line('[FAIL] Thieu AUTH0_DOMAIN / AUTH0_MGMT_CLIENT_ID / AUTH0_MGMT_CLIENT_SECRET.');
    exit(1);
}

function auth0_http(string $method, string $url, array $headers, ?string $body = null): array {
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

line('--- Step 1: lay Management token ---');
$tokenResp = auth0_http('POST', "https://{$domain}/oauth/token",
    ['Content-Type: application/json'],
    json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $mgmtId,
        'client_secret' => $mgmtSecret,
        'audience' => "https://{$domain}/api/v2/",
    ])
);
$tokenPayload = json_decode($tokenResp['body'], true);
$token = (string) ($tokenPayload['access_token'] ?? '');
if ($token === '') {
    line('[FAIL] Khong lay duoc token. HTTP ' . $tokenResp['status'] . ' - ' . substr($tokenResp['body'], 0, 300));
    line('Kiem tra: M2M app da duoc Authorize cho Auth0 Management API + co scope read:email_provider chua.');
    exit(1);
}
line('[OK]  Management token: ' . substr($token, 0, 10) . '... (len=' . strlen($token) . ')');

line('');
line('--- Step 2: GET /api/v2/emails/provider ---');
$provResp = auth0_http('GET', "https://{$domain}/api/v2/emails/provider",
    ['Authorization: Bearer ' . $token]
);
line('HTTP ' . $provResp['status']);
if ($provResp['status'] === 404 || trim($provResp['body']) === '' || trim($provResp['body']) === '{}') {
    line('[!] CHUA co custom email provider tren tenant.');
    line('    => Day la ly do mail reset/verify khong toi user that.');
    line('    Fix: chay scripts/configure_auth0_email_provider.php');
    exit(0);
}
$prov = json_decode($provResp['body'], true);
if (is_array($prov) && !empty($prov['name'])) {
    line('[OK]  Da co provider: ' . $prov['name'] . (isset($prov['enabled']) ? ' (enabled=' . var_export($prov['enabled'], true) . ')' : ''));
    line('    default_from_address: ' . (string) ($prov['default_from_address'] ?? '<none>'));
    $credsKeys = isset($prov['credentials']) && is_array($prov['credentials']) ? implode(', ', array_keys($prov['credentials'])) : '<none>';
    line('    credentials keys: ' . $credsKeys);
} else {
    line('Raw: ' . substr($provResp['body'], 0, 500));
}
