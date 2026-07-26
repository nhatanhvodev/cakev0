<?php
// tools/diagnose_mail.php
// Chạy: php tools/diagnose_mail.php [recipient@example.com]
// Kiểm tra từng bước theo driver: env vars → connectivity → gửi mail test.

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

function line(string $s): void { echo $s . PHP_EOL; }
function mask(?string $v): string {
    if ($v === null || $v === '') return '<empty>';
    $len = strlen($v);
    return $len <= 8 ? str_repeat('*', $len) : substr($v, 0, 4) . '...' . substr($v, -4) . " (len=$len)";
}

$driver = mail_driver();
line('=== Mail Diagnostic ===');
line('MAIL_DRIVER          : ' . (string) env_value('MAIL_DRIVER', 'smtp'));
line('Active driver        : ' . $driver);
line('MAIL_FROM_ADDRESS    : ' . (string) env_value('MAIL_FROM_ADDRESS', '<empty>'));
line('MAIL_FROM_NAME       : ' . (string) env_value('MAIL_FROM_NAME', '<empty>'));
line('');

if ($driver === 'resend') {
    line('--- Resend config ---');
    line('RESEND_API_KEY       : ' . mask(env_value('RESEND_API_KEY')));
    line('');

    $missing = resend_config_missing();
    if (!empty($missing)) {
        line('[FAIL] Missing config: ' . implode(', ', $missing));
        line('Fix: set RESEND_API_KEY and MAIL_FROM_ADDRESS in .env or Render dashboard.');
        exit(1);
    }
    line('[OK]  Config OK.');

    if (!function_exists('curl_init')) {
        line('[FAIL] ext-curl not available.');
        exit(1);
    }
    line('[OK]  ext-curl available.');

} elseif ($driver === 'gmail_api') {
    line('--- Gmail API config ---');
    line('GMAIL_CLIENT_ID      : ' . mask(env_value('GMAIL_CLIENT_ID')));
    line('GMAIL_CLIENT_SECRET  : ' . mask(env_value('GMAIL_CLIENT_SECRET')));
    line('GMAIL_REFRESH_TOKEN  : ' . mask(env_value('GMAIL_REFRESH_TOKEN')));
    line('GMAIL_USER_ID        : ' . (string) env_value('GMAIL_USER_ID', 'me'));
    line('');

    $missing = gmail_api_config_missing();
    if (!empty($missing)) {
        line('[FAIL] Missing config: ' . implode(', ', $missing));
        line('Fix: điền các env var còn thiếu vào .env hoặc Render dashboard, rồi restart PHP.');
        exit(1);
    }
    line('[OK]  Config đủ 4 env var.');

    if (!function_exists('curl_init')) {
        line('[FAIL] ext-curl chưa bật. Bật `extension=curl` trong php.ini.');
        exit(1);
    }
    line('[OK]  ext-curl available.');

    line('');
    line('--- Step 1: refresh access token ---');
    $token = gmail_api_refresh_access_token();
    if ($token === null) {
        line('[FAIL] Không lấy được access token. Xem PHP error log.');
        line('');
        line('ROOT CAUSE thường gặp:');
        line('  1. OAuth consent screen "Testing" → token hết hạn sau 7 ngày. Fix: publish to Production.');
        line('  2. User revoke app access. Fix: chạy lại OAuth flow.');
        line('  3. Refresh token unused > 6 tháng. Fix: chạy lại OAuth flow.');
        line('  4. Password/2FA thay đổi. Fix: chạy lại OAuth flow.');
        line('  5. Client ID/Secret/Refresh Token sai. Fix: verify trong Cloud Console.');
        line('  6. Server firewall block oauth2.googleapis.com.');
        exit(1);
    }
    line('[OK]  Access token: ' . substr($token, 0, 12) . '... (len=' . strlen($token) . ')');

} else {
    line('--- SMTP config ---');
    line('MAIL_HOST            : ' . (string) env_value('MAIL_HOST', 'smtp.gmail.com'));
    line('MAIL_PORT            : ' . (string) env_value('MAIL_PORT', '587'));
    line('MAIL_USERNAME        : ' . mask(env_value('MAIL_USERNAME')));
    line('MAIL_PASSWORD        : ' . mask(env_value('MAIL_PASSWORD')));
    line('MAIL_ENCRYPTION      : ' . (string) env_value('MAIL_ENCRYPTION', 'tls'));
    line('');

    $missing = mail_required_config_missing();
    if (!empty($missing)) {
        line('[FAIL] Missing config: ' . implode(', ', $missing));
        exit(1);
    }
    line('[OK]  Config OK.');
}

$to = $argv[1] ?? env_value('MAIL_FROM_ADDRESS');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    line('[SKIP] No valid recipient. Usage: php tools/diagnose_mail.php you@example.com');
    exit(0);
}

line('');
line('--- Send test email → ' . $to . ' ---');
$ok = send_custom_mail(
    $to,
    'Gau Bakery — mail diagnostic (' . $driver . ') ' . date('c'),
    '<p>Mail test từ <code>tools/diagnose_mail.php</code> qua driver <strong>' . htmlspecialchars($driver) . '</strong>. Nếu nhận được, mail OK.</p>'
);

if ($ok) {
    line('[OK]  send_custom_mail() returned true. Check inbox (and Spam).');
    exit(0);
}

line('[FAIL] send_custom_mail() returned false. Check PHP error log for details.');
if ($driver === 'resend') {
    line('  - 401: API key invalid or expired.');
    line('  - 403: domain not verified in Resend dashboard.');
    line('  - 422: invalid from/to address or missing required field.');
} elseif ($driver === 'gmail_api') {
    line('  - 400 "Invalid grant": refresh token invalidated.');
    line('  - 403 "insufficientPermissions": OAuth scope missing gmail.send.');
    line('  - 403 "Delegation denied": GMAIL_USER_ID mismatch.');
}
exit(1);
