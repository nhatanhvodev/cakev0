<?php
// tools/diagnose_auth0_logs.php
// Keo log gan nhat tu Auth0 (uu tien failed signup/login) de biet ly do that bai.
// Scope M2M can: read:logs.

require_once __DIR__ . '/../config/bootstrap.php';

function ln(string $s): void { echo $s . PHP_EOL; }

$domain = (string) env_value('AUTH0_DOMAIN', '');
$id = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
$secret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');
if ($domain === '' || $id === '' || $secret === '') {
    fwrite(STDERR, "Thieu AUTH0_DOMAIN / AUTH0_MGMT_CLIENT_ID / AUTH0_MGMT_CLIENT_SECRET\n");
    exit(1);
}

function h(string $method, string $url, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $b = curl_exec($ch); $s = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return ['status' => $s, 'body' => is_string($b) ? $b : ''];
}

$tok = h('POST', "https://{$domain}/oauth/token", ['Content-Type: application/json']);
// token needs body -> do it inline
$ch = curl_init("https://{$domain}/oauth/token");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret, 'audience' => "https://{$domain}/api/v2/"]),
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
]);
$token = (string) (json_decode((string) curl_exec($ch), true)['access_token'] ?? '');
curl_close($ch);
if ($token === '') { fwrite(STDERR, "Khong lay duoc token\n"); exit(1); }

$take = (int) ($argv[1] ?? 25);
$resp = h('GET', "https://{$domain}/api/v2/logs?per_page={$take}&sort=date%3A-1&include_totals=false",
    ['Authorization: Bearer ' . $token]);

if ($resp['status'] === 403) {
    fwrite(STDERR, "403 -> M2M app thieu scope read:logs. Them trong Auth0 Dashboard.\n");
    exit(1);
}
if ($resp['status'] < 200 || $resp['status'] >= 300) {
    fwrite(STDERR, "GET logs that bai HTTP {$resp['status']}: " . substr($resp['body'], 0, 300) . "\n");
    exit(1);
}

$logs = json_decode($resp['body'], true);
if (!is_array($logs)) { fwrite(STDERR, "Parse log loi\n"); exit(1); }

ln('=== ' . count($logs) . ' log gan nhat (moi nhat truoc) ===');
foreach ($logs as $log) {
    $type = (string) ($log['type'] ?? '?');
    $desc = (string) ($log['description'] ?? '');
    $conn = (string) ($log['connection'] ?? '');
    $date = (string) ($log['date'] ?? '');
    // Lay them chi tiet loi neu co
    $detail = '';
    if (isset($log['details']['error'])) {
        $err = $log['details']['error'];
        $detail = is_array($err) ? (string) ($err['message'] ?? json_encode($err)) : (string) $err;
    } elseif (isset($log['details']['response']['body'])) {
        $body = $log['details']['response']['body'];
        $detail = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body;
    }
    ln(sprintf('%s  type=%-4s  conn=%-16s  %s', substr($date, 0, 19), $type, $conn !== '' ? $conn : '-', $desc));
    if ($detail !== '') {
        ln('    -> ' . substr($detail, 0, 400));
    }
}

ln('');
ln('Type codes: ss=signup success, fs=signup FAILED, s=login success, f/fp=login failed, fu=invalid user, ...');
