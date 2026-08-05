<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =================================================================================
   PHẦN 1: KẾT NỐI DB & KHỞI TẠO
   ================================================================================= */
require_once '../config/config.php';
require_once '../config/uploadthing.php';
require_once '../config/connect.php';
require_once '../includes/order_helpers.php';
require_once '../includes/auth_helpers.php';
require_once '../includes/notifications.php';
//
$conn->set_charset("utf8mb4"); //

if ($conn->connect_error) {
    die("Lỗi kết nối DB: " . $conn->connect_error); //
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); //
    exit;
}

$user_id = (int) $_SESSION['user_id']; //
$error = '';
$success = '';

// Lấy thông báo từ session (nếu có)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']); //
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

function resolveAvatarUrl(?string $avatar): string
{
    $fallback = base_url('pages/uploads/default.png');
    if ($avatar === null) {
        return $fallback;
    }

    $avatar = trim(str_replace('\\', '/', $avatar));
    if ($avatar === '') {
        return $fallback;
    }

    if (is_remote_media_url($avatar)) {
        return $avatar;
    }

    $cakePos = stripos($avatar, '/cakev0/');
    if ($cakePos !== false) {
        $avatar = substr($avatar, $cakePos + 8);
    } elseif (stripos($avatar, 'cakev0/') === 0) {
        $avatar = substr($avatar, 7);
    }

    $avatar = ltrim($avatar, '/');
    if ($avatar === '') {
        return $fallback;
    }

    if (strpos($avatar, 'uploads/') === 0) {
        return base_url('pages/' . $avatar);
    }
    if (strpos($avatar, 'pages/uploads/') === 0 || strpos($avatar, 'assets/') === 0) {
        return base_url($avatar);
    }
    if (strpos($avatar, '/') === false) {
        return base_url('pages/uploads/' . $avatar);
    }

    return base_url($avatar);
}

function resolveAvatarLocalPath(?string $avatar): ?string
{
    if ($avatar === null || is_remote_media_url($avatar)) {
        return null;
    }

    $normalized = trim(str_replace('\\', '/', $avatar));
    if ($normalized === '') {
        return null;
    }

    $normalized = ltrim($normalized, '/');

    if (strpos($normalized, 'uploads/') === 0) {
        return __DIR__ . '/' . $normalized;
    }
    if (strpos($normalized, 'pages/uploads/') === 0) {
        return dirname(__DIR__) . '/' . $normalized;
    }
    if (strpos($normalized, 'assets/') === 0) {
        return dirname(__DIR__) . '/' . $normalized;
    }
    if (strpos($normalized, '/') === false) {
        return __DIR__ . '/uploads/' . $normalized;
    }

    return dirname(__DIR__) . '/' . $normalized;
}

function prepareAvatarUploadPayload(string $tmpPath, string $ext): array
{
    $result = [
        'path' => $tmpPath,
        'mime' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
        'ext' => $ext === 'jpg' ? 'jpeg' : $ext,
        'cleanup' => null,
    ];

    if ($tmpPath === '' || !is_file($tmpPath)) {
        return $result;
    }

    $size = @filesize($tmpPath);
    if ($size !== false && $size <= 900 * 1024) {
        return $result;
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return $result;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    $pixelCount = $width * $height;
    $maxSide = 1200;

    // Skip server-side optimization for very large images to avoid exhausting
    // memory on small production containers (for example Render free instances).
    if ($width <= 0 || $height <= 0 || $pixelCount > 4_000_000 || $width > 2200 || $height > 2200) {
        return $result;
    }

    $createMap = [
        'jpg' => 'imagecreatefromjpeg',
        'jpeg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'webp' => 'imagecreatefromwebp',
    ];
    $saveMap = [
        'jpg' => 'imagejpeg',
        'jpeg' => 'imagejpeg',
        'png' => 'imagepng',
        'webp' => 'imagewebp',
    ];

    $createFn = $createMap[$ext] ?? null;
    $saveFn = $saveMap[$ext] ?? null;
    if ($createFn === null || $saveFn === null || !function_exists($createFn) || !function_exists($saveFn)) {
        return $result;
    }

    $source = @$createFn($tmpPath);
    if (!$source) {
        return $result;
    }

    $scale = min(1, $maxSide / max($width, $height));
    $targetW = max(1, (int) round($width * $scale));
    $targetH = max(1, (int) round($height * $scale));

    $target = imagecreatetruecolor($targetW, $targetH);
    if (!$target) {
        imagedestroy($source);
        return $result;
    }

    if ($ext === 'png' || $ext === 'webp') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
    }

    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height)) {
        imagedestroy($source);
        imagedestroy($target);
        return $result;
    }

    $optimizedPath = tempnam(sys_get_temp_dir(), 'avatar_opt_');
    if ($optimizedPath === false) {
        imagedestroy($source);
        imagedestroy($target);
        return $result;
    }

    $saved = false;
    $preferWebp = function_exists('imagewebp') && $ext !== 'webp';
    if ($preferWebp) {
        imagesavealpha($target, true);
        $saved = @imagewebp($target, $optimizedPath, 80);
        if ($saved) {
            $result['mime'] = 'image/webp';
            $result['ext'] = 'webp';
        }
    }

    if (!$saved && ($ext === 'jpg' || $ext === 'jpeg')) {
        $saved = @$saveFn($target, $optimizedPath, 82);
        $result['mime'] = 'image/jpeg';
        $result['ext'] = 'jpg';
    } elseif (!$saved && $ext === 'png') {
        $saved = @$saveFn($target, $optimizedPath, 6);
        $result['mime'] = 'image/png';
        $result['ext'] = 'png';
    } elseif (!$saved && $ext === 'webp') {
        $saved = @$saveFn($target, $optimizedPath, 80);
        $result['mime'] = 'image/webp';
        $result['ext'] = 'webp';
    }

    imagedestroy($source);
    imagedestroy($target);

    if (!$saved || !is_file($optimizedPath)) {
        @unlink($optimizedPath);
        return $result;
    }

    $result['path'] = $optimizedPath;
    $result['cleanup'] = $optimizedPath;

    return $result;
}

function persistAvatarFileLocally(string $sourcePath, string $fileName): ?string
{
    if ($sourcePath === '' || !is_file($sourcePath)) {
        return null;
    }

    $uploadDirFs = __DIR__ . '/uploads';
    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0777, true) && !is_dir($uploadDirFs)) {
        return null;
    }

    $targetPath = rtrim($uploadDirFs, '/\\') . '/' . $fileName;
    if (is_uploaded_file($sourcePath)) {
        if (!move_uploaded_file($sourcePath, $targetPath)) {
            return null;
        }
    } else {
        if (!@rename($sourcePath, $targetPath)) {
            if (!@copy($sourcePath, $targetPath)) {
                return null;
            }
            @unlink($sourcePath);
        }
    }

    return 'uploads/' . $fileName;
}

function storeAvatarUploadLocally(array $avatarFile, int $userId, string $ext): ?string
{
    $tmpAvatarPath = (string) ($avatarFile['tmp_name'] ?? '');
    if ($tmpAvatarPath === '') {
        return null;
    }

    $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $preparedUpload = prepareAvatarUploadPayload($tmpAvatarPath, $ext);
    $preparedExt = (string) ($preparedUpload['ext'] ?? $ext);

    if ($preparedExt !== '') {
        $newName = preg_replace('/\.[a-z0-9]+$/i', '', $newName) . '.' . $preparedExt;
    }

    $storedPath = persistAvatarFileLocally((string) $preparedUpload['path'], $newName);

    if (!empty($preparedUpload['cleanup']) && is_string($preparedUpload['cleanup']) && is_file($preparedUpload['cleanup'])) {
        @unlink($preparedUpload['cleanup']);
    }

    return $storedPath;
}

function allowLocalAvatarFallback(): bool
{
    return strtolower((string) env_value('APP_ENV', 'development')) !== 'production';
}

/* =================================================================================
   PHẦN 2: XỬ LÝ FORM (POST REQUESTS)
   ================================================================================= */

// Lấy thông tin người dùng hiện tại để xử lý logic
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?"); //
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user)
    die("Không tìm thấy thông tin người dùng."); //

// --- LOGIC 1: CẬP NHẬT THÔNG TIN & AVATAR ---
if (isset($_POST['update_profile'])) {
    $ten = trim($_POST['ten']);
    $email = trim($_POST['email']);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $avatar_name = $user['avatar']; // Mặc định giữ ảnh cũ

    // Xử lý Upload Ảnh
    if (!empty($_FILES['avatar']['name'])) { //
        $avatarFile = $_FILES['avatar'];
        $uploadError = (int) ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ảnh vượt quá dung lượng cho phép của máy chủ.',
                UPLOAD_ERR_PARTIAL => 'Ảnh được tải lên chưa hoàn tất, vui lòng thử lại.',
                UPLOAD_ERR_NO_FILE => 'Bạn chưa chọn ảnh để tải lên.',
                default => 'Không thể tải ảnh lên máy chủ.',
            };
        } else {
            $ext = strtolower(pathinfo((string) ($avatarFile['name'] ?? ''), PATHINFO_EXTENSION));
        }
        $allow = ['jpg', 'jpeg', 'png', 'webp'];

        if (empty($error) && in_array($ext, $allow, true)) { //
            $avatarSize = (int) ($avatarFile['size'] ?? 0);
            if ($avatarSize > 8 * 1024 * 1024) {
                $error = "Ảnh quá lớn (tối đa 8MB). Vui lòng chọn ảnh nhỏ hơn.";
            }
        }

        if (empty($error) && in_array($ext, $allow, true)) { //
            $oldAvatar = $avatar_name;
            $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $tmpAvatarPath = (string) ($avatarFile['tmp_name'] ?? '');
            if ($tmpAvatarPath === '' || !is_uploaded_file($tmpAvatarPath)) {
                $error = 'Tệp tải lên không hợp lệ, vui lòng thử lại.';
            }

            $uploadedUrl = null;
            if (empty($error)) {
                $preparedUpload = [
                    'path' => $tmpAvatarPath,
                    'mime' => (string) ($avatarFile['type'] ?? ''),
                    'ext' => $ext,
                    'cleanup' => null,
                ];

                if (uploadthing_enabled()) {
                    $preparedUpload = prepareAvatarUploadPayload($tmpAvatarPath, $ext);
                }

                $uploadMime = $preparedUpload['mime'] ?: ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
                $remoteExt = (string) ($preparedUpload['ext'] ?? $ext);
                $remoteName = preg_replace('/\.[a-z0-9]+$/i', '', $new_name) . '.' . $remoteExt;

                try {
                    $uploadedUrl = uploadthing_upload_file(
                        $preparedUpload['path'],
                        $remoteName,
                        $uploadMime
                    );
                } finally {
                    if (!empty($preparedUpload['cleanup']) && is_string($preparedUpload['cleanup'])) {
                        @unlink($preparedUpload['cleanup']);
                    }
                }
            }
            if ($uploadedUrl !== null) {
                $avatar_name = $uploadedUrl;
            } elseif (empty($error) && allowLocalAvatarFallback()) {
                $storedAvatarPath = storeAvatarUploadLocally($avatarFile, $user_id, $ext);
                if ($storedAvatarPath !== null) {
                    $avatar_name = $storedAvatarPath;
                } else {
                    $error = 'Khong the luu file anh.';
                }
            } elseif (empty($error)) {
                $error = 'Khong the tai avatar len UploadThing. Vui long thu lai voi anh nho hon.';
            }
            if (empty($error) && $oldAvatar !== $avatar_name) {
                $oldAvatarPath = resolveAvatarLocalPath($oldAvatar);
                if ($oldAvatarPath !== null && is_file($oldAvatarPath)) {
                    @unlink($oldAvatarPath);
                }
            }
        } elseif (empty($error)) {
            $error = "Định dạng ảnh không hợp lệ (Chỉ nhận JPG, PNG, WEBP)."; //
        }
    }

    // Cập nhật DB nếu không có lỗi upload
    if (empty($error)) {
        $sql_update = "UPDATE users SET username=?, email=?, phone=?, avatar=? WHERE id=?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssssi", $ten, $email, $phone, $avatar_name, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "🎉 Cập nhật hồ sơ thành công!";
            $_SESSION['username'] = $ten;
            $_SESSION['avatar'] = $avatar_name;
            header("Location: account.php");
            exit;
        } else {
            $error = "Lỗi hệ thống: " . $conn->error;
        }
        $stmt->close();
    }
}

// --- LOGIC 2: MẬT KHẨU DO AUTH0 QUẢN LÝ ---
if (isset($_POST['change_password'])) {
    header('Location: ' . base_url('pages/forgot-password.php'));
    exit;
}

// --- LOGIC 3: HỦY ĐƠN HÀNG (CHỈ KHI ĐANG CHỜ) ---
if (isset($_POST['cancel_order'])) {
    $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    if ($orderId <= 0) {
        $_SESSION['error'] = 'Đơn hàng không hợp lệ.';
    } else {
        $stmt = $conn->prepare(
            "SELECT status, payment_method FROM orders WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->bind_param('ii', $orderId, $user_id);
        $stmt->execute();
        $cancelOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cancelOrder || !canCustomerCancelOrder((string) ($cancelOrder['payment_method'] ?? ''), (string) ($cancelOrder['status'] ?? ''))) {
            $_SESSION['error'] = 'Chỉ có thể hủy đơn COD khi đơn còn chờ duyệt.';
        } else {
            $paymentMethod = (string) $cancelOrder['payment_method'];
            $stmt = $conn->prepare(
                "UPDATE orders SET status = 'cancelled'
                 WHERE id = ? AND user_id = ? AND payment_method = ? AND LOWER(status) IN ('pending', 'cod_not_deposited')"
            );
            $stmt->bind_param('iis', $orderId, $user_id, $paymentMethod);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                notifyOrderStatusChanged($conn, $user_id, $orderId, (string) ($cancelOrder['status'] ?? ''), 'cancelled');
                $_SESSION['success'] = 'Đã hủy đơn hàng thành công.';
            } else {
                $_SESSION['error'] = 'Không thể hủy đơn. Đơn có thể đã được xử lý.';
            }
            $stmt->close();
        }
    }
    header('Location: account.php');
    exit;
}

/* =================================================================================
   PHẦN 3: LẤY DỮ LIỆU HIỂN THỊ (GET REQUESTS)
   ================================================================================= */

// 1. Lấy Lịch sử đơn hàng
$stmt = $conn->prepare("SELECT id, total_amount, payment_method, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC"); //
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_spent = 0;
foreach ($orders as $order) {
    $total_spent += (float) ($order['total_amount'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang cá nhân - <?= htmlspecialchars($user['username']) ?></title> <!-- -->

    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        :root {
            --brown-900: #3c1819;
            --brown-800: #4a1d1f;
            --brown-700: #6a2d22;
            --caramel: #f3e0be;
            --cream: #fff7ea;
            --ink: #272727;
        }

        body {
            background: radial-gradient(circle at 12% 18%, #fff3da 0%, transparent 45%),
                radial-gradient(circle at 90% 12%, #fde8c6 0%, transparent 40%),
                #ffffff;
            font-family: 'Poppins', sans-serif;
            color: var(--ink);
            overflow-x: hidden;
        }

        .account-shell {
            max-width: 1180px;
            margin: 10px auto 8px;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            height: min(560px, max(420px, calc(100dvh - 236px)));
            min-height: 0;
        }

        .account-hero {
            background: linear-gradient(135deg, #fff7ea, #fdf1db);
            border: 1px solid var(--caramel);
            border-radius: 20px;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 10px 24px rgba(74, 29, 31, 0.08);
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--caramel);
            font-size: 11px;
            color: var(--brown-700);
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .account-hero h1 {
            margin: 5px 0 2px;
            color: var(--brown-800);
            font-size: 22px;
            line-height: 1.2;
        }

        .account-hero p {
            margin: 0;
            color: #4a4a4a;
            font-size: 14px;
            line-height: 1.35;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-soft {
            border: 1px solid var(--caramel);
            background: #fff;
            color: var(--brown-700);
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 600;
            text-decoration: none;
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-outline {
            border: 1px solid var(--brown-800);
            background: transparent;
            color: var(--brown-800);
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 600;
            text-decoration: none;
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .account-grid {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 16px;
            align-items: stretch;
            flex: 1 1 auto;
            min-height: 0;
        }

        .profile-card {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid var(--caramel);
            box-shadow: 0 16px 32px rgba(74, 29, 31, 0.08);
            align-self: start;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .profile-avatar {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 16px;
            border: 2px solid #fff;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
        }

        .profile-meta h4 {
            margin: 0;
            font-weight: 700;
            color: var(--brown-800);
            font-size: 22px;
        }

        .profile-meta p {
            margin: 4px 0 0;
            color: #6a6a6a;
            font-size: 13px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 14px 0;
        }

        .stat-item {
            background: #fff7ea;
            border: 1px solid var(--caramel);
            border-radius: 14px;
            padding: 10px;
            text-align: center;
        }

        .stat-item strong {
            display: block;
            color: var(--brown-800);
            font-size: 16px;
        }

        .stat-item span {
            font-size: 12px;
            color: #7c6b67;
        }

        .profile-actions {
            display: grid;
            gap: 10px;
        }

        .content-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid var(--caramel);
            box-shadow: 0 16px 32px rgba(74, 29, 31, 0.08);
            min-height: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            align-self: stretch;
        }

        .nav-tabs {
            border-bottom: none;
            gap: 10px;
            flex-wrap: nowrap;
            flex: 0 0 auto;
            min-height: 38px;
            margin-bottom: 10px !important;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
        }

        .nav-tabs::-webkit-scrollbar {
            display: none;
        }

        .nav-tabs .nav-link {
            border: 1px solid var(--caramel);
            border-radius: 12px;
            font-weight: 600;
            color: var(--brown-700);
            background: #fff7ea;
            padding: 8px 16px;
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: .25s;
        }

        .nav-tabs .nav-link.active {
            background: var(--brown-800);
            color: #fbedcd;
            border-color: var(--brown-800);
            box-shadow: 0 6px 18px rgba(74, 29, 31, .25);
        }

        .section-title {
            font-weight: 700;
            color: var(--brown-800);
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
        }

        .content-card .tab-content {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .content-card .tab-pane.active {
            flex: 1 1 auto;
            min-height: 0;
        }

        #orders-tab.active {
            display: flex;
            flex-direction: column;
        }

        #settings-tab.active {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding-right: 0;
        }

        #settings-tab > .row {
            --bs-gutter-x: 0;
            --bs-gutter-y: 0;
            margin: 0;
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(0, 0.96fr);
            gap: 12px;
            align-items: stretch;
        }

        #settings-tab > .row > hr {
            display: none;
        }

        #settings-tab > .row > [class*="col-"] {
            width: auto;
            max-width: none;
            margin: 0 !important;
            padding: 12px;
            border: 1px solid rgba(74, 29, 31, 0.14);
            border-radius: 14px;
            background: linear-gradient(180deg, #fffdf8 0%, #fff9ef 100%);
            box-shadow: 0 8px 18px rgba(74, 29, 31, 0.06);
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #settings-tab form {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
        }

        #settings-tab form > .row {
            --bs-gutter-x: 10px;
            --bs-gutter-y: 8px;
            width: 100%;
            margin: 0;
            align-content: start;
        }

        #settings-tab .form-label {
            margin-bottom: 4px;
            color: #7b6d63 !important;
            font-size: 11px !important;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        #settings-tab .form-control {
            min-height: 38px;
            border-radius: 10px;
            border-color: rgba(74, 29, 31, 0.14);
            background-color: #fff;
            padding: 7px 10px;
            color: var(--brown-800);
            font-size: 13px;
            box-shadow: none;
        }

        #settings-tab .form-control:focus {
            border-color: var(--brown-700);
            box-shadow: 0 0 0 3px rgba(106, 45, 34, 0.12);
        }

        #settings-tab .form-control-sm {
            min-height: 36px;
            padding: 6px 10px;
        }

        #settings-tab .col-12:last-child {
            display: flex;
            align-items: flex-end;
        }

        .account-orders-panel {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 10px;
            overflow: hidden;
        }

        .account-orders-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 32px;
        }

        .account-orders-sub {
            margin: 2px 0 0;
            color: #86766b;
            font-size: 12px;
        }

        .account-orders-count {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 30px;
            padding: 5px 10px;
            border-radius: 8px;
            background: #fff7ea;
            border: 1px solid var(--caramel);
            color: var(--brown-700);
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .account-table-wrap {
            min-height: 0;
            height: 100%;
            max-width: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid rgba(74, 29, 31, 0.14);
            border-radius: 12px;
            background: #fffdf8;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .account-orders-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0;
        }

        .account-orders-table th:nth-child(1),
        .account-orders-table td:nth-child(1) {
            width: 13%;
        }

        .account-orders-table th:nth-child(2),
        .account-orders-table td:nth-child(2) {
            width: 19%;
        }

        .account-orders-table th:nth-child(3),
        .account-orders-table td:nth-child(3) {
            width: 22%;
        }

        .account-orders-table th:nth-child(4),
        .account-orders-table td:nth-child(4) {
            width: 26%;
        }

        .account-orders-table th:nth-child(5),
        .account-orders-table td:nth-child(5) {
            width: 20%;
        }

        .account-orders-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 11px 14px 10px;
            background: #fffdf8;
            box-shadow: 0 1px 0 rgba(74, 29, 31, 0.14);
            color: #9c958c;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .account-orders-table td {
            padding: 11px 14px;
            border-top: 1px solid rgba(74, 29, 31, 0.1);
            color: #2f2b27;
            font-size: 13.5px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .account-orders-table tbody tr {
            transition: background 0.12s ease;
        }

        .account-orders-table tbody tr:hover {
            background: #f5f2ea;
        }

        .account-order-id {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            min-height: 24px;
            padding: 3px 9px;
            border-radius: 8px;
            background: #697480;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .account-order-total {
            color: #13815a;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .account-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 26px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .account-status-pill::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .account-status-success {
            color: #16804d;
            background: #e5f5eb;
        }

        .account-status-warning {
            color: #a56a09;
            background: #fff1d7;
        }

        .account-status-primary {
            color: #2878c7;
            background: #e5f0ff;
        }

        .account-status-info {
            color: #25849b;
            background: #e1f6fb;
        }

        .account-status-danger {
            color: #ba3b32;
            background: #fee9e7;
        }

        .account-status-secondary {
            color: #67737f;
            background: #edf0f2;
        }

        .account-order-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .account-order-btn {
            min-height: 34px;
            padding: 6px 12px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease;
        }

        .account-order-btn:active {
            transform: scale(0.97);
        }

        .account-order-btn-view {
            border: 1px solid var(--caramel);
            background: #fff;
            color: var(--brown-800);
        }

        .account-order-btn-view:hover {
            border-color: var(--brown-800);
            color: var(--brown-800);
            background: #fff7ea;
        }

        .account-order-btn-cancel {
            border: 1px solid #f1b3ad;
            background: #fff;
            color: #b42318;
        }

        .account-order-btn-cancel:hover {
            background: #fff2f0;
            color: #7a271a;
        }

        .subsection-title {
            font-weight: 700;
            color: var(--brown-800);
            margin-bottom: 14px;
        }

        #settings-tab .subsection-title {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.2;
        }

        .subsection-title.danger {
            color: #b42318;
        }

        .btn-theme {
            background: var(--brown-800);
            color: #fbedcd;
            border: none;
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 600;
        }

        #settings-tab .btn-theme,
        #settings-tab .btn-theme-danger {
            width: 100%;
            min-height: 38px;
            padding: 8px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
        }

        .btn-theme:hover {
            background: #2f1415;
            color: #fbedcd;
        }

        .btn-theme-danger {
            background: #b42318;
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 600;
        }

        .btn-theme-danger:hover {
            background: #7a271a;
            color: #fff;
        }

        .confirm-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 3000;
        }

        .confirm-modal.is-open {
            display: flex;
        }

        .confirm-modal-box {
            background: #fff;
            width: 92%;
            max-width: 420px;
            border-radius: 22px;
            padding: 28px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.22);
            animation: fadeUp 0.25s ease;
        }

        .confirm-modal-title {
            margin: 0 0 8px;
            font-size: 18px;
            color: var(--brown-800);
            font-weight: 700;
        }

        .confirm-modal-desc {
            margin: 0 0 20px;
            color: #6b6b6b;
            font-size: 14px;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        @media (max-width: 992px) {
            .account-shell {
                height: auto;
                min-height: 0;
                margin: 16px auto 32px;
                gap: 16px;
            }

            .account-grid {
                grid-template-columns: 1fr;
                flex: none;
            }
            .account-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .content-card {
                height: min(620px, calc(100dvh - 190px));
            }

            #settings-tab > .row {
                grid-template-columns: 1fr;
            }

            #settings-tab.active {
                overflow: auto;
            }

            #settings-tab > .row > [class*="col-"] {
                overflow: visible;
            }
        }

        @media (max-width: 768px) {
            .account-shell {
                padding: 0 16px;
                margin: 14px auto 28px;
            }

            .account-hero {
                padding: 14px;
            }

            .account-hero h1 {
                font-size: 20px;
            }

            .profile-card,
            .content-card {
                padding: 16px;
            }

            .content-card {
                height: min(560px, calc(100dvh - 180px));
            }

            .content-card.settings-mode {
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .content-card:has(#settings-tab.active) {
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .content-card.settings-mode .tab-content,
            .content-card.settings-mode .tab-pane.active {
                flex: 0 0 auto;
                min-height: 0;
                overflow: visible;
            }

            .content-card:has(#settings-tab.active) .tab-content,
            .content-card:has(#settings-tab.active) .tab-pane.active {
                flex: 0 0 auto;
                min-height: 0;
                overflow: visible;
            }

            .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 13px;
            }

            #settings-tab.active {
                overflow: visible;
            }

            #settings-tab > .row {
                display: flex;
                flex-direction: column;
                gap: 12px;
                min-height: 0;
                overflow: visible;
            }

            #settings-tab > .row > [class*="col-"] {
                padding: 14px;
                border-radius: 16px;
                overflow: visible;
            }

            #settings-tab form,
            #settings-tab form > .row {
                display: block;
            }

            #settings-tab form > .row > [class*="col-"] {
                margin-bottom: 12px;
            }

            #settings-tab .form-label {
                margin-bottom: 5px;
                font-size: 10.5px !important;
            }

            #settings-tab .form-control,
            #settings-tab .form-control-sm {
                min-height: 44px;
                padding: 9px 12px;
                font-size: 14px;
            }

            #settings-tab .form-control::placeholder {
                font-size: 12px;
            }

            #settings-tab .col-12:last-child {
                display: block;
            }

            #settings-tab .btn-theme,
            #settings-tab .btn-theme-danger {
                min-height: 44px;
                font-size: 14px;
            }

            .account-table-wrap {
                overflow-y: auto;
                overflow-x: hidden;
            }

            .account-orders-table {
                min-width: 0;
                table-layout: auto;
            }

            .account-orders-table thead {
                display: none;
            }

            .account-orders-table tbody,
            .account-orders-table tr,
            .account-orders-table td {
                display: block;
                width: 100%;
            }

            .account-orders-table th:nth-child(n),
            .account-orders-table td:nth-child(n) {
                width: 100%;
            }

            .account-orders-table tbody {
                display: grid;
                gap: 14px;
            }

            .account-orders-table tr {
                border: 1px solid var(--caramel);
                border-radius: 18px;
                padding: 14px;
                background: #fffdf8;
                box-shadow: 0 10px 22px rgba(74, 29, 31, 0.08);
            }

            .account-orders-table tbody tr:hover {
                background: #fffdf8;
            }

            .account-orders-table td {
                border: 0;
                padding: 8px 0;
            }

            .account-orders-table td[data-label] {
                display: grid;
                grid-template-columns: minmax(84px, 96px) minmax(0, 1fr);
                gap: 10px;
                align-items: start;
                min-width: 0;
            }

            .account-orders-table td[data-label="Ngày đặt"],
            .account-orders-table td[data-label="Tổng tiền"] {
                white-space: nowrap;
                overflow-wrap: normal;
                word-break: normal;
            }

            .account-orders-table td[data-label]::before {
                content: attr(data-label);
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--brown-700);
            }

            .account-orders-table .order-action-cell {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding-top: 12px;
                border-top: 1px dashed var(--caramel);
                margin-top: 4px;
            }

            .account-orders-table .order-action-cell::before {
                display: none;
            }
        }

        .scroll-top {
            position: fixed;
            right: 20px;
            top: 80%;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: var(--brown-800);
            color: #fbedcd;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 24px rgba(74, 29, 31, 0.25);
            opacity: 0;
            visibility: hidden;
            transform: translateY(calc(-50% + 6px));
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 2000;
        }

        .scroll-top.is-visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%);
        }

        .scroll-top:hover {
            background: #2f1415;
        }
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>

    <div class="account-shell">

        <script>
            <?php if ($success): ?>
                window.showToast(<?= json_encode($success) ?>, 'success');
            <?php endif; ?>
            <?php if ($error): ?>
                window.showToast(<?= json_encode($error) ?>, 'error');
            <?php endif; ?>
        </script>

        <div class="account-hero">
            <div>
                <span class="hero-chip">Tài khoản</span>
                <h1>Xin chào, <?= htmlspecialchars($user['username']) ?></h1>
                <p>Quản lý đơn hàng, cập nhật hồ sơ và bảo mật tài khoản của bạn.</p>
            </div>
            <div class="hero-actions">
                <a href="/cakev0/pages/product.php" class="btn-outline"><i class="fa-solid fa-cookie"></i> Mua thêm</a>
                <a href="/cakev0/pages/favorites.php" class="btn-soft"><i class="fa-regular fa-heart"></i> Sản phẩm đã lưu</a>
            </div>
        </div>

        <div class="account-grid">
            <div class="profile-card">
                <div class="profile-header">
                    <img src="<?= htmlspecialchars(resolveAvatarUrl($user['avatar'] ?? null), ENT_QUOTES) ?>"
                        class="profile-avatar">
                    <div class="profile-meta">
                        <h4><?= htmlspecialchars($user['username']) ?></h4>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-item">
                        <strong><?= count($orders) ?></strong>
                        <span>Đơn hàng</span>
                    </div>
                    <div class="stat-item">
                        <strong><?= number_format($total_spent, 0, ',', '.') ?> VNĐ</strong>
                        <span>Tổng chi tiêu</span>
                    </div>
                </div>

                <div class="profile-actions">
                    <form id="logoutForm" action="logout.php" method="POST">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                        </button>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#orders-tab">
                            <i class="fa-solid fa-receipt"></i> Đơn hàng
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings-tab">
                            <i class="fa-solid fa-gear"></i> Cài đặt
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                        <!-- TAB 1: LỊCH SỬ ĐƠN HÀNG -->
                        <div class="tab-pane fade show active" id="orders-tab">
                            <div class="account-orders-panel">
                                <div class="account-orders-head">
                                    <div>
                                        <h5 class="section-title">Lịch sử mua hàng</h5>
                                        <p class="account-orders-sub">Theo dõi đơn gần đây và trạng thái xử lý.</p>
                                    </div>
                                    <span class="account-orders-count"><i class="fa-solid fa-receipt"></i> <?= count($orders) ?> đơn</span>
                                </div>
                            <?php if (count($orders) > 0): ?>
                                <div class="table-responsive account-table-wrap">
                                    <table class="account-orders-table">
                                        <thead>
                                            <tr>
                                                <th>Mã ĐH</th>
                                                <th>Ngày đặt</th>
                                                <th>Tổng tiền</th>
                                                <th>Trạng thái</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $o): ?>
                                                <tr>
                                                    <td data-label="Mã ĐH"><span class="account-order-id">#<?= $o['id'] ?></span></td>
                                                    <td data-label="Ngày đặt"><?= date("d/m/Y", strtotime($o['created_at'])) ?></td>
                                                    <td data-label="Tổng tiền" class="account-order-total"><?= number_format($o['total_amount']) ?> VNĐ
                                                    </td>
                                                    <td data-label="Trạng thái">
                                                        <?php
                                                        $statusData = match (strtolower($o['status'])) {
                                                            'completed', 'thanh cong' => ['tone' => 'success', 'label' => 'Hoàn tất'],
                                                            'pending', 'cho xu ly' => ['tone' => 'warning', 'label' => 'Đang chờ xác nhận'],
                                                            'cod_not_deposited' => ['tone' => 'warning', 'label' => 'Chờ xác nhận COD'],
                                                            'cod_deposited' => ['tone' => 'primary', 'label' => 'COD đã xác nhận'],
                                                            'paid' => ['tone' => 'primary', 'label' => 'Đã thanh toán'],
                                                            'approved', 'confirmed' => ['tone' => 'info', 'label' => 'Đã xác nhận'],
                                                            'delivering' => ['tone' => 'info', 'label' => 'Đang giao'],
                                                            'delivered', 'da giao' => ['tone' => 'info', 'label' => 'Đã giao'],
                                                            'failed' => ['tone' => 'danger', 'label' => 'Thanh toán lỗi'],
                                                            'cancelled', 'huy' => ['tone' => 'danger', 'label' => 'Đã hủy'],
                                                            default => ['tone' => 'secondary', 'label' => 'Không rõ']
                                                        };
                                                        ?>
                                                        <span class="account-status-pill account-status-<?= $statusData['tone'] ?>">
                                                            <?= $statusData['label'] ?>
                                                        </span>
                                                    </td>
                                                    <td data-label="Thao tác" class="order-action-cell">
                                                        <span class="account-order-actions">
                                                        <a href="/cakev0/pages/order-detail.php?id=<?= $o['id'] ?>"
                                                            class="account-order-btn account-order-btn-view"><i class="fa-regular fa-eye"></i> Xem</a>
                                                        <?php if (canCustomerCancelOrder((string) ($o['payment_method'] ?? ''), (string) $o['status'])): ?>
                                                            <button type="button"
                                                                class="account-order-btn account-order-btn-cancel cancel-order-btn"
                                                                data-order-id="<?= $o['id'] ?>">
                                                                <i class="fa-regular fa-circle-xmark"></i> Hủy đơn
                                                            </button>
                                                        <?php endif; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-cart-arrow-down fa-3x mb-3"></i>
                                    <p>Bạn chưa có đơn hàng nào.</p>
                                </div>
                            <?php endif; ?>
                            </div>

                            <div id="cancelOrderModal" class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="cancelOrderTitle">
                                <div class="confirm-modal-box">
                                    <div class="confirm-modal-title" id="cancelOrderTitle">Hủy đơn hàng?</div>
                                    <p class="confirm-modal-desc" id="cancelOrderDesc">Đơn hàng sẽ được chuyển sang trạng thái đã hủy.</p>
                                    <div class="confirm-modal-actions">
                                        <button type="button" class="btn btn-outline-secondary" id="cancelOrderCancel">Hủy đơn</button>
                                        <form method="POST" id="cancelOrderForm">
                                            <input type="hidden" name="order_id" id="cancelOrderId" value="">
                                            <button type="submit" name="cancel_order" class="btn btn-danger">Xác nhận hủy đơn</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: CÀI ĐẶT -->
                        <div class="tab-pane fade" id="settings-tab">
                            <div class="row">
                                <!-- Form Cập nhật thông tin -->
                                <div class="col-md-12 mb-4">
                                    <h6 class="subsection-title"><i class="fa-solid fa-user-pen"></i> Cập nhật thông tin</h6>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">Ảnh đại diện mới</label>
                                                <input type="file" name="avatar" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Họ và tên</label>
                                                <input type="text" name="ten" class="form-control"
                                                    value="<?= htmlspecialchars($user['username']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Số điện thoại</label>
                                                <input type="text" name="phone" class="form-control"
                                                    value="<?= html_escape($user['phone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">Email</label>
                                                <input type="email" name="email" class="form-control"
                                                    value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="update_profile" class="btn-theme">Lưu thay đổi</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <hr>
                                <!-- Form Đổi mật khẩu -->
                                <div class="col-md-12">
                                    <h6 class="subsection-title danger"><i class="fa-solid fa-shield-halved"></i> Bảo mật</h6>
                                    <a href="forgot-password.php" class="btn-theme-danger">Đổi mật khẩu qua Auth0</a>
                                </div>
                            </div>
                        </div> <!-- End Tab Content -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.html'; ?>

    <button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

    <script>
        const cancelOrderModal = document.getElementById('cancelOrderModal');
        const cancelOrderCancel = document.getElementById('cancelOrderCancel');
        const cancelOrderId = document.getElementById('cancelOrderId');
        const cancelOrderDesc = document.getElementById('cancelOrderDesc');
        const accountContentCard = document.querySelector('.content-card');
        const settingsTabPane = document.getElementById('settings-tab');

        function syncAccountContentMode() {
            if (!accountContentCard || !settingsTabPane) {
                return;
            }
            accountContentCard.classList.toggle('settings-mode', settingsTabPane.classList.contains('active'));
        }

        document.querySelectorAll('#profileTab button[data-bs-toggle="tab"]').forEach(function (tabButton) {
            tabButton.addEventListener('shown.bs.tab', syncAccountContentMode);
            tabButton.addEventListener('click', function () {
                setTimeout(syncAccountContentMode, 0);
            });
        });
        syncAccountContentMode();

        function closeCancelOrderModal() {
            cancelOrderModal.classList.remove('is-open');
            cancelOrderId.value = '';
        }

        document.querySelectorAll('.cancel-order-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.dataset.orderId || '';
                cancelOrderId.value = id;
                cancelOrderDesc.textContent = 'Đơn hàng #' + id + ' sẽ được chuyển sang trạng thái đã hủy.';
                cancelOrderModal.classList.add('is-open');
            });
        });

        cancelOrderCancel.addEventListener('click', closeCancelOrderModal);

        cancelOrderModal.addEventListener('click', function (event) {
            if (event.target === cancelOrderModal) {
                closeCancelOrderModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && cancelOrderModal.classList.contains('is-open')) {
                closeCancelOrderModal();
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const scrollTopBtn = document.getElementById('scrollTopBtn');
            if (!scrollTopBtn) return;

            const toggleScrollTop = function () {
                scrollTopBtn.classList.toggle('is-visible', window.scrollY > 300);
            };

            toggleScrollTop();
            window.addEventListener('scroll', toggleScrollTop, { passive: true });

            scrollTopBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

