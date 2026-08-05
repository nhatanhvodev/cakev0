<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../includes/auth_bridge.php';
require_once __DIR__ . '/../../config/connect.php';

$auth0 = auth0_client();

try {
    $auth0->exchange();
} catch (Throwable $e) {
    $reason = auth0_callback_error_reason($e, $_GET);
    $detail = auth0_callback_error_detail($e, $_GET);
    error_log(sprintf(
        '[auth0-callback] exchange_failed reason=%s class=%s message=%s detail=%s has_code=%s has_state=%s provider_error=%s',
        $reason,
        get_class($e),
        $e->getMessage(),
        $detail,
        isset($_GET['code']) ? 'yes' : 'no',
        isset($_GET['state']) ? 'yes' : 'no',
        (string) ($_GET['error'] ?? '')
    ));
    header('Location: ' . base_url('index.php?toast=auth_error&auth_reason=' . rawurlencode($reason) . '&auth_detail=' . rawurlencode($detail)));
    exit;
}

$credentials = $auth0->getCredentials();
if ($credentials === null || $credentials->user === null) {
    error_log('[auth0-callback] credentials_missing');
    header('Location: ' . base_url('index.php?toast=auth_error&auth_reason=no_credentials'));
    exit;
}

$claims = (array) $credentials->user;

// Chan dang nhap khi email chua xac minh: khong tao session app, dua ve trang chu
// kem thong bao. Auth0 da gui email xac minh luc dang ky (can email provider hoat dong).
if (!auth0_email_verified($claims)) {
    $auth0->clear();
    $_SESSION['pending_verification'] = [
        'auth0_id' => (string) ($claims['sub'] ?? ''),
        'email' => (string) ($claims['email'] ?? ''),
    ];
    header('Location: ' . base_url('pages/auth/verify-notice.php'));
    exit;
}

$user = sync_session_from_auth0($conn, $claims);

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
if ($stmt) {
    $uid = (int) $user['id'];
    $stmt->bind_param('is', $uid, $ip);
    $stmt->execute();
    $stmt->close();
}

$fallback = $user['role'] === 'admin' ? base_url('admin/index.php') : base_url('index.php');
$target = safe_redirect_target($_SESSION['auth_return_to'] ?? null, $fallback);
if ($user['role'] === 'admin') {
    $target = base_url('admin/index.php');
}
unset($_SESSION['auth_return_to']);

header('Location: ' . $target);
exit;
