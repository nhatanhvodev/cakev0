<?php
// scripts/configure_auth0_email_provider.php
// Cau hinh email provider (Resend) cua tenant Auth0 qua Management API, de mail
// reset password / verify email cua Auth0 giao duoc toi user that.
//
// Chay:
//   php scripts/configure_auth0_email_provider.php                       # dry-run
//   php scripts/configure_auth0_email_provider.php --apply               # thuc su PATCH
//   php scripts/configure_auth0_email_provider.php --apply --from=no-reply@nhatanhvodev.me
//
// Env yeu cau:
//   AUTH0_DOMAIN, AUTH0_MGMT_CLIENT_ID, AUTH0_MGMT_CLIENT_SECRET
//   Tuy chon: RESEND_API_KEY (neu co, se ghi de credentials; neu trong, giu key da luu tren Auth0)
//   Tuy chon: AUTH0_EMAIL_FROM (mac dinh --from hoac no-reply@nhatanhvodev.me)
//
// M2M app phai co scope: update:email_provider (+ read:email_provider de verify).

require_once __DIR__ . '/../config/bootstrap.php';

function line(string $s): void { echo $s . PHP_EOL; }

function a0_http(string $method, string $url, array $headers, ?string $body = null): array {
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

$apply = in_array('--apply', $argv, true);

// --from=... > env AUTH0_EMAIL_FROM > mac dinh
$fromArg = '';
foreach ($argv as $a) {
    if (strncmp($a, '--from=', 7) === 0) {
        $fromArg = substr($a, 7);
    }
}
$fromAddress = trim($fromArg !== '' ? $fromArg : (string) env_value('AUTH0_EMAIL_FROM', 'no-reply@nhatanhvodev.me'));

$domain = (string) env_value('AUTH0_DOMAIN', '');
$mgmtId = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
$mgmtSecret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');
$resendKey = trim((string) env_value('RESEND_API_KEY', ''));

if ($domain === '' || $mgmtId === '' || $mgmtSecret === '') {
    fwrite(STDERR, "Thieu AUTH0_DOMAIN / AUTH0_MGMT_CLIENT_ID / AUTH0_MGMT_CLIENT_SECRET\n");
    exit(1);
}
if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "from-address khong hop le: {$fromAddress}\n");
    exit(1);
}

$providerBody = [
    'name' => 'resend',
    'enabled' => true,
    'default_from_address' => $fromAddress,
];
if ($resendKey !== '') {
    // Chi ghi credentials khi co key trong env; neu khong, giu key da luu tren Auth0.
    $providerBody['credentials'] = ['api_key' => $resendKey];
}

$preview = $providerBody;
if (isset($preview['credentials']['api_key'])) {
    $preview['credentials']['api_key'] = str_repeat('*', strlen($resendKey));
}
line('=== Auth0 Email Provider: Resend ===');
line('Tenant   : ' . $domain);
line('From     : ' . $fromAddress);
line('api_key  : ' . ($resendKey !== '' ? 'tu env (' . strlen($resendKey) . ' ky tu)' : 'giu key da luu tren Auth0 (khong gui trong PATCH)'));
line('Body     : ' . json_encode($preview, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
line('');

if (!$apply) {
    line('[DRY-RUN] Chua gui gi. Them --apply de thuc su cau hinh len Auth0.');
    exit(0);
}

line('--- Lay Management token ---');
$tokenResp = a0_http('POST', "https://{$domain}/oauth/token",
    ['Content-Type: application/json'],
    json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $mgmtId,
        'client_secret' => $mgmtSecret,
        'audience' => "https://{$domain}/api/v2/",
    ])
);
$token = (string) (json_decode($tokenResp['body'], true)['access_token'] ?? '');
if ($token === '') {
    fwrite(STDERR, 'Khong lay duoc token. HTTP ' . $tokenResp['status'] . ' - ' . substr($tokenResp['body'], 0, 300) . "\n");
    exit(1);
}
line('[OK] token len=' . strlen($token));

$headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
$json = json_encode($providerBody, JSON_UNESCAPED_SLASHES);

line('--- PATCH /api/v2/emails/provider ---');
$resp = a0_http('PATCH', "https://{$domain}/api/v2/emails/provider", $headers, $json);
line('HTTP ' . $resp['status']);

if ($resp['status'] === 404) {
    line('Provider chua ton tai, thu POST tao moi...');
    if (!isset($providerBody['credentials'])) {
        fwrite(STDERR, "POST tao moi can RESEND_API_KEY trong env.\n");
        exit(1);
    }
    $resp = a0_http('POST', "https://{$domain}/api/v2/emails/provider", $headers, $json);
    line('POST HTTP ' . $resp['status']);
}

if ($resp['status'] < 200 || $resp['status'] >= 300) {
    fwrite(STDERR, 'That bai: ' . substr($resp['body'], 0, 500) . "\n");
    if ($resp['status'] === 403) {
        fwrite(STDERR, "403 -> M2M app thieu scope update:email_provider / create:email_provider.\n");
    }
    exit(1);
}

line('[OK] Cau hinh xong.');
$prov = json_decode($resp['body'], true);
if (is_array($prov)) {
    line('  name: ' . (string) ($prov['name'] ?? '?'));
    line('  enabled: ' . var_export($prov['enabled'] ?? null, true));
    line('  default_from_address: ' . (string) ($prov['default_from_address'] ?? '<none>'));
}
line('');
line('Buoc tiep: gui thu email reset password tren Universal Login, kiem tra inbox + Spam.');
