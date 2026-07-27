<?php
/* admin/handlers/products.php — Products tab action handlers.
 * Ported from admin/admin.php (logic verbatim):
 *   - handle_add_product()          <- add_product POST handler (admin.php L210-295)
 *   - handle_update_product()       <- update_product POST handler (admin.php L297-368)
 *   - handle_delete_product_image() <- delete_product_image POST handler (admin.php L370-400)
 *   - handle_delete_product()       <- delete_product_id GET handler (admin.php L905-943)
 * Image upload/path helpers (storeProductImageUpload, buildImageUrl,
 * project_local_path) live in admin/lib/images.php — legacy defined them
 * inline in admin.php with no shared include, so they were moved out once
 * here instead of duplicating the bodies in both this handler and the view.
 * is_remote_media_url() comes from config/uploadthing.php (already required
 * by admin/bootstrap.php).
 * Note: legacy also called regenerateCsrfToken() after each mutation; like
 * admin/handlers/orders.php, the modular admin's bootstrap.php does not
 * define/use that rotation scheme, so it is intentionally omitted here
 * (kept the CSRF-check + toast + redirect contract only).
 */
require_once __DIR__ . '/../lib/images.php';

/* --- Tên/slug helpers, ported verbatim from admin.php L147-171 ---
 * Only needed by the products handlers (add/update set banh.slug), so kept
 * local here rather than in the shared images lib (they are not image
 * helpers). No other ported tab currently needs them.
 */
if (!function_exists('safeTransliterate')) {
    function safeTransliterate(string $value): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted === false || $converted === '') {
            return $value;
        }
        return $converted;
    }
}

if (!function_exists('slugify')) {
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
}

function handle_add_product(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('products');
    }

    $ten_banh = trim($_POST['ten_banh'] ?? '');
    $loai = $_POST['loai'] ?? '';
    $gia = isset($_POST['gia']) ? (float) $_POST['gia'] : 0;
    $mo_ta = trim($_POST['mo_ta'] ?? '');
    if ($ten_banh === '' || $loai === '' || $gia <= 0) {
        setAdminToast("Dữ liệu sản phẩm không hợp lệ", "error");
        redirectToTab('products');
    }

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
    $stmt->close();
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

function handle_update_product(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('products');
    }

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
    redirectToTab('products');
}

function handle_delete_product_image(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('products');
    }

    $imageId = (int) ($_POST['delete_product_image'] ?? 0);
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

    redirectToTab('products');
}

function handle_delete_product(mysqli $conn): void
{
    // GET-triggered delete — legacy had no CSRF check on this link at all
    // (admin.php L905). Hardened per plan Global Constraints: verify the
    // csrf query param before touching the database (same pattern as
    // admin/handlers/orders.php::handle_delete_order()).
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '')) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('products');
    }

    $id = (int) ($_GET['delete_product_id'] ?? 0);

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
