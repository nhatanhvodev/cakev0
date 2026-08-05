<?php
require_once __DIR__ . '/../../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/connect.php';

$pending = $_SESSION['pending_verification'] ?? null;
if (!is_array($pending) || ($pending['auth0_id'] ?? '') === '') {
    header('Location: ' . base_url('index.php'));
    exit;
}

$email = (string) ($pending['email'] ?? '');

// Xu ly gui lai email xac minh (PRG + cooldown 60s chong spam).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $now = time();
    $last = (int) ($_SESSION['verify_resend_at'] ?? 0);
    if ($now - $last < 60) {
        $_SESSION['toast'] = [
            'msg' => 'Vui lòng đợi khoảng 1 phút trước khi gửi lại email xác minh.',
            'type' => 'warning',
        ];
    } else {
        require_once __DIR__ . '/../../includes/auth0_management.php';
        if (auth0_send_verification_email((string) $pending['auth0_id'])) {
            $_SESSION['verify_resend_at'] = $now;
            $_SESSION['toast'] = [
                'msg' => 'Đã gửi lại email xác minh' . ($email !== '' ? ' tới ' . $email : '') . '. Kiểm tra inbox và Spam.',
                'type' => 'success',
            ];
        } else {
            $_SESSION['toast'] = [
                'msg' => 'Gửi lại email xác minh thất bại. Vui lòng thử lại sau.',
                'type' => 'error',
            ];
        }
    }
    header('Location: ' . base_url('pages/auth/verify-notice.php'));
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>
<main class="container" style="max-width:560px;margin:48px auto;padding:0 16px;">
  <div class="card" style="padding:28px;border-radius:14px;text-align:center;">
    <h1 style="margin:0 0 12px;font-size:22px;">Xác minh email của bạn</h1>
    <p style="color:var(--muted,#666);line-height:1.6;">
      Tài khoản đã được tạo. Chúng tôi đã gửi một liên kết xác minh
      <?php if ($email !== ''): ?>tới <strong><?= htmlspecialchars($email) ?></strong><?php endif; ?>.
      Vui lòng bấm liên kết trong email đó, sau đó đăng nhập lại.
    </p>
    <p style="color:var(--muted,#666);font-size:14px;">Không thấy email? Kiểm tra thư mục Spam hoặc gửi lại bên dưới.</p>

    <form method="post" action="<?= htmlspecialchars(base_url('pages/auth/verify-notice.php')) ?>" style="margin-top:18px;">
      <button type="submit" name="resend_verification" value="1" class="btn btn-primary" style="padding:10px 20px;">
        Gửi lại email xác minh
      </button>
    </form>

    <div style="margin-top:16px;">
      <a href="<?= htmlspecialchars(base_url('pages/auth/login.php')) ?>">Đã xác minh? Đăng nhập</a>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.html'; ?>
