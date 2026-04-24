<?php
/**
 * ADMIN DASHBOARD - FINAL VERSION
 */

// 1. KẾT NỐI VÀ CẤU HÌNH
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once '../config/config.php';
require_once '../config/uploadthing.php';
require_once '../config/connect.php';
require_once '../includes/mailer.php';

// Hàm tạo lại CSRF Token
function regenerateCsrfToken()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    regenerateCsrfToken();
}

// Hàm hỗ trợ set Toast Message
function setAdminToast($msg, $type = 'success')
{
    $_SESSION['admin_toast'] = ['msg' => $msg, 'type' => $type];
}

function redirectToTab(string $tab): void
{
    header("Location: admin.php?tab={$tab}#{$tab}");
    exit;
}

function isCodPaymentMethod(?string $paymentMethod): bool
{
    $normalized = strtolower(trim((string) $paymentMethod));
    if ($normalized === '') {
        return false;
    }

    return $normalized === 'tiền mặt'
        || $normalized === 'tien mat'
        || $normalized === 'cod'
        || str_contains($normalized, 'cod');
}

// Hàm xử lý đường dẫn ảnh (Kết hợp logic từ nguồn)
function buildImageUrl(string $relativePath): array
{
    $defaultImage = base_url('assets/img/no-image.jpg');
    $result = ['url' => $defaultImage];

    if (empty($relativePath))
        return $result;

    $relativePath = trim(str_replace('\\', '/', $relativePath));
    if ($relativePath === '') {
        return $result;
    }

    if (is_remote_media_url($relativePath)) {
        $result['url'] = $relativePath;
        return $result;
    }

    if (strpos($relativePath, 'assets/') === false && strpos($relativePath, 'img/') === 0) {
        $relativePath = 'assets/' . $relativePath;
    }
    if (strpos($relativePath, 'uploads/') === 0) {
        $relativePath = 'assets/' . $relativePath;
    }

    $cakePos = stripos($relativePath, '/cakev0/');
    if ($cakePos !== false) {
        $relativePath = substr($relativePath, $cakePos + 8);
    } else {
        $cakePos = stripos($relativePath, 'cakev0/');
        if ($cakePos !== false) {
            $relativePath = substr($relativePath, $cakePos + 7);
        }
    }

    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '') {
        return $result;
    }

    $fullPath = project_local_path($relativePath);
    if (is_file($fullPath)) {
        $result['url'] = base_url($relativePath);
    }

    return $result;
}

function project_local_path(string $relativePath): string
{
    $projectRoot = dirname(__DIR__);
    return $projectRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function storeProductImageUpload(string $tmpName, string $originalName, string $loai): ?string
{
    if ($tmpName === '' || !is_file($tmpName)) {
        return null;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allow, true)) {
        return null;
    }

    $fileName = uniqid('banh_', true) . '.' . $ext;
    $mimeType = function_exists('mime_content_type') ? mime_content_type($tmpName) : null;
    $mimeType = is_string($mimeType) ? $mimeType : null;

    if (uploadthing_enabled()) {
        $uploadedUrl = uploadthing_upload_file($tmpName, $fileName, $mimeType);
        if ($uploadedUrl !== null) {
            return $uploadedUrl;
        }
    }

    $uploadDir = project_local_path("assets/uploads/banh{$loai}");
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return null;
    }

    $targetPath = rtrim($uploadDir, '/\\') . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        return null;
    }

    return "assets/uploads/banh{$loai}/" . $fileName;
}

function safeTransliterate(string $value): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted === false || $converted === '') {
        return $value;
    }
    return $converted;
}

function slugify(string $value, ?int $id = null): string
{
    $slug = safeTransliterate($value);
    $slug = strtolower($slug ?: $value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($id !== null) {
        $suffix = '-' . $id;
        if ($slug === '') {
            $slug = 'san-pham' . $suffix;
        } elseif (!str_ends_with($slug, $suffix)) {
            $slug .= $suffix;
        }
    }
    return $slug;
}

/* --- ĐĂNG XUẤT --- */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../pages/login.php");
    exit;
}

// 2. KIỂM TRA QUYỀN TRUY CẬP
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php");
    exit;
}

// 3. XỬ LÝ LOGIC (POST REQUESTS)



// Xử lý dữ liệu khi ĐÃ ĐĂNG NHẬP
// Tạo token mới nếu chưa có sau khi login

    // Tạo token mới nếu chưa có sau khi login
    if (empty($_SESSION['csrf_token']))
        regenerateCsrfToken();

    $missingSlugRows = $conn->query("SELECT id, ten_banh FROM banh WHERE slug IS NULL OR slug = ''");
    if ($missingSlugRows) {
        $slugStmt = $conn->prepare("UPDATE banh SET slug = ? WHERE id = ?");
        while ($row = $missingSlugRows->fetch_assoc()) {
            $newSlug = slugify($row['ten_banh'], (int) $row['id']);
            $slugStmt->bind_param('si', $newSlug, $row['id']);
            $slugStmt->execute();
        }
        $slugStmt->close();
        $missingSlugRows->free();
    }

    /* ===== UPLOAD HÌNH ẢNH SẢN PHẨM ===== */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['add_product']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        // ===== VALIDATE DỮ LIỆU =====
        $ten_banh = trim($_POST['ten_banh'] ?? '');
        $loai = $_POST['loai'] ?? '';
        $gia = isset($_POST['gia']) ? (float) $_POST['gia'] : 0;
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        if ($ten_banh === '' || $loai === '' || $gia <= 0) {
            setAdminToast("Dữ liệu sản phẩm không hợp lệ", "error");
            redirectToTab('products');
        }

        /* ===== UPLOAD HÌNH ẢNH ===== */
        $hinh_anh = '';

        $uploadedImages = $_FILES['product_images'] ?? null;
        if (!$uploadedImages || empty($uploadedImages['name'][0])) {
            setAdminToast("Vui lòng chọn ít nhất 1 ảnh sản phẩm", "error");
            redirectToTab('products');
        }

        $uploadedPaths = [];
        foreach ($uploadedImages['name'] as $index => $name) {
            if ($uploadedImages['error'][$index] !== 0) {
                continue;
            }
            $storedPath = storeProductImageUpload(
                (string) ($uploadedImages['tmp_name'][$index] ?? ''),
                (string) $name,
                $loai
            );
            if ($storedPath !== null) {
                $uploadedPaths[] = $storedPath;
            }
        }

        if (empty($uploadedPaths)) {
            setAdminToast("Không thể tải ảnh lên máy chủ", "error");
            redirectToTab('products');
        }

        $hinh_anh = $uploadedPaths[0];

        /* ===== INSERT DB ===== */
        $stmt = $conn->prepare(
            "INSERT INTO banh (ten_banh, loai, gia, hinh_anh, mo_ta)
         VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssdss",
            $ten_banh,
            $loai,
            $gia,
            $hinh_anh,
            $mo_ta
        );

        $stmt->execute();

        $newId = $stmt->insert_id;
        if ($newId) {
            $newSlug = slugify($ten_banh, $newId);
            $slugStmt = $conn->prepare("UPDATE banh SET slug = ? WHERE id = ?");
            $slugStmt->bind_param('si', $newSlug, $newId);
            $slugStmt->execute();
            $slugStmt->close();
            if (count($uploadedPaths) > 1) {
                $galleryStmt = $conn->prepare(
                    "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)"
                );
                foreach (array_slice($uploadedPaths, 1) as $path) {
                    $galleryStmt->bind_param('is', $newId, $path);
                    $galleryStmt->execute();
                }
                $galleryStmt->close();
            }
        }

        setAdminToast("Thêm sản phẩm thành công!");
        redirectToTab('products');
    }

    /* --- CẬP NHẬT SẢN PHẨM --- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['update_product']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $ten_banh = trim($_POST['ten_banh'] ?? '');
        $loai = $_POST['loai'] ?? '';
        $gia = isset($_POST['gia']) ? (float) $_POST['gia'] : 0;
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        $currentImage = $_POST['current_image'] ?? '';

        if ($productId <= 0 || $ten_banh === '' || $loai === '' || $gia <= 0) {
            setAdminToast("Dữ liệu cập nhật không hợp lệ", "error");
            redirectToTab('products');
        }

        $hinh_anh = $currentImage;
        $uploadedImages = $_FILES['product_images'] ?? null;
        $uploadedPaths = [];
        if ($uploadedImages && !empty($uploadedImages['name'][0])) {
            foreach ($uploadedImages['name'] as $index => $name) {
                if ($uploadedImages['error'][$index] !== 0) {
                    continue;
                }
                $storedPath = storeProductImageUpload(
                    (string) ($uploadedImages['tmp_name'][$index] ?? ''),
                    (string) $name,
                    $loai
                );
                if ($storedPath !== null) {
                    $uploadedPaths[] = $storedPath;
                }
            }
        }

        $galleryPaths = $uploadedPaths;
        if (!empty($uploadedPaths)) {
            $hinh_anh = $uploadedPaths[0];
            $galleryPaths = array_slice($uploadedPaths, 1);
            if ($currentImage && $currentImage !== $hinh_anh && !is_remote_media_url($currentImage)) {
                $oldPath = project_local_path($currentImage);
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }
        }

        $newSlug = slugify($ten_banh, $productId);
        $stmt = $conn->prepare(
            "UPDATE banh SET ten_banh = ?, loai = ?, gia = ?, hinh_anh = ?, mo_ta = ?, slug = ? WHERE id = ?"
        );
        $stmt->bind_param('ssdsssi', $ten_banh, $loai, $gia, $hinh_anh, $mo_ta, $newSlug, $productId);
        $stmt->execute();
        $stmt->close();

        if (!empty($galleryPaths)) {
            $galleryStmt = $conn->prepare(
                "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)"
            );
            foreach ($galleryPaths as $path) {
                $galleryStmt->bind_param('is', $productId, $path);
                $galleryStmt->execute();
            }
            $galleryStmt->close();
        }

        setAdminToast("Đã cập nhật sản phẩm");
        regenerateCsrfToken();
        redirectToTab('products');
    }

    /* --- XÓA ẢNH GALLERY --- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['delete_product_image']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $imageId = (int) $_POST['delete_product_image'];
        if ($imageId > 0) {
            $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE id = ?");
            $stmt->bind_param('i', $imageId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['image_path']) && !is_remote_media_url($row['image_path'])) {
                $fullPath = project_local_path($row['image_path']);
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }

            $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
            $stmt->bind_param('i', $imageId);
            $stmt->execute();
            $stmt->close();

            setAdminToast("Đã xóa ảnh gallery");
        }
        regenerateCsrfToken();
        redirectToTab('products');
    }

    /* --- CẬP NHẬT BEST SELLING (THỦ CÔNG) --- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['update_best_selling']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $productIds = $_POST['product_ids'] ?? [];
        $manualBest = $_POST['manual_best'] ?? [];
        $bestRank = $_POST['best_rank'] ?? [];

        $stmt = $conn->prepare("UPDATE banh SET is_best_manual = ?, best_rank = ? WHERE id = ?");
        $updated = 0;

        foreach ($productIds as $id) {
            $id = (int) $id;
            $isBest = isset($manualBest[$id]) ? 1 : 0;
            $rank = isset($bestRank[$id]) ? (int) $bestRank[$id] : 0;
            if ($id > 0) {
                $stmt->bind_param('iii', $isBest, $rank, $id);
                $stmt->execute();
                $updated++;
            }
        }

        $stmt->close();
        setAdminToast("Đã cập nhật best selling cho {$updated} sản phẩm");
        regenerateCsrfToken();
        redirectToTab('best-selling');
    }

    /* --- DUYỆT ĐÁNH GIÁ --- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['update_review_status']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $reviewId = isset($_POST['review_id']) ? (int) $_POST['review_id'] : 0;
        $status = $_POST['review_status'] ?? '';
        $allowed = ['approved', 'rejected', 'pending'];

        if ($reviewId > 0 && in_array($status, $allowed, true)) {
            $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $reviewId);
            $stmt->execute();
            $stmt->close();
            setAdminToast("Đã cập nhật trạng thái đánh giá");
        } else {
            setAdminToast("Dữ liệu đánh giá không hợp lệ", "error");
        }

        regenerateCsrfToken();
        redirectToTab('testimonials');
    }


    /* --- XỬ LÝ ĐƠN HÀNG (Cập nhật trạng thái hàng loạt) --- */
    if (isset($_POST['update_order_statuses']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $commonAllowedStatuses = ['pending', 'paid', 'approved', 'delivering', 'delivered', 'completed', 'cancelled', 'failed'];
        $codOnlyStatuses = ['cod_not_deposited', 'cod_deposited'];
        $allowedStatuses = array_merge($commonAllowedStatuses, $codOnlyStatuses);
        $selected = $_POST['selected_orders'] ?? [];
        $order_statuses = $_POST['order_status'] ?? [];

        if (empty($selected)) {
            setAdminToast("Vui lòng chọn ít nhất một đơn hàng", "warning");
            regenerateCsrfToken();
            redirectToTab('orders');
        }

        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $paymentStmt = $conn->prepare("SELECT payment_method FROM orders WHERE id = ? LIMIT 1");
        $updated = 0;

        foreach ($selected as $id) {
            $id = (int) $id;
            $status = strtolower(trim((string) ($order_statuses[$id] ?? '')));
            if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
                continue;
            }

            $paymentStmt->bind_param("i", $id);
            $paymentStmt->execute();
            $orderMeta = $paymentStmt->get_result()->fetch_assoc();
            if (!$orderMeta) {
                continue;
            }

            $isCodOrder = isCodPaymentMethod((string) ($orderMeta['payment_method'] ?? ''));
            if (in_array($status, $codOnlyStatuses, true) && !$isCodOrder) {
                continue;
            }

            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
            $updated++;
        }

        $paymentStmt->close();
        $stmt->close();
        setAdminToast("Đã cập nhật trạng thái cho $updated đơn hàng");
        regenerateCsrfToken();
        redirectToTab('orders');
    }

    /* --- XỬ LÝ KHUYẾN MÃI --- */
    if (isset($_POST['add_promotion']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $stmt = $conn->prepare("INSERT INTO promotions (banh_id, gia_khuyen_mai, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $_POST['banh_id'], $_POST['gia_khuyen_mai'], $_POST['ngay_bat_dau'], $_POST['ngay_ket_thuc']);
        $stmt->execute();
        setAdminToast("Đã thêm khuyến mãi thành công!");
        regenerateCsrfToken();
        redirectToTab('promotions');
    }

    if (isset($_POST['update_promotion']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $stmt = $conn->prepare("UPDATE promotions SET gia_khuyen_mai = ?, ngay_bat_dau = ?, ngay_ket_thuc = ? WHERE id = ?");
        $stmt->bind_param("issi", $_POST['gia_khuyen_mai'], $_POST['ngay_bat_dau'], $_POST['ngay_ket_thuc'], $_POST['promotion_id']);
        $stmt->execute();
        setAdminToast("Đã cập nhật khuyến mãi thành công!");
        regenerateCsrfToken();
        redirectToTab('promotions');
    }

    if (isset($_GET['delete_promotion_id'])) {
        $id = (int) $_GET['delete_promotion_id'];
        $conn->query("DELETE FROM promotions WHERE id=$id");
        setAdminToast("Đã xóa khuyến mãi thành công!");
        redirectToTab('promotions');
    }

    /* --- XỬ LÝ LIÊN HỆ / HỖ TRỢ --- */
    if (isset($_POST['reply_contact']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id = (int) $_POST['contact_id'];
        $email = $_POST['contact_email'];
        $name = $_POST['contact_name'];
        $reply_message = trim($_POST['reply_message'] ?? '');

        if ($id > 0 && !empty($reply_message)) {
            // Update DB
            $stmt = $conn->prepare("UPDATE contact_requests SET status = 'replied', reply_message = ?, replied_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $reply_message, $id);
            $stmt->execute();
            $stmt->close();

            // Send Email
            $subject = "Phản hồi từ Gấu Bakery";
            $body = "<p>Chào <strong>{$name}</strong>,</p>
                     <p>Cảm ơn bạn đã liên hệ với Gấu Bakery. Đây là phản hồi cho thắc mắc của bạn:</p>
                     <div style='background:#f9f9f9; padding:15px; border-left:4px solid #4a1d1f; margin:15px 0;'>
                        " . nl2br($reply_message) . "
                     </div>
                     <p>Trân trọng,<br><strong>Gấu Bakery Team</strong></p>";
            
            if (send_custom_mail($email, $subject, $body)) {
                setAdminToast("Đã gửi phản hồi thành công!");
            } else {
                setAdminToast("Đã lưu phản hồi nhưng không thể gửi email (Lỗi SMTP).", "error");
            }
        }
        regenerateCsrfToken();
        redirectToTab('contacts');
    }

    if (isset($_POST['update_password_request_status']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $requestStatus = trim((string) ($_POST['request_status'] ?? ''));

        if ($requestId <= 0 || !in_array($requestStatus, ['approved', 'rejected'], true)) {
            setAdminToast("Thao tac khong hop le.", "error");
            regenerateCsrfToken();
            redirectToTab('password-requests');
        }

        if ($requestStatus === 'approved') {
            $stmt = $conn->prepare(
                "SELECT r.user_id, r.new_password, u.email, u.username
                 FROM password_reset_requests r
                 JOIN users u ON r.user_id = u.id
                 WHERE r.id = ? AND r.status = 'pending'"
            );
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($request) {
                $userId = (int) $request['user_id'];
                $newPasswordHash = (string) $request['new_password'];
                $userEmail = (string) $request['email'];
                $username = (string) $request['username'];

                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newPasswordHash, $userId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "UPDATE password_reset_requests
                     SET status = 'approved', approved_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param("i", $requestId);
                $stmt->execute();
                $stmt->close();

                $subject = "Thong bao: Mat khau Gau Bakery cua ban da duoc cap nhat";
                $body = "<h3>Chao mung quay tro lai, {$username}!</h3>
                         <p>Yeu cau dat lai mat khau cua ban da duoc quan tri vien phe duyet thanh cong.</p>
                         <p>Bay gio ban co the dang nhap vao he thong bang mat khau moi ma ban da dang ky.</p>
                         <p>Neu ban khong thuc hien yeu cau nay, vui long lien he ngay voi chung toi de duoc ho tro.</p>
                         <br>
                         <p>Tran trong,<br><strong>Gau Bakery Team</strong></p>";

                send_custom_mail($userEmail, $subject, $body);
                setAdminToast("Da duyet yeu cau doi mat khau thanh cong.");
            } else {
                setAdminToast("Yeu cau khong ton tai hoac da duoc xu ly truoc do.", "warning");
            }
        }

        if ($requestStatus === 'rejected') {
            $stmt = $conn->prepare(
                "UPDATE password_reset_requests
                 SET status = 'rejected', approved_at = NULL
                 WHERE id = ? AND status = 'pending'"
            );
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                setAdminToast("Da tu choi yeu cau doi mat khau.");
            } else {
                setAdminToast("Yeu cau khong ton tai hoac da duoc xu ly truoc do.", "warning");
            }
        }

        regenerateCsrfToken();
        redirectToTab('password-requests');
    }

    if (isset($_GET['delete_contact_id'])) {
        $id = (int) $_GET['delete_contact_id'];
        $conn->query("DELETE FROM contact_requests WHERE id=$id");
        setAdminToast("Đã xóa liên hệ!");
        redirectToTab('contacts');
    }
//} // Removed redundant check

// 3. LẤY DỮ LIỆU HIỂN THỊ & CHUẨN BỊ BIỂU ĐỒ
$products = [];
$users = [];
$orders = [];
$order_items = [];
$promotions = [];
$total_revenue = 0;
$pending_count = 0;
$js_dates = '[]';
$js_revenues = '[]';
$chart_view = $_GET['chart_view'] ?? '7days';
$chart_view = in_array($chart_view, ['7days', 'month', 'year'], true) ? $chart_view : '7days';
$selected_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$selected_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$current_year = (int) date('Y');
$selected_year = ($selected_year < 2000 || $selected_year > $current_year + 1) ? $current_year : $selected_year;
$selected_month = ($selected_month < 1 || $selected_month > 12) ? (int) date('m') : $selected_month;
$chart_title = 'Biểu đồ doanh thu 7 ngày qua';
$chart_labels = [];

if (isset($_SESSION['admin_logged_in'])) {
    if (isset($_GET['delete_user_id'])) {
        $userId = (int) $_GET['delete_user_id'];
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM login_logs WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM login_tokens WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM password_reset_requests WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "DELETE oi FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.user_id = ?"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            setAdminToast("Đã xóa khách hàng thành công!");
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            setAdminToast("Xóa khách hàng thất bại. Vui lòng thử lại.", "error");
        }

        redirectToTab('users');
    }

    if (isset($_GET['delete_order_id'])) {
        $orderId = (int) $_GET['delete_order_id'];
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            setAdminToast("Đã xóa đơn hàng thành công!");
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            setAdminToast("Xóa đơn hàng thất bại: " . $e->getMessage(), "error");
        }

        redirectToTab('orders');
    }

    if (isset($_GET['delete_product_id'])) {
        $id = (int) $_GET['delete_product_id'];
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM promotions WHERE banh_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM order_items WHERE banh_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM product_reviews WHERE product_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM banh WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            setAdminToast("Đã xóa sản phẩm thành công!");
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            setAdminToast("Xóa sản phẩm thất bại. Vui lòng thử lại.", "error");
        }

        redirectToTab('products');
    }
}


// Lấy dữ liệu từ DB
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query(
    "SELECT u.*, COALESCE(SUM(CASE WHEN o.status IN ('paid','approved','delivered','completed') THEN o.total_amount ELSE 0 END), 0) AS total_spent
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

// Lấy đơn hàng kèm thông tin user (nếu có)
// Lưu ý: Nếu user_id null hoặc đã xóa user, vẫn nên hiện đơn hàng -> dùng LEFT JOIN
$orders = $conn->query("SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$ordersByUser = [];
$ordersById = [];
foreach ($orders as $order) {
    if (empty($order['user_id'])) {
        continue;
    }
    $uid = (int) $order['user_id'];
    $orderId = (int) $order['id'];
    $ordersById[$orderId] = [
        'id' => $orderId,
        'recipient_name' => (string) $order['recipient_name'],
        'phone' => (string) $order['phone'],
        'address' => (string) $order['address'],
        'note' => (string) ($order['note'] ?? ''),
        'payment_method' => (string) $order['payment_method'],
        'total_amount' => (float) $order['total_amount'],
        'status' => (string) $order['status'],
        'created_at' => (string) $order['created_at']
    ];
    $ordersByUser[$uid][] = [
        'id' => $orderId,
        'total_amount' => (float) $order['total_amount'],
        'status' => (string) $order['status'],
        'created_at' => (string) $order['created_at']
    ];
}

$order_items = $conn->query("SELECT oi.*, b.ten_banh FROM order_items oi LEFT JOIN banh b ON oi.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);
$orderItemsById = [];
foreach ($order_items as $item) {
    $oid = (int) $item['order_id'];
    $orderItemsById[$oid][] = [
        'ten_banh' => (string) $item['ten_banh'],
        'quantity' => (int) $item['quantity'],
        'price' => (float) $item['price']
    ];
}
$promotions = $conn->query("SELECT p.*, b.ten_banh, b.gia AS gia_hien_tai FROM promotions p JOIN banh b ON p.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);

// Kiểm tra query contact_requests (phòng trường hợp người dùng chưa tạo bảng trên cloud)
$contactRequestsQuery = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC");
$contactRequests = $contactRequestsQuery ? $contactRequestsQuery->fetch_all(MYSQLI_ASSOC) : [];

$passwordRequestLabels = [
    'pending' => ['label' => 'Chờ duyệt', 'class' => 'warning text-dark'],
    'approved' => ['label' => 'Đã duyệt', 'class' => 'success'],
    'rejected' => ['label' => 'Đã từ chối', 'class' => 'danger'],
];
$passwordRequestsQuery = $conn->query(
    "SELECT r.id, r.user_id, r.status, r.created_at, r.approved_at, u.username, u.email
     FROM password_reset_requests r
     JOIN users u ON r.user_id = u.id
     ORDER BY r.created_at DESC"
);
$passwordRequests = $passwordRequestsQuery ? $passwordRequestsQuery->fetch_all(MYSQLI_ASSOC) : [];

$reviews = $conn->query("SELECT * FROM reviews ORDER BY timestamp DESC")->fetch_all(MYSQLI_ASSOC);
$productImages = $conn->query("SELECT * FROM product_images ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$productImageMap = [];
foreach ($productImages as $imgRow) {
    $productImageMap[(int) $imgRow['product_id']][] = $imgRow;
}

$bestSalesRows = $conn->query(
    "SELECT oi.banh_id, SUM(oi.quantity) AS sold_qty
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status IN ('paid','approved','delivered','completed')
     GROUP BY oi.banh_id"
)->fetch_all(MYSQLI_ASSOC);

$bestSalesMap = [];
foreach ($bestSalesRows as $row) {
    $bestSalesMap[(int) $row['banh_id']] = (int) $row['sold_qty'];
}

// --- LOGIC THỐNG KÊ & BIỂU ĐỒ ---
$chart_data = [];
if ($chart_view === 'month') {
    $days_in_month = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $selected_year, $selected_month)))
        ->format('t');
    for ($day = 1; $day <= $days_in_month; $day++) {
        $chart_data[$day] = 0;
    }
    $chart_title = "Biểu đồ doanh thu tháng {$selected_month}/{$selected_year}";
} elseif ($chart_view === 'year') {
    for ($month = 1; $month <= 12; $month++) {
        $chart_data[$month] = 0;
    }
    $chart_title = "Biểu đồ doanh thu năm {$selected_year}";
} else {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data[$date] = 0;
    }
}

foreach ($orders as $o) {
    $status = strtolower($o['status']);
    $is_revenue = in_array($status, ['paid', 'approved', 'delivered', 'completed'], true);

    if ($is_revenue) {
        $total_revenue += $o['total_amount'];

        $order_date = strtotime($o['created_at']);
        $order_year = (int) date('Y', $order_date);
        $order_month = (int) date('n', $order_date);
        $order_day = (int) date('j', $order_date);

        if ($chart_view === 'month') {
            if ($order_year === $selected_year && $order_month === $selected_month) {
                $chart_data[$order_day] += $o['total_amount'];
            }
        } elseif ($chart_view === 'year') {
            if ($order_year === $selected_year) {
                $chart_data[$order_month] += $o['total_amount'];
            }
        } else {
            $order_key = date('Y-m-d', $order_date);
            if (isset($chart_data[$order_key])) {
                $chart_data[$order_key] += $o['total_amount'];
            }
        }
    }
    if (in_array($status, ['pending', 'cod_not_deposited'], true)) {
        $pending_count++;
    }
}

// Chuyển dữ liệu sang JSON để JS sử dụng
$chart_values = array_values($chart_data);
if ($chart_view === 'month') {
    foreach (array_keys($chart_data) as $day) {
        $chart_labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }
} elseif ($chart_view === 'year') {
    foreach (array_keys($chart_data) as $month) {
        $chart_labels[] = 'T' . $month;
    }
} else {
    foreach (array_keys($chart_data) as $date) {
        $chart_labels[] = date('d/m', strtotime($date));
    }
}

$js_dates = json_encode($chart_labels);
$js_revenues = json_encode($chart_values);

$export_query_xlsx = http_build_query([
    'tab' => 'dashboard',
    'chart_view' => $chart_view,
    'month' => $selected_month,
    'year' => $selected_year,
    'export_format' => 'xlsx',
    'export_revenue' => 1
]);

if (isset($_GET['export_revenue']) && isset($_SESSION['admin_logged_in'])) {
    $filename = "revenue_report_{$chart_view}_{$selected_year}";
    if ($chart_view === 'month') {
        $filename .= '_' . str_pad((string) $selected_month, 2, '0', STR_PAD_LEFT);
    }
    $filename .= '.xlsx';

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        echo 'Chua cai dat thu vien xuat Excel. Vui long cai PhpSpreadsheet.';
        exit;
    }
    require_once $autoload;
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Doanh thu');
    $sheet->fromArray(['Ngày/Tháng', 'Doanh thu (VND)'], null, 'A1');
    $row = 2;
    foreach ($chart_labels as $index => $label) {
        $value = $chart_values[$index] ?? 0;
        if ($chart_view === 'month') {
            $dayNumber = (int) $label;
            $sheet->setCellValue("A{$row}", $dayNumber);
            $sheet->getStyle("A{$row}")->getNumberFormat()->setFormatCode('00');
        } else {
            $sheet->setCellValue("A{$row}", $label);
        }
        $sheet->setCellValue("B{$row}", $value);
        $row++;
    }
    $sheet->setCellValue("A{$row}", 'Tong');
    $sheet->setCellValue("B{$row}", array_sum($chart_values));

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename=' . $filename);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gấu Bakery</title>

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Icons & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        :root {
            --brown-900: #3c1819;
            --brown-800: #4a1d1f;
            --brown-700: #6a2d22;
            --caramel: #f3e0be;
            --cream: #fff7ea;
            --ink: #272727;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #fffaf2;
            margin: 0;
            color: var(--ink);
        }



        /* --- DASHBOARD STYLES --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #ffffff, #fff7ea);
            height: 100vh;
            position: fixed;
            padding: 20px;
            border-right: 1px solid var(--caramel);
            box-shadow: 4px 0 20px rgba(74, 29, 31, 0.06);
            z-index: 1000;
        }

        .sidebar h2 {
            color: var(--brown-800);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .nav-link {
            color: var(--brown-700);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #ffffff;
            border-color: var(--caramel);
            box-shadow: 0 6px 14px rgba(74, 29, 31, 0.08);
            transform: translateX(4px);
            color: var(--brown-800);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: 0.3s;
        }

        /* Stat Cards */
        .stat-card {
            position: relative;
            background: #ffffff;
            border-radius: 18px;
            padding: 20px 22px;
            border: 1px solid var(--caramel);
            box-shadow: 0 12px 26px rgba(74, 29, 31, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            transition: transform 0.2s;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(243, 224, 190, 0.55), transparent 55%);
            opacity: 0.7;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .confirm-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(44, 24, 20, 0.65);
            backdrop-filter: blur(6px);
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

        .user-orders-modal-box {
            text-align: left;
            max-width: 1200px;
            max-height: 85vh;
            overflow: hidden;
        }

        .user-orders-layout {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 18px;
            max-height: 58vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }

        .user-orders-detail {
            background: #fff7ea;
            border: 1px solid #f3e0be;
            border-radius: 16px;
            padding: 14px;
            min-height: 220px;
        }

        .user-orders-detail h6 {
            margin: 0 0 10px;
            color: #4a1d1f;
            font-weight: 700;
        }

        .user-orders-meta {
            display: grid;
            gap: 6px;
            font-size: 13px;
            color: #6a2d22;
        }

        .user-orders-items {
            margin-top: 10px;
            border-top: 1px dashed #e8d9c6;
            padding-top: 10px;
        }

        .user-orders-items div {
            font-size: 13px;
            color: #4a4a4a;
            display: flex;
            justify-content: space-between;
        }

        .user-orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .user-orders-table th,
        .user-orders-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0e6d7;
            font-size: 14px;
        }

        .user-orders-empty {
            margin: 12px 0 0;
            color: #6b6b6b;
        }

        .stat-info h5 {
            margin: 0;
            font-size: 0.9rem;
            color: #7c6b67;
        }

        .stat-info h3 {
            margin: 5px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brown-800);
        }

        .stat-icon {
            font-size: 1.7rem;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(74, 29, 31, 0.12);
        }

        .stat-icon.revenue {
            background: #fff1d6;
            color: #7a3b1d;
        }

        .stat-icon.pending {
            background: #ffe7b8;
            color: #b36b00;
        }

        .stat-icon.products {
            background: #f4e1c9;
            color: #7a4b2a;
        }

        .stat-icon.customers {
            background: #f9ead5;
            color: #6a2d22;
        }

        /* Tables & Tabs */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-table {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 12px 26px rgba(74, 29, 31, 0.08);
            overflow-x: auto;
            border: 1px solid var(--caramel);
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            background: #fff1d6;
            color: var(--brown-800);
            padding: 15px;
            text-align: left;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-green {
            background: var(--brown-800);
            color: #fbedcd;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            transition: .2s;
        }

        .btn-green:hover {
            background: #2f1415;
            color: #fbedcd;
        }

        .btn-action {
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            margin-right: 5px;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .is-hidden {
            display: none !important;
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



        <!-- 1. SIDEBAR -->
        <div class="sidebar">
            <h2>
                <img src="/cakev0/assets/img/logo.png" alt="Gấu Bakery"
                    style="width:28px;height:28px;object-fit:contain;margin-right:8px;">
                Gấu Bakery
            </h2>
            <nav class="nav flex-column">
                <a class="nav-link active" href="admin.php?tab=dashboard#dashboard" data-tab="dashboard"
                    onclick="showTab(event, 'dashboard')"><i class="bi bi-speedometer2"></i>
                    Dashboard</a>
                <a class="nav-link" href="admin.php?tab=orders#orders" data-tab="orders"
                    onclick="showTab(event, 'orders')"><i class="bi bi-cart-check"></i> Đơn hàng</a>
                <a class="nav-link" href="admin.php?tab=products#products" data-tab="products"
                    onclick="showTab(event, 'products')"><i class="bi bi-box-seam"></i> Sản phẩm</a>
                <a class="nav-link" href="admin.php?tab=best-selling#best-selling" data-tab="best-selling"
                    onclick="showTab(event, 'best-selling')"><i class="bi bi-star"></i> Best Selling</a>
                <a class="nav-link" href="admin.php?tab=testimonials#testimonials" data-tab="testimonials"
                    onclick="showTab(event, 'testimonials')"><i class="bi bi-chat-quote"></i> Đánh giá</a>
                <a class="nav-link" href="admin.php?tab=password-requests#password-requests" data-tab="password-requests"
                    onclick="showTab(event, 'password-requests')"><i class="bi bi-shield-lock"></i> Duyệt đổi mật khẩu</a>
                <a class="nav-link" href="admin.php?tab=users#users" data-tab="users" onclick="showTab(event, 'users')"><i
                        class="bi bi-people"></i> Khách hàng</a>
                <a class="nav-link" href="admin.php?tab=promotions#promotions" data-tab="promotions"
                    onclick="showTab(event, 'promotions')"><i class="bi bi-tags"></i> Khuyến mãi</a>
                <a class="nav-link" href="admin.php?tab=contacts#contacts" data-tab="contacts"
                    onclick="showTab(event, 'contacts')"><i class="bi bi-envelope"></i> Liên hệ</a>
            </nav>
        </div>

        <!-- 2. MAIN CONTENT -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm"
                style="border:1px solid #f3e0be;">
                <h3 class="m-0 fw-bold" style="color:#4a1d1f;">Quản Trị Hệ Thống</h3>
                <a href="?logout=1" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Đăng
                    xuất</a>
            </div>

            <!-- TAB 1: DASHBOARD -->
            <div id="dashboard" class="tab-content active">
                <!-- Thẻ thống kê -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng doanh thu</h5>
                                <h3><?= number_format($total_revenue, 0, ',', '.') ?>đ</h3>
                            </div>
                            <div class="stat-icon revenue"><i class="bi bi-graph-up-arrow"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Đơn chờ xử lý</h5>
                                <h3 class="text-warning"><?= $pending_count ?></h3>
                            </div>
                            <div class="stat-icon pending"><i class="bi bi-receipt"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng sản phẩm</h5>
                                <h3><?= count($products) ?></h3>
                            </div>
                            <div class="stat-icon products"><i class="bi bi-box2-heart"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Khách hàng</h5>
                                <h3><?= count($users) ?></h3>
                            </div>
                            <div class="stat-icon customers"><i class="bi bi-people-fill"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ doanh thu -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-3" style="border:1px solid #f3e0be;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <h5 class="m-0" style="color:#4a1d1f;"><i class="bi bi-graph-up-arrow"></i>
                                    <?= htmlspecialchars($chart_title) ?></h5>
                                <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="hidden" name="tab" value="dashboard">
                                    <select name="chart_view" class="form-select form-select-sm" id="chartViewSelect"
                                        style="min-width: 160px;">
                                        <option value="7days" <?= $chart_view === '7days' ? 'selected' : '' ?>>7 ngày gần nhất
                                        </option>
                                        <option value="month" <?= $chart_view === 'month' ? 'selected' : '' ?>>Theo tháng
                                        </option>
                                        <option value="year" <?= $chart_view === 'year' ? 'selected' : '' ?>>Theo năm</option>
                                    </select>
                                    <select name="month" class="form-select form-select-sm" id="chartMonth"
                                        style="min-width: 110px;">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m ?>" <?= $m === $selected_month ? 'selected' : '' ?>>Tháng
                                                <?= $m ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="year" class="form-select form-select-sm" id="chartYear"
                                        style="min-width: 110px;">
                                        <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                            <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Xem</button>
                                    <a href="admin.php?<?= $export_query_xlsx ?>"
                                        class="btn btn-sm btn-outline-success">Xuất Excel</a>
                                </form>
                            </div>
                            <div style="height: 350px;">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bảng đơn hàng mới nhất -->
                <div class="custom-table">
                    <h5 class="mb-3">Đơn hàng mới nhất</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orders, 0, 5) as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td><?= htmlspecialchars($o['username'] ?? 'Khách lẻ') ?></td>
                                    <td><?= number_format($o['total_amount']) ?>đ</td>
                                    <td>
                                        <?php
                                        $statusData = match (strtolower($o['status'])) {
                                            'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                            'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ xác nhận'],
                                            'cod_not_deposited' => ['badge' => 'warning text-dark', 'label' => 'Ch&#432;a &#273;&#7863;t c&#7885;c'],
                                            'cod_deposited' => ['badge' => 'primary', 'label' => '&#272;&#227; &#273;&#7863;t c&#7885;c'],
                                            'paid' => ['badge' => 'primary', 'label' => 'Đã thanh toán'],
                                            'approved', 'confirmed' => ['badge' => 'info', 'label' => 'Đã xác nhận'],
                                            'delivering' => ['badge' => 'info', 'label' => 'Đang giao'],
                                            'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                            'failed' => ['badge' => 'danger', 'label' => 'Thanh toán lỗi'],
                                            'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                            default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: ORDERS -->
            <div id="orders" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Quản Lý Đơn Hàng</h3>
                <div class="custom-table">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 44px;"><input type="checkbox" id="selectAllOrders"></th>
                                    <th>ID</th>
                                    <th>Khách hàng</th>
                                    <th>Chi tiết SP</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Cập nhật</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_orders[]" value="<?= $o['id'] ?>"
                                                class="order-select">
                                        </td>
                                        <td>#<?= $o['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($o['username'] ?? 'N/A') ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($o['email'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php foreach ($order_items as $i):
                                                if ($i['order_id'] == $o['id']): ?>
                                                    <div class="small">- <?= htmlspecialchars($i['ten_banh']) ?>
                                                        (x<?= $i['quantity'] ?>)
                                                    </div>
                                                <?php endif; endforeach; ?>
                                        </td>
                                        <td class="fw-bold"><?= number_format($o['total_amount']) ?>đ</td>
                                        <td>
                                            <?php
                                            $statusData = match (strtolower($o['status'])) {
                                                'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                                'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ xác nhận'],
                                                'cod_not_deposited' => ['badge' => 'warning text-dark', 'label' => 'Ch&#432;a &#273;&#7863;t c&#7885;c'],
                                                'cod_deposited' => ['badge' => 'primary', 'label' => '&#272;&#227; &#273;&#7863;t c&#7885;c'],
                                                'paid' => ['badge' => 'primary', 'label' => 'Đã thanh toán'],
                                                'approved', 'confirmed' => ['badge' => 'info', 'label' => 'Đã xác nhận'],
                                                'delivering' => ['badge' => 'info', 'label' => 'Đang giao'],
                                                'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                                'failed' => ['badge' => 'danger', 'label' => 'Thanh toán lỗi'],
                                                'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                                default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                            };
                                            ?>
                                            <span
                                                class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>
                                        </td>
                                        <td>
                                            <select name="order_status[<?= $o['id'] ?>]" class="form-select form-select-sm"
                                                style="min-width: 160px;">
                                                <?php
                                                $currentStatus = strtolower((string) $o['status']);
                                                $isCodOrder = isCodPaymentMethod((string) ($o['payment_method'] ?? ''));
                                                $statusOptions = [
                                                    'pending' => 'Đang chờ',
                                                    'paid' => 'Đã thanh toán',
                                                    'approved' => 'Đã xác nhận',
                                                    'delivering' => 'Đang giao',
                                                    'delivered' => 'Đã giao',
                                                    'completed' => 'Hoàn tất',
                                                    'cancelled' => 'Đã hủy',
                                                    'failed' => 'Thanh toán lỗi'
                                                ];
                                                if ($isCodOrder) {
                                                    $statusOptions = [
                                                        'cod_not_deposited' => 'Ch&#432;a &#273;&#7863;t c&#7885;c',
                                                        'cod_deposited' => '&#272;&#227; &#273;&#7863;t c&#7885;c'
                                                    ] + $statusOptions;
                                                }
                                                if (!isset($statusOptions[$currentStatus])) {
                                                    $statusOptions = [$currentStatus => ucfirst((string) $o['status'])] + $statusOptions;
                                                }
                                                foreach ($statusOptions as $value => $label):
                                                    $selected = ($currentStatus === $value) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary order-detail-btn"
                                                data-order-id="<?= $o['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger order-delete-btn"
                                                data-delete-url="?delete_order_id=<?= $o['id'] ?>"
                                                data-order-id="<?= $o['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-end mt-3">
                            <button name="update_order_statuses" class="btn btn-green">
                                <i class="bi bi-check2-circle"></i> Cập nhật trạng thái đã chọn
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: PRODUCTS -->
            <div id="products" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Danh Sách Sản Phẩm</h3>
                <div class="card p-4 mb-4 border-0 shadow-sm">
                    <form id="productForm" method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="product_id" id="productId">
                        <input type="hidden" name="current_image" id="currentImage">
                        <div class="col-md-3">
                            <label class="form-label">Tên bánh</label>
                            <input type="text" name="ten_banh" id="productName" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Loại</label>
                            <select name="loai" id="productType" class="form-select">
                                <option value="ngot">Bánh ngọt</option>
                                <option value="man">Bánh mặn</option>
                                <option value="kem">Bánh kem</option>
                                <option value="mi">Bánh mì</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="gia" id="productPrice" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mô tả</label>
                            <textarea name="mo_ta" id="productDesc" class="form-control editor" rows="6"
                                placeholder="Mô tả ngắn về sản phẩm"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hình ảnh (ảnh đầu là ảnh chính)</label>
                            <input type="file" name="product_images[]" class="form-control" multiple>
                            <small class="text-muted">Bỏ trống nếu không đổi ảnh khi cập nhật.</small>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button id="addProductBtn" name="add_product" class="btn btn-green">
                                <i class="bi bi-plus-circle"></i> Thêm sản phẩm
                            </button>
                            <button id="updateProductBtn" name="update_product" class="btn btn-outline-primary is-hidden">
                                <i class="bi bi-save"></i> Lưu cập nhật
                            </button>
                            <button id="cancelEditBtn" type="button" class="btn btn-outline-secondary is-hidden">
                                <i class="bi bi-x-circle"></i> Hủy sửa
                            </button>
                        </div>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="flex-grow-1" style="max-width:360px;">
                        <input type="text" id="productSearchInput" class="form-control"
                            placeholder="Tìm theo tên, loại, giá...">
                    </div>
                </div>

                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên</th>
                                <th>Loại</th>
                                <th>Giá</th>
                                <th>Mô tả</th>
                                <th>Gallery</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p):
                                $img = buildImageUrl($p['hinh_anh']); ?>
                                <tr>
                                    <td><img src="<?= $img['url'] ?>" width="50" height="50" style="object-fit:cover"
                                            class="rounded"></td>
                                    <td><?= htmlspecialchars($p['ten_banh']) ?></td>
                                    <td><?= htmlspecialchars($p['loai']) ?></td>
                                    <td><?= number_format((int) $p['gia']) ?>đ</td>
                                    <td><?= !empty($p['mo_ta']) ? 'Có mô tả' : 'Chưa có' ?></td>
                                    <td>
                                        <?php if (!empty($productImageMap[(int) $p['id']])): ?>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <?php foreach ($productImageMap[(int) $p['id']] as $gallery):
                                                    $galleryUrl = buildImageUrl($gallery['image_path']);
                                                    ?>
                                                    <div class="position-relative" style="width:40px;height:40px;">
                                                        <img src="<?= $galleryUrl['url'] ?>" width="40" height="40"
                                                            style="object-fit:cover;border-radius:8px;">
                                                        <form method="POST" class="position-absolute" style="top:-6px;right:-6px;">
                                                            <input type="hidden" name="csrf_token"
                                                                value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="delete_product_image"
                                                                value="<?= (int) $gallery['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger"
                                                                style="padding:0 4px;line-height:1;">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary product-edit-btn"
                                            data-id="<?= (int) $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['ten_banh'], ENT_QUOTES) ?>"
                                            data-type="<?= htmlspecialchars($p['loai'], ENT_QUOTES) ?>"
                                            data-price="<?= (int) $p['gia'] ?>"
                                            data-desc="<?= htmlspecialchars($p['mo_ta'] ?? '', ENT_QUOTES) ?>"
                                            data-image="<?= htmlspecialchars($p['hinh_anh'], ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger product-delete-btn"
                                            data-delete-url="?delete_product_id=<?= $p['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($p['ten_banh'], ENT_QUOTES) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>


                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: USERS -->
            <div id="users" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Khách Hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Tổng chi tiêu</th>
                                <th>Ngày đăng ký</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= number_format((float) $u['total_spent']) ?>đ</td>
                                    <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary user-orders-btn"
                                            data-user-id="<?= $u['id'] ?>"
                                            data-user-name="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>">
                                            <i class="bi bi-receipt"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger user-delete-btn"
                                            data-delete-url="?delete_user_id=<?= $u['id'] ?>"
                                            data-user-name="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4B: BEST SELLING -->
            <div id="best-selling" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Best Selling</h3>

                <div class="custom-table mb-4">
                    <h5 class="mb-3">Chọn thủ công Best Selling</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ảnh</th>
                                    <th>Tên</th>
                                    <th>Đã bán</th>
                                    <th>Thủ công</th>
                                    <th>Thứ tự</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p):
                                    $img = buildImageUrl($p['hinh_anh']);
                                    $soldQty = $bestSalesMap[(int) $p['id']] ?? 0;
                                    ?>
                                    <tr>
                                        <td><img src="<?= $img['url'] ?>" width="46" height="46" style="object-fit:cover"
                                                class="rounded"></td>
                                        <td><?= htmlspecialchars($p['ten_banh']) ?></td>
                                        <td><?= $soldQty ?></td>
                                        <td>
                                            <input type="hidden" name="product_ids[]" value="<?= $p['id'] ?>">
                                            <input class="form-check-input" type="checkbox" name="manual_best[<?= $p['id'] ?>]"
                                                <?= !empty($p['is_best_manual']) ? 'checked' : '' ?>>
                                        </td>
                                        <td style="width: 120px;">
                                            <input type="number" min="0" name="best_rank[<?= $p['id'] ?>]"
                                                class="form-control form-control-sm"
                                                value="<?= (int) ($p['best_rank'] ?? 0) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-end mt-3">
                            <button name="update_best_selling" class="btn btn-green">
                                <i class="bi bi-check2-circle"></i> Cập nhật Best Selling
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- TAB 4C: TESTIMONIALS -->
            <div id="testimonials" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Duyệt đánh giá khách hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên</th>
                                <th>Nội dung</th>
                                <th>Sao</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <?php
                                $reviewStatusRaw = (string) ($r['status'] ?? 'pending');
                                $reviewStatusData = match ($reviewStatusRaw) {
                                    'pending' => ['label' => 'Chờ duyệt', 'badge' => 'warning text-dark'],
                                    'approved' => ['label' => 'Đã duyệt', 'badge' => 'success'],
                                    'rejected' => ['label' => 'Đã từ chối', 'badge' => 'danger'],
                                    default => ['label' => ucfirst($reviewStatusRaw), 'badge' => 'secondary']
                                };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td><?= htmlspecialchars($r['text']) ?></td>
                                    <td><?= htmlspecialchars($r['stars']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $reviewStatusData['badge'] ?>">
                                            <?= htmlspecialchars($reviewStatusData['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reviewStatusRaw === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="review_status" value="approved">
                                                <button name="update_review_status" class="btn btn-sm btn-success" title="Duyệt">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="review_status" value="rejected">
                                                <button name="update_review_status" class="btn btn-sm btn-outline-danger"
                                                    title="Từ chối">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Đã xử lý</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- TAB: PASSWORD REQUESTS -->
            <div id="password-requests" class="tab-content"> 
                <h3 class="mb-4" style="color:#4a1d1f;">Duyet yeu cau doi mat khau</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Người dùng</th>
                                <th>Email</th>
                                <th>Trạng thái</th>
                                <th>Yêu cầu lúc</th>
                                <th>Phê duyệt lúc</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passwordRequests as $request): ?>
                                <tr>
                                    <td>#<?= (int) $request['id'] ?></td>
                                    <td><?= htmlspecialchars($request['username']) ?></td>
                                    <td><?= htmlspecialchars((string) ($request['email'] ?? '')) ?></td>
                                    <td>
                                        <?php
                                        $passwordRequestStatus = (string) ($request['status'] ?? 'pending');
                                        $passwordRequestMeta = $passwordRequestLabels[$passwordRequestStatus] ?? ['label' => ucfirst($passwordRequestStatus), 'class' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= htmlspecialchars($passwordRequestMeta['class']) ?>"><?= htmlspecialchars($passwordRequestMeta['label']) ?></span>
                                    </td>
                                    <td><small><?= htmlspecialchars((string) ($request['created_at'] ?? '')) ?></small></td>
                                    <td>
                                        <small>
                                            <?= !empty($request['approved_at']) ? htmlspecialchars((string) $request['approved_at']) : '<span class="text-muted">Chưa xử lý</span>' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (($request['status'] ?? '') === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                                <input type="hidden" name="request_status" value="approved">
                                                <button type="submit" name="update_password_request_status" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                                <input type="hidden" name="request_status" value="rejected">
                                                <button type="submit" name="update_password_request_status" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Da xu ly</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($passwordRequests)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Chưa có yêu cầu đổi mật khẩu nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: PROMOTIONS -->
            <div id="promotions" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Chương Trình Khuyến Mãi</h3>
                <form method="POST" class="card p-3 border-0 shadow-sm mb-3 row g-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-md-3">
                        <select name="banh_id" id="promotionProductSelect" class="form-select">
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= (float) $p['gia'] ?>">
                                    <?= htmlspecialchars($p['ten_banh']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="promotionCurrentPrice" class="form-control"
                            value="<?= !empty($products) ? number_format((float) $products[0]['gia'], 0, ',', '.') . 'đ' : '0đ' ?>"
                            readonly>
                    </div>
                    <div class="col-md-2"><input type="number" name="gia_khuyen_mai" class="form-control"
                            placeholder="Giá KM" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_bat_dau" class="form-control" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_ket_thuc" class="form-control" required></div>
                    <div class="col-md-1"><button name="add_promotion" class="btn btn-green w-100"><i
                                class="bi bi-plus-lg"></i></button></div>
                </form>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Giá hiện tại</th>
                                <th>Giá KM</th>
                                <th>Thời gian</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($promo['ten_banh']) ?></td>
                                    <td><?= number_format((float) ($promo['gia_hien_tai'] ?? 0), 0, ',', '.') ?>đ</td>
                                    <td><?= number_format($promo['gia_khuyen_mai']) ?>đ</td>
                                    <td><?= date('d/m', strtotime($promo['ngay_bat_dau'])) ?> ->
                                        <?= date('d/m', strtotime($promo['ngay_ket_thuc'])) ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary promo-edit-btn"
                                            data-id="<?= $promo['id'] ?>"
                                            data-price="<?= $promo['gia_khuyen_mai'] ?>"
                                            data-start="<?= date('Y-m-d', strtotime($promo['ngay_bat_dau'])) ?>"
                                            data-end="<?= date('Y-m-d', strtotime($promo['ngay_ket_thuc'])) ?>"
                                            data-name="<?= htmlspecialchars($promo['ten_banh']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger promo-delete-btn"
                                            data-delete-url="?delete_promotion_id=<?= $promo['id'] ?>"
                                            data-promo-name="<?= htmlspecialchars($promo['ten_banh']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 6: CONTACTS -->
            <div id="contacts" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Yêu cầu hỗ trợ khách hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên</th>
                                <th>Email/SĐT</th>
                                <th>Nội dung</th>
                                <th>Ngày gửi</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contactRequests as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                    <td>
                                        <small><?= htmlspecialchars($c['email']) ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($c['phone']) ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($c['message']) ?>">
                                            <?= htmlspecialchars($c['message']) ?>
                                        </div>
                                    </td>
                                    <td><small><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small></td>
                                    <td>
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Chờ xử lý</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Đã phản hồi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary contact-detail-btn" 
                                            data-id="<?= $c['id'] ?>"
                                            data-name="<?= htmlspecialchars($c['name']) ?>"
                                            data-email="<?= htmlspecialchars($c['email']) ?>"
                                            data-phone="<?= htmlspecialchars($c['phone']) ?>"
                                            data-msg="<?= htmlspecialchars($c['message']) ?>"
                                            data-status="<?= $c['status'] ?>"
                                            data-reply="<?= htmlspecialchars($c['reply_message'] ?? '') ?>"
                                            data-date="<?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($c['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success contact-reply-btn"
                                            data-id="<?= $c['id'] ?>"
                                            data-name="<?= htmlspecialchars($c['name']) ?>"
                                            data-email="<?= htmlspecialchars($c['email']) ?>">
                                            <i class="bi bi-reply"></i>
                                        </button>
                                        <?php endif; ?>
                                        <a href="?delete_contact_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa liên hệ này?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($contactRequests)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có yêu cầu hỗ trợ nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="deleteProductModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="deleteProductTitle">
                <div class="confirm-modal-box">
                    <div class="confirm-modal-title" id="deleteProductTitle">Xóa sản phẩm?</div>
                    <p class="confirm-modal-desc" id="deleteProductDesc">Sản phẩm sẽ bị xóa vĩnh viễn và không thể khôi
                        phục.</p>
                    <div class="confirm-modal-actions">
                        <button type="button" class="btn btn-outline-secondary" id="deleteProductCancel">Hủy</button>
                        <button type="button" class="btn btn-danger" id="deleteProductConfirm">Xác nhận xóa</button>
                    </div>
                </div>
            </div>

            <div id="deleteUserModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="deleteUserTitle">
                <div class="confirm-modal-box">
                    <div class="confirm-modal-title" id="deleteUserTitle">Xóa khách hàng?</div>
                    <p class="confirm-modal-desc" id="deleteUserDesc">Khách hàng sẽ bị xóa vĩnh viễn và không thể khôi phục.
                    </p>
                    <div class="confirm-modal-actions">
                        <button type="button" class="btn btn-outline-secondary" id="deleteUserCancel">Hủy</button>
                        <button type="button" class="btn btn-danger" id="deleteUserConfirm">Xác nhận xóa</button>
                    </div>
                </div>
            </div>

            <div id="deleteOrderModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="deleteOrderTitle">
                <div class="confirm-modal-box">
                    <div class="confirm-modal-title" id="deleteOrderTitle">Xóa đơn hàng?</div>
                    <p class="confirm-modal-desc" id="deleteOrderDesc">Đơn hàng sẽ bị xóa vĩnh viễn và không thể khôi phục.
                    </p>
                    <div class="confirm-modal-actions">
                        <button type="button" class="btn btn-outline-secondary" id="deleteOrderCancel">Hủy</button>
                        <button type="button" class="btn btn-danger" id="deleteOrderConfirm">Xác nhận xóa</button>
                    </div>
                </div>
            </div>

            <div id="deletePromotionModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="deletePromotionTitle">
                <div class="confirm-modal-box">
                    <div class="confirm-modal-title" id="deletePromotionTitle">Xóa khuyến mãi?</div>
                    <p class="confirm-modal-desc" id="deletePromotionDesc">Khuyến mãi sẽ bị xóa vĩnh viễn và không thể khôi
                        phục.</p>
                    <div class="confirm-modal-actions">
                        <button type="button" class="btn btn-outline-secondary" id="deletePromotionCancel">Hủy</button>
                        <button type="button" class="btn btn-danger" id="deletePromotionConfirm">Xác nhận xóa</button>
                    </div>
                </div>
            </div>

            <div id="editPromotionModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="editPromotionTitle">
                <div class="confirm-modal-box" style="text-align: left; max-width: 500px;">
                    <div class="confirm-modal-title" id="editPromotionTitle">Chỉnh sửa khuyến mãi</div>
                    <form method="POST" class="mt-3 row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="promotion_id" id="editPromoId">
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">Sản phẩm</label>
                            <input type="text" id="editPromoName" class="form-control" readonly disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Giá khuyến mãi (VNĐ)</label>
                            <input type="number" name="gia_khuyen_mai" id="editPromoPrice" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ngày bắt đầu</label>
                            <input type="date" name="ngay_bat_dau" id="editPromoStart" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ngày kết thúc</label>
                            <input type="date" name="ngay_ket_thuc" id="editPromoEnd" class="form-control" required>
                        </div>
                        <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="editPromotionCancel">Hủy</button>
                            <button type="submit" name="update_promotion" class="btn btn-green">Lưu thay đổi</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="adminOrderModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="adminOrderTitle">
                <div class="confirm-modal-box user-orders-modal-box">
                    <div class="confirm-modal-title" id="adminOrderTitle">Chi tiết đơn hàng</div>
                    <p class="confirm-modal-desc" id="adminOrderDesc"></p>
                    <div class="user-orders-detail" id="adminOrderDetail"></div>
                    <div class="confirm-modal-actions" style="justify-content: flex-end;">
                        <button type="button" class="btn btn-outline-secondary" id="adminOrderClose">Đóng</button>
                    </div>
                </div>
            </div>

            <div id="contactDetailModal" class="confirm-modal" role="dialog" aria-modal="true">
                <div class="confirm-modal-box" style="text-align: left; max-width: 600px;">
                    <div class="confirm-modal-title">Chi tiết yêu cầu hỗ trợ</div>
                    <div id="contactDetailBody" class="mt-3"></div>
                    <div class="confirm-modal-actions mt-4" style="justify-content: flex-end;">
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('contactDetailModal').classList.remove('is-open')">Đóng</button>
                    </div>
                </div>
            </div>

            <div id="contactReplyModal" class="confirm-modal" role="dialog" aria-modal="true">
                <div class="confirm-modal-box" style="text-align: left; max-width: 500px;">
                    <div class="confirm-modal-title">Phản hồi khách hàng</div>
                    <form method="POST" class="mt-3 row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="contact_id" id="replyContactId">
                        <input type="hidden" name="contact_email" id="replyContactEmail">
                        <input type="hidden" name="contact_name" id="replyContactName">
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">Người nhận</label>
                            <input type="text" id="displayReplyName" class="form-control" readonly disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Nội dung phản hồi</label>
                            <textarea name="reply_message" id="reply_message_editor" class="form-control editor" rows="5" placeholder="Nhập nội dung phản hồi cho khách hàng..."></textarea>
                        </div>
                        <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('contactReplyModal').classList.remove('is-open')">Hủy</button>
                            <button type="submit" name="reply_contact" class="btn btn-green">Gửi phản hồi</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="userOrdersModal" class="confirm-modal" role="dialog" aria-modal="true"
                aria-labelledby="userOrdersTitle">
                <div class="confirm-modal-box user-orders-modal-box">
                    <div class="confirm-modal-title" id="userOrdersTitle">Đơn hàng của khách</div>
                    <p class="confirm-modal-desc" id="userOrdersDesc"></p>
                    <div class="user-orders-layout">
                        <div id="userOrdersBody"></div>
                        <div class="user-orders-detail" id="userOrdersDetail">
                            <h6>Chi tiết đơn hàng</h6>
                            <p class="user-orders-empty">Chọn một đơn để xem chi tiết.</p>
                        </div>
                    </div>
                    <div class="confirm-modal-actions" style="justify-content: flex-end;">
                        <button type="button" class="btn btn-outline-secondary" id="userOrdersClose">Đóng</button>
                    </div>
                </div>
            </div>

            <button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

        </div> <!-- End Main Content -->

        <!-- JAVASCRIPT LOGIC -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
        <script>
            tinymce.init({
                selector: 'textarea.editor',
                height: 260,
                menubar: false,
                branding: false,
                plugins: 'lists link table code',
                toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | code',
                content_style: "body { font-family: 'Poppins', sans-serif; font-size: 14px; }"
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('productForm');
                const addBtn = document.getElementById('addProductBtn');
                const updateBtn = document.getElementById('updateProductBtn');
                const cancelBtn = document.getElementById('cancelEditBtn');
                const productId = document.getElementById('productId');
                const currentImage = document.getElementById('currentImage');
                const nameInput = document.getElementById('productName');
                const typeInput = document.getElementById('productType');
                const priceInput = document.getElementById('productPrice');
                const descInput = document.getElementById('productDesc');
                const searchInput = document.getElementById('productSearchInput');
                const productTableBody = document.querySelector('#products .custom-table tbody');
                const promotionProductSelect = document.getElementById('promotionProductSelect');
                const promotionCurrentPrice = document.getElementById('promotionCurrentPrice');

                function formatVnd(value) {
                    const amount = Number(value || 0);
                    return amount.toLocaleString('vi-VN') + 'đ';
                }

                function syncPromotionCurrentPrice() {
                    if (!promotionProductSelect || !promotionCurrentPrice) {
                        return;
                    }
                    const selectedOption = promotionProductSelect.options[promotionProductSelect.selectedIndex];
                    const selectedPrice = selectedOption ? selectedOption.dataset.price : 0;
                    promotionCurrentPrice.value = formatVnd(selectedPrice);
                }

                if (promotionProductSelect && promotionCurrentPrice) {
                    promotionProductSelect.addEventListener('change', syncPromotionCurrentPrice);
                    syncPromotionCurrentPrice();
                }

                function setEditorValue(value) {
                    const editor = tinymce.get('productDesc');
                    if (editor) {
                        editor.setContent(value || '');
                    } else {
                        descInput.value = value || '';
                    }
                }

                function resetForm() {
                    form.reset();
                    productId.value = '';
                    currentImage.value = '';
                    addBtn.classList.remove('is-hidden');
                    updateBtn.classList.add('is-hidden');
                    cancelBtn.classList.add('is-hidden');
                    setEditorValue('');
                }

                document.querySelectorAll('.product-edit-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        productId.value = btn.dataset.id || '';
                        currentImage.value = btn.dataset.image || '';
                        nameInput.value = btn.dataset.name || '';
                        typeInput.value = btn.dataset.type || 'ngot';
                        priceInput.value = btn.dataset.price || '';
                        addBtn.classList.add('is-hidden');
                        updateBtn.classList.remove('is-hidden');
                        cancelBtn.classList.remove('is-hidden');
                        setEditorValue(btn.dataset.desc || '');
                        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });

                cancelBtn.addEventListener('click', function () {
                    resetForm();
                });

                if (searchInput && productTableBody) {
                    const rows = Array.from(productTableBody.querySelectorAll('tr'));
                    const filterRows = function () {
                        const keyword = (searchInput.value || '').trim().toLowerCase();
                        rows.forEach(function (row) {
                            if (keyword === '') {
                                row.style.display = '';
                                return;
                            }
                            const text = (row.textContent || '').toLowerCase();
                            row.style.display = text.includes(keyword) ? '' : 'none';
                        });
                    };

                    searchInput.addEventListener('input', filterRows);
                }

                const deleteModal = document.getElementById('deleteProductModal');
                const deleteCancel = document.getElementById('deleteProductCancel');
                const deleteConfirm = document.getElementById('deleteProductConfirm');
                const deleteDesc = document.getElementById('deleteProductDesc');
                let deleteUrl = '';

                function closeDeleteModal() {
                    deleteModal.classList.remove('is-open');
                    deleteUrl = '';
                }

                document.querySelectorAll('.product-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        deleteUrl = btn.dataset.deleteUrl || '';
                        const name = btn.dataset.productName || 'Sản phẩm này';
                        deleteDesc.textContent = name + ' sẽ bị xóa vĩnh viễn và không thể khôi phục.';
                        deleteModal.classList.add('is-open');
                    });
                });

                deleteCancel.addEventListener('click', closeDeleteModal);
                deleteConfirm.addEventListener('click', function () {
                    if (deleteUrl) {
                        window.location.href = deleteUrl;
                    }
                });

                deleteModal.addEventListener('click', function (event) {
                    if (event.target === deleteModal) {
                        closeDeleteModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && deleteModal.classList.contains('is-open')) {
                        closeDeleteModal();
                    }
                });

                const deleteUserModal = document.getElementById('deleteUserModal');
                const deleteUserCancel = document.getElementById('deleteUserCancel');
                const deleteUserConfirm = document.getElementById('deleteUserConfirm');
                const deleteUserDesc = document.getElementById('deleteUserDesc');
                let deleteUserUrl = '';

                function closeDeleteUserModal() {
                    deleteUserModal.classList.remove('is-open');
                    deleteUserUrl = '';
                }

                document.querySelectorAll('.user-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        deleteUserUrl = btn.dataset.deleteUrl || '';
                        const name = btn.dataset.userName || 'Khách hàng này';
                        deleteUserDesc.textContent = name + ' sẽ bị xóa vĩnh viễn và không thể khôi phục.';
                        deleteUserModal.classList.add('is-open');
                    });
                });

                deleteUserCancel.addEventListener('click', closeDeleteUserModal);
                deleteUserConfirm.addEventListener('click', function () {
                    if (deleteUserUrl) {
                        window.location.href = deleteUserUrl;
                    }
                });

                deleteUserModal.addEventListener('click', function (event) {
                    if (event.target === deleteUserModal) {
                        closeDeleteUserModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && deleteUserModal.classList.contains('is-open')) {
                        closeDeleteUserModal();
                    }
                });

                const deleteOrderModal = document.getElementById('deleteOrderModal');
                const deleteOrderCancel = document.getElementById('deleteOrderCancel');
                const deleteOrderConfirm = document.getElementById('deleteOrderConfirm');
                const deleteOrderDesc = document.getElementById('deleteOrderDesc');
                let deleteOrderUrl = '';

                function closeDeleteOrderModal() {
                    deleteOrderModal.classList.remove('is-open');
                    deleteOrderUrl = '';
                }

                document.querySelectorAll('.order-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        deleteOrderUrl = btn.dataset.deleteUrl || '';
                        const id = btn.dataset.orderId || '';
                        deleteOrderDesc.textContent = 'Đơn hàng #' + id + ' sẽ bị xóa vĩnh viễn và không thể khôi phục.';
                        deleteOrderModal.classList.add('is-open');
                    });
                });

                deleteOrderCancel.addEventListener('click', closeDeleteOrderModal);
                deleteOrderConfirm.addEventListener('click', function () {
                    if (deleteOrderUrl) {
                        window.location.href = deleteOrderUrl;
                    }
                });

                deleteOrderModal.addEventListener('click', function (event) {
                    if (event.target === deleteOrderModal) {
                        closeDeleteOrderModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && deleteOrderModal.classList.contains('is-open')) {
                        closeDeleteOrderModal();
                    }
                });

                const deletePromotionModal = document.getElementById('deletePromotionModal');
                const deletePromotionCancel = document.getElementById('deletePromotionCancel');
                const deletePromotionConfirm = document.getElementById('deletePromotionConfirm');
                const deletePromotionDesc = document.getElementById('deletePromotionDesc');
                let deletePromotionUrl = '';

                function closeDeletePromotionModal() {
                    deletePromotionModal.classList.remove('is-open');
                    deletePromotionUrl = '';
                }

                document.querySelectorAll('.promo-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function (event) {
                        event.preventDefault();
                        deletePromotionUrl = btn.dataset.deleteUrl || '';
                        const name = btn.dataset.promoName || 'Khuyến mãi này';
                        deletePromotionDesc.textContent = name + ' sẽ bị xóa vĩnh viễn và không thể khôi phục.';
                        deletePromotionModal.classList.add('is-open');
                    });
                });

                deletePromotionCancel.addEventListener('click', closeDeletePromotionModal);
                deletePromotionConfirm.addEventListener('click', function () {
                    if (deletePromotionUrl) {
                        window.location.href = deletePromotionUrl;
                    }
                });

                deletePromotionModal.addEventListener('click', function (event) {
                    if (event.target === deletePromotionModal) {
                        closeDeletePromotionModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && deletePromotionModal.classList.contains('is-open')) {
                        closeDeletePromotionModal();
                    }
                });

                const editPromotionModal = document.getElementById('editPromotionModal');
                const editPromotionCancel = document.getElementById('editPromotionCancel');

                function closeEditPromotionModal() {
                    editPromotionModal.classList.remove('is-open');
                }

                document.querySelectorAll('.promo-edit-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('editPromoId').value = btn.dataset.id;
                        document.getElementById('editPromoName').value = btn.dataset.name;
                        document.getElementById('editPromoPrice').value = btn.dataset.price;
                        document.getElementById('editPromoStart').value = btn.dataset.start;
                        document.getElementById('editPromoEnd').value = btn.dataset.end;
                        editPromotionModal.classList.add('is-open');
                    });
                });

                if (editPromotionCancel) {
                    editPromotionCancel.addEventListener('click', closeEditPromotionModal);
                }

                if (editPromotionModal) {
                    editPromotionModal.addEventListener('click', function (event) {
                        if (event.target === editPromotionModal) {
                            closeEditPromotionModal();
                        }
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && editPromotionModal && editPromotionModal.classList.contains('is-open')) {
                        closeEditPromotionModal();
                    }
                });

                const adminOrderModal = document.getElementById('adminOrderModal');
                const adminOrderDesc = document.getElementById('adminOrderDesc');
                const adminOrderDetail = document.getElementById('adminOrderDetail');
                const adminOrderClose = document.getElementById('adminOrderClose');

                function closeAdminOrderModal() {
                    adminOrderModal.classList.remove('is-open');
                    adminOrderDesc.textContent = '';
                    adminOrderDetail.innerHTML = '';
                }

                function renderAdminOrderDetail(orderId) {
                    const detail = ordersById[orderId];
                    if (!detail) {
                        adminOrderDetail.innerHTML = '<p class="user-orders-empty">Không có dữ liệu đơn hàng.</p>';
                        return;
                    }
                    const items = orderItemsById[orderId] || [];
                    let itemsHtml = '';
                    if (items.length === 0) {
                        itemsHtml = '<p class="user-orders-empty">Không có sản phẩm.</p>';
                    } else {
                        itemsHtml = items.map(function (item) {
                            const total = Number(item.price) * Number(item.quantity);
                            return '<div><span>' + item.ten_banh + ' x' + item.quantity + '</span><strong>' +
                                total.toLocaleString('vi-VN') + 'đ</strong></div>';
                        }).join('');
                    }

                    adminOrderDetail.innerHTML =
                        '<h6>Đơn #' + detail.id + '</h6>' +
                        '<div class="user-orders-meta">' +
                        '<div><strong>Tên người nhận:</strong> ' + detail.recipient_name + '</div>' +
                        '<div><strong>SĐT:</strong> ' + detail.phone + '</div>' +
                        '<div><strong>Địa chỉ:</strong> ' + detail.address + '</div>' +
                        (detail.note ? '<div><strong>Ghi chú:</strong> ' + detail.note + '</div>' : '') +
                        '<div><strong>Phương thức thanh toán:</strong> ' + detail.payment_method + '</div>' +
                        '<div><strong>Trạng thái:</strong> ' + formatStatus(detail.status) + '</div>' +
                        '<div><strong>Ngày đặt:</strong> ' + new Date(detail.created_at).toLocaleString('vi-VN') + '</div>' +
                        '<div><strong>Tổng tiền:</strong> ' + Number(detail.total_amount).toLocaleString('vi-VN') + 'đ</div>' +
                        '</div>' +
                        '<div class="user-orders-items">' + itemsHtml + '</div>';
                }

                document.querySelectorAll('.order-detail-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const orderId = btn.dataset.orderId;
                        adminOrderDesc.textContent = 'Chi tiết đơn hàng #' + orderId + '.';
                        renderAdminOrderDetail(orderId);
                        adminOrderModal.classList.add('is-open');
                    });
                });

                adminOrderClose.addEventListener('click', closeAdminOrderModal);
                adminOrderModal.addEventListener('click', function (event) {
                    if (event.target === adminOrderModal) {
                        closeAdminOrderModal();
                    }
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && adminOrderModal.classList.contains('is-open')) {
                        closeAdminOrderModal();
                    }
                });

                const ordersByUser = <?= json_encode($ordersByUser) ?>;
                const ordersById = <?= json_encode($ordersById) ?>;
                const orderItemsById = <?= json_encode($orderItemsById) ?>;
                const userOrdersModal = document.getElementById('userOrdersModal');
                const userOrdersDesc = document.getElementById('userOrdersDesc');
                const userOrdersBody = document.getElementById('userOrdersBody');
                const userOrdersDetail = document.getElementById('userOrdersDetail');
                const userOrdersClose = document.getElementById('userOrdersClose');

                function closeUserOrdersModal() {
                    userOrdersModal.classList.remove('is-open');
                    userOrdersBody.innerHTML = '';
                    userOrdersDesc.textContent = '';
                    userOrdersDetail.innerHTML = '<h6>Chi tiết đơn hàng</h6><p class="user-orders-empty">Chọn một đơn để xem chi tiết.</p>';
                }

                function formatStatus(status) {
                    const map = {
                        completed: 'Hoàn tất',
                        pending: '&#272;ang ch&#7901; x&#225;c nh&#7853;n',
                        cod_not_deposited: 'Ch&#432;a &#273;&#7863;t c&#7885;c',
                        cod_deposited: '&#272;&#227; &#273;&#7863;t c&#7885;c',
                        paid: 'Đã thanh toán',
                        approved: 'Đã xác nhận',
                        confirmed: 'Đã xác nhận',
                        delivering: 'Đang giao',
                        delivered: 'Đã giao',
                        failed: 'Thanh toán lỗi',
                        cancelled: 'Đã hủy'
                    };
                    const key = (status || '').toLowerCase();
                    return map[key] || status;
                }

                function renderOrderDetail(orderId) {
                    const detail = ordersById[orderId];
                    if (!detail) {
                        userOrdersDetail.innerHTML = '<h6>Chi tiết đơn hàng</h6><p class="user-orders-empty">Không có dữ liệu đơn hàng.</p>';
                        return;
                    }
                    const items = orderItemsById[orderId] || [];
                    let itemsHtml = '';
                    if (items.length === 0) {
                        itemsHtml = '<p class="user-orders-empty">Không có sản phẩm.</p>';
                    } else {
                        itemsHtml = items.map(function (item) {
                            const total = Number(item.price) * Number(item.quantity);
                            return '<div><span>' + item.ten_banh + ' x' + item.quantity + '</span><strong>' +
                                total.toLocaleString('vi-VN') + 'đ</strong></div>';
                        }).join('');
                    }

                    userOrdersDetail.innerHTML =
                        '<h6>Chi tiết đơn #' + detail.id + '</h6>' +
                        '<div class="user-orders-meta">' +
                        '<div><strong>Người nhận:</strong> ' + detail.recipient_name + '</div>' +
                        '<div><strong>SĐT:</strong> ' + detail.phone + '</div>' +
                        '<div><strong>Địa chỉ:</strong> ' + detail.address + '</div>' +
                        (detail.note ? '<div><strong>Ghi chú:</strong> ' + detail.note + '</div>' : '') +
                        '<div><strong>Phương thức:</strong> ' + detail.payment_method + '</div>' +
                        '<div><strong>Trạng thái:</strong> ' + formatStatus(detail.status) + '</div>' +
                        '<div><strong>Ngày đặt:</strong> ' + new Date(detail.created_at).toLocaleString('vi-VN') + '</div>' +
                        '<div><strong>Tổng tiền:</strong> ' + Number(detail.total_amount).toLocaleString('vi-VN') + 'đ</div>' +
                        '</div>' +
                        '<div class="user-orders-items">' + itemsHtml + '</div>';
                }

                function renderUserOrders(orders) {
                    if (!orders || orders.length === 0) {
                        userOrdersBody.innerHTML = '<p class="user-orders-empty">Khách hàng chưa có đơn hàng.</p>';
                        return;
                    }
                    let html = '<table class="user-orders-table"><thead><tr>' +
                        '<th>Mã ĐH</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th></th>' +
                        '</tr></thead><tbody>';
                    orders.forEach(function (order) {
                        const dateText = new Date(order.created_at).toLocaleString('vi-VN');
                        html += '<tr>' +
                            '<td>#' + order.id + '</td>' +
                            '<td>' + dateText + '</td>' +
                            '<td>' + Number(order.total_amount).toLocaleString('vi-VN') + 'đ</td>' +
                            '<td>' + formatStatus(order.status) + '</td>' +
                            '<td><button type="button" class="btn btn-sm btn-outline-primary user-order-detail-btn" data-order-id="' + order.id + '">Chi tiết</button></td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';
                    userOrdersBody.innerHTML = html;

                    userOrdersBody.querySelectorAll('.user-order-detail-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const orderId = btn.dataset.orderId;
                            renderOrderDetail(orderId);
                        });
                    });
                }

                document.querySelectorAll('.user-orders-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const userId = btn.dataset.userId;
                        const userName = btn.dataset.userName || 'Khách hàng';
                        userOrdersDesc.textContent = 'Danh sách đơn hàng của ' + userName + '.';
                        renderUserOrders(ordersByUser[userId] || []);
                        userOrdersModal.classList.add('is-open');
                    });
                });

                userOrdersClose.addEventListener('click', closeUserOrdersModal);
                userOrdersModal.addEventListener('click', function (event) {
                    if (event.target === userOrdersModal) {
                        closeUserOrdersModal();
                    }
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && userOrdersModal.classList.contains('is-open')) {
                        closeUserOrdersModal();
                    }
                });

                // CONTACT LOGIC
                document.querySelectorAll('.contact-detail-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const d = btn.dataset;
                        let html = `
                            <div class="mb-2"><strong>Khách hàng:</strong> ${d.name}</div>
                            <div class="mb-2"><strong>Email:</strong> ${d.email}</div>
                            <div class="mb-2"><strong>SĐT:</strong> ${d.phone}</div>
                            <div class="mb-2"><strong>Ngày gửi:</strong> ${d.date}</div>
                            <div class="mb-3 p-3 bg-light rounded" style="border:1px solid #eee">
                                <strong>Nội dung thắc mắc:</strong><br>${d.msg}
                            </div>
                        `;
                        if (d.status === 'replied') {
                            html += `
                                <div class="mt-3 p-3 rounded" style="background:#e8f5e9; border:1px solid #c8e6c9">
                                    <strong class="text-success"><i class="bi bi-check-circle"></i> Đã phản hồi:</strong><br>
                                    ${d.reply}
                                </div>
                            `;
                        }
                        document.getElementById('contactDetailBody').innerHTML = html;
                        document.getElementById('contactDetailModal').classList.add('is-open');
                    });
                });

                document.querySelectorAll('.contact-reply-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('replyContactId').value = btn.dataset.id;
                        document.getElementById('replyContactEmail').value = btn.dataset.email;
                        document.getElementById('replyContactName').value = btn.dataset.name;
                        document.getElementById('displayReplyName').value = btn.dataset.name + ' (' + btn.dataset.email + ')';
                        document.getElementById('contactReplyModal').classList.add('is-open');
                        // Reset TinyMCE content when opening
                        const editor = tinymce.get('reply_message_editor');
                        if (editor) {
                            editor.setContent('');
                        }
                    });
                });

                // Ensure TinyMCE saves content before form submit
                document.querySelectorAll('form').forEach(form => {
                    form.addEventListener('submit', () => {
                        if (typeof tinymce !== 'undefined') {
                            tinymce.triggerSave();
                        }
                    });
                });
            });
        </script>

        <script>
            // Global Toast Logic
            window.showToast = function (msg, type = 'success') {
                let config = {
                    success: { bg: 'linear-gradient(135deg, #4a1d1f, #6a2d22)', icon: '✓' },
                    error: { bg: 'linear-gradient(135deg, #b42318, #f04438)', icon: '✕' },
                    info: { bg: 'linear-gradient(135deg, #1d4ed8, #3b82f6)', icon: 'ℹ' },
                    warning: { bg: 'linear-gradient(135deg, #b45309, #f59e0b)', icon: '⚠' }
                };
                let c = config[type] || config.success;

                Toastify({
                    text: c.icon + ' ' + msg,
                    duration: 3500,
                    close: true,
                    gravity: "top",
                    position: "right",
                    stopOnFocus: true,
                    style: {
                        background: c.bg,
                        borderRadius: "14px",
                        fontFamily: "'Poppins', sans-serif",
                        fontWeight: "600",
                        fontSize: "14px",
                        padding: "14px 20px",
                        boxShadow: "0 8px 24px rgba(0,0,0,0.18)",
                        minWidth: "260px"
                    }
                }).showToast();
            };

            <?php if (isset($_SESSION['admin_toast'])): ?>
                showToast("<?= $_SESSION['admin_toast']['msg'] ?>", "<?= $_SESSION['admin_toast']['type'] ?>");
                <?php unset($_SESSION['admin_toast']); ?>
            <?php endif; ?>

            const scrollTopBtn = document.getElementById('scrollTopBtn');

            function getActiveAdminTab() {
                const activeTab = document.querySelector('.tab-content.active');
                return activeTab ? activeTab.id : '';
            }

            function updateScrollTopButton() {
                if (!scrollTopBtn) return;
                const tab = getActiveAdminTab();
                const allow = tab === 'orders' || tab === 'best-selling';
                const shouldShow = allow && window.scrollY > 300;
                scrollTopBtn.classList.toggle('is-visible', shouldShow);
            }

            // 1. Logic chuyển Tab
            function activateTab(tabName) {
                if (!tabName) return;
                var tabContent = document.getElementsByClassName("tab-content");
                for (var i = 0; i < tabContent.length; i++) {
                    tabContent[i].classList.remove("active");
                }

                var navLinks = document.getElementsByClassName("nav-link");
                for (var i = 0; i < navLinks.length; i++) {
                    navLinks[i].classList.remove("active");
                }

                var tabElement = document.getElementById(tabName);
                if (tabElement) {
                    tabElement.classList.add("active");
                }

                var activeLink = document.querySelector('.nav-link[data-tab="' + tabName + '"]');
                if (activeLink) {
                    activeLink.classList.add("active");
                }

                updateScrollTopButton();
            }

            function showTab(evt, tabName) {
                if (evt) {
                    evt.preventDefault();
                }
                activateTab(tabName);
                if (history.replaceState) {
                    history.replaceState(null, '', 'admin.php?tab=' + tabName + '#' + tabName);
                } else {
                    window.location.hash = tabName;
                }

                updateScrollTopButton();
            }

            document.addEventListener('DOMContentLoaded', function () {
                const params = new URLSearchParams(window.location.search);
                const tabFromParam = params.get('tab');
                const tabFromHash = window.location.hash.replace('#', '');
                activateTab(tabFromParam || tabFromHash || 'dashboard');

                updateScrollTopButton();
                window.addEventListener('scroll', updateScrollTopButton, { passive: true });

                if (scrollTopBtn) {
                    scrollTopBtn.addEventListener('click', function () {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }

                const selectAll = document.getElementById('selectAllOrders');
                if (!selectAll) return;
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.order-select').forEach(function (item) {
                        item.checked = selectAll.checked;
                    });
                });

                const viewSelect = document.getElementById('chartViewSelect');
                const monthSelect = document.getElementById('chartMonth');
                const yearSelect = document.getElementById('chartYear');
                if (viewSelect && monthSelect && yearSelect) {
                    const toggleRangeFields = function () {
                        if (viewSelect.value === 'year') {
                            monthSelect.disabled = true;
                            yearSelect.disabled = false;
                        } else if (viewSelect.value === 'month') {
                            monthSelect.disabled = false;
                            yearSelect.disabled = false;
                        } else {
                            monthSelect.disabled = true;
                            yearSelect.disabled = true;
                        }
                    };
                    viewSelect.addEventListener('change', toggleRangeFields);
                    toggleRangeFields();
                }
            });

            // 2. Vẽ biểu đồ Chart.js (Chỉ chạy khi đã login)
            document.addEventListener("DOMContentLoaded", function () {
                const chartCanvas = document.getElementById('revenueChart');
                if (chartCanvas) {
                    const ctx = chartCanvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= $js_dates ?>, // Dữ liệu ngày từ PHP
                            datasets: [{
                                label: 'Doanh thu (VNĐ)',
                                data: <?= $js_revenues ?>, // Dữ liệu tiền từ PHP
                                backgroundColor: 'rgba(46, 125, 50, 0.6)', // Màu xanh pastel đậm
                                borderColor: 'rgba(46, 125, 50, 1)',
                                borderWidth: 1,
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function (value) { return value.toLocaleString('vi-VN') + 'đ'; } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) { return context.raw.toLocaleString('vi-VN') + ' VNĐ'; }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
</body>

</html>
<?php $conn->close(); ?>
