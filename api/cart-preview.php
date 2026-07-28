<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function cart_preview_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cart_preview_image_url(?string $path): string
{
    $fallback = base_url('assets/img/no-image.jpg');
    $path = trim((string) ($path ?? ''));
    if ($path === '') {
        return $fallback;
    }

    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:image/')) {
        return $path;
    }

    $cakePos = stripos($path, '/cakev0/');
    if ($cakePos !== false) {
        $path = substr($path, $cakePos + 8);
    } else {
        $cakePos = stripos($path, 'cakev0/');
        if ($cakePos !== false) {
            $path = substr($path, $cakePos + 7);
        }
    }

    $path = ltrim($path, '/');
    if (strpos($path, 'img/') === 0 || strpos($path, 'uploads/') === 0) {
        $path = 'assets/' . $path;
    }

    return base_url($path);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    cart_preview_json(['error' => 'method'], 405);
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId <= 0) {
    cart_preview_json([
        'authenticated' => false,
        'count' => 0,
        'subtotal' => 0,
        'items' => [],
    ]);
}

$stmt = $conn->prepare(
    "SELECT c.id AS cart_id,
            c.quantity,
            b.id AS banh_id,
            b.ten_banh,
            b.slug,
            b.hinh_anh,
            b.gia,
            c.quantity * b.gia AS line_total
     FROM cart c
     JOIN banh b ON b.id = c.banh_id
     WHERE c.user_id = ? AND b.is_hidden = 0
     ORDER BY c.id DESC"
);

if (!$stmt) {
    cart_preview_json([
        'authenticated' => true,
        'count' => 0,
        'subtotal' => 0,
        'items' => [],
    ]);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtotal = 0.0;
$items = [];
foreach ($rows as $index => $row) {
    $quantity = max(1, (int) ($row['quantity'] ?? 1));
    $price = (float) ($row['gia'] ?? 0);
    $lineTotal = (float) ($row['line_total'] ?? ($price * $quantity));
    $subtotal += $lineTotal;

    if ($index >= 5) {
        continue;
    }

    $productId = (int) ($row['banh_id'] ?? 0);
    $slug = trim((string) ($row['slug'] ?? ''));
    $items[] = [
        'id' => $productId,
        'cart_id' => (int) ($row['cart_id'] ?? 0),
        'name' => (string) ($row['ten_banh'] ?? 'Sản phẩm'),
        'quantity' => $quantity,
        'price' => $price,
        'line_total' => $lineTotal,
        'image' => cart_preview_image_url((string) ($row['hinh_anh'] ?? '')),
        'href' => $slug !== ''
            ? base_url('product/' . rawurlencode($slug))
            : base_url('pages/product-detail.php?id=' . $productId),
    ];
}

cart_preview_json([
    'authenticated' => true,
    'count' => count($rows),
    'subtotal' => $subtotal,
    'has_more' => count($rows) > count($items),
    'items' => $items,
]);
