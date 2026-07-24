<?php
// tools/diagnose_gmail.php
// Chạy: php tools/diagnose_gmail.php [recipient@example.com]
// Kiểm tra từng bước: env vars → refresh token → gửi mail test.

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

function line(string $s): void { echo $s . PHP_EOL; }
function mask(?string $v): string {
    if ($v === null || $v === '') return '<empty>';
    $len = strlen($v);
    return $len <= 8 ? str_repeat('*', $len) : substr($v, 0, 4) . '...' . substr($v, -4) . " (len=$len)";
}

line('=== Gmail API Diagnostic ===');
line('MAIL_DRIVER          : ' . (string) env_value('MAIL_DRIVER', 'smtp'));
line('MAIL_FROM_ADDRESS    : ' . (string) env_value('MAIL_FROM_ADDRESS', '<empty>'));
line('MAIL_FROM_NAME       : ' . (string) env_value('MAIL_FROM_NAME', '<empty>'));
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
    line('[FAIL] Không lấy được access token. Xem PHP error log để biết mã HTTP + message.');
    line('');
    line('ROOT CAUSE thường gặp:');
    line('  1. OAuth consent screen ở trạng thái "Testing" → refresh token hết hạn sau 7 ngày.');
    line('     Fix: Google Cloud Console → OAuth consent screen → Publish App (Production).');
    line('  2. User revoke app access.');
    line('     Fix: user vào https://myaccount.google.com/permissions gỡ app, chạy OAuth flow lấy refresh_token mới.');
    line('  3. Refresh token unused > 6 tháng → Google tự invalidate.');
    line('     Fix: chạy lại OAuth flow.');
    line('  4. Password/2FA của tài khoản gửi thay đổi.');
    line('     Fix: chạy lại OAuth flow.');
    line('  5. GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN sai (copy-paste lỗi).');
    line('     Fix: verify trong Cloud Console.');
    line('  6. Server không ra được internet (firewall block oauth2.googleapis.com).');
    exit(1);
}
line('[OK]  Access token: ' . substr($token, 0, 12) . '... (len=' . strlen($token) . ')');

$to = $argv[1] ?? env_value('MAIL_FROM_ADDRESS');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    line('[SKIP] Không có địa chỉ nhận hợp lệ. Truyền: php tools/diagnose_gmail.php you@example.com');
    exit(0);
}

line('');
line('--- Step 2: send test email → ' . $to . ' ---');
$ok = send_custom_mail(
    $to,
    'Gau Bakery — Gmail diagnostic ' . date('c'),
    '<p>Đây là mail test từ <code>tools/diagnose_gmail.php</code>. Nếu bạn nhận được, Gmail API OK.</p>'
);
if ($ok) {
    line('[OK]  send_custom_mail() trả true. Check inbox (và Spam).');
    exit(0);
}
line('[FAIL] send_custom_mail() trả false. Xem PHP error log — thường là HTTP 400/403 từ Gmail API:');
line('  - 400 "Invalid grant" → refresh token invalidated (xem root causes trên).');
line('  - 403 "insufficientPermissions" → scope OAuth thiếu gmail.send / gmail.compose.');
line('  - 403 "Delegation denied" → GMAIL_USER_ID không khớp chủ token.');
exit(1);
