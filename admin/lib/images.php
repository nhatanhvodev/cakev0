<?php
/* admin/lib/images.php — shared product-image helpers for the modular admin.
 * Moved verbatim (logic unchanged) from admin/admin.php:
 *   - project_local_path() <- admin.php L105-109
 *   - buildImageUrl()      <- admin.php L57-103
 *   - storeProductImageUpload() <- admin.php L111-145
 * These three are NOT autoloaded anywhere in the modular admin/ tree (every
 * legacy page — admin.php, pages/product.php, pages/cart.php, etc. — carries
 * its own private copy instead of a shared include), so per the task brief
 * they are consolidated here once and required by both
 * admin/handlers/products.php and admin/views/tabs/products.php, rather than
 * duplicating the bodies in two places.
 *
 * Depends on (already required by admin/bootstrap.php before this file):
 *   - base_url()               <- config/config.php
 *   - is_remote_media_url()    <- config/uploadthing.php
 *   - uploadthing_enabled(), uploadthing_upload_file() <- config/uploadthing.php
 */

function project_local_path(string $relativePath): string
{
    $projectRoot = dirname(__DIR__, 2);
    return $projectRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

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
