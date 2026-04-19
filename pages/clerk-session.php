<?php
declare(strict_types=1);

use Clerk\Backend\ClerkBackend;
use Clerk\Backend\Helpers\Jwks\VerifyToken;
use Clerk\Backend\Helpers\Jwks\VerifyTokenOptions;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

function clerk_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clerk_parse_authorized_parties(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }

    $parts = array_map('trim', explode(',', $raw));
    $parts = array_filter($parts, static fn ($item) => $item !== '');

    return array_values(array_unique($parts));
}

function clerk_extract_session_token(): string
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && trim($rawBody) !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $token = $decoded['token'] ?? $decoded['session_token'] ?? '';
            if (is_string($token) && trim($token) !== '') {
                return trim($token);
            }
        }
    }

    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers) && isset($headers['Authorization'])) {
            $authorization = (string) $headers['Authorization'];
        }
    }

    if ($authorization !== '' && preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        return trim((string) ($matches[1] ?? ''));
    }

    return '';
}

function clerk_column_exists(mysqli $conn, string $column): bool
{
    $sql = "SHOW COLUMNS FROM users LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

function clerk_normalize_username(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/i', '_', $value);
    $value = trim((string) $value, '._-');

    if ($value === '') {
        $value = 'user';
    }

    return substr($value, 0, 40);
}

function clerk_username_exists(mysqli $conn, string $username): bool
{
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

function clerk_generate_unique_username(mysqli $conn, string $base): string
{
    $base = clerk_normalize_username($base);
    $candidate = $base;

    if (!clerk_username_exists($conn, $candidate)) {
        return $candidate;
    }

    for ($i = 0; $i < 100; $i++) {
        $suffix = '_' . (string) random_int(1000, 9999);
        $prefixLength = max(1, 40 - strlen($suffix));
        $candidate = substr($base, 0, $prefixLength) . $suffix;

        if (!clerk_username_exists($conn, $candidate)) {
            return $candidate;
        }
    }

    return 'user_' . bin2hex(random_bytes(3));
}

function clerk_select_columns(bool $hasRoleColumn): string
{
    if ($hasRoleColumn) {
        return 'id, username, role, avatar, clerk_user_id';
    }

    return 'id, username, avatar, clerk_user_id';
}

function clerk_find_user_by_clerk_id(mysqli $conn, string $clerkUserId, bool $hasRoleColumn): ?array
{
    $sql = 'SELECT ' . clerk_select_columns($hasRoleColumn) . ' FROM users WHERE clerk_user_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $clerkUserId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

function clerk_find_user_by_email(mysqli $conn, string $email, bool $hasRoleColumn): ?array
{
    $sql = 'SELECT ' . clerk_select_columns($hasRoleColumn) . ' FROM users WHERE email = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clerk_json_response(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$secretKey = (string) env_value('CLERK_SECRET_KEY', '');
if ($secretKey === '') {
    clerk_json_response(500, ['ok' => false, 'message' => 'CLERK_SECRET_KEY chưa được cấu hình.']);
}

$sessionToken = clerk_extract_session_token();
if ($sessionToken === '') {
    clerk_json_response(400, ['ok' => false, 'message' => 'Thiếu token phiên đăng nhập Clerk.']);
}

$authorizedParties = clerk_parse_authorized_parties((string) env_value('CLERK_AUTHORIZED_PARTIES', ''));

try {
    $verifyOptions = new VerifyTokenOptions(
        secretKey: $secretKey,
        authorizedParties: $authorizedParties !== [] ? $authorizedParties : null
    );
    $claims = VerifyToken::verifyToken($sessionToken, $verifyOptions);
} catch (Throwable $error) {
    clerk_json_response(401, ['ok' => false, 'message' => 'Token Clerk không hợp lệ hoặc đã hết hạn.']);
}

$clerkUserId = trim((string) ($claims->sub ?? ''));
if ($clerkUserId === '') {
    clerk_json_response(401, ['ok' => false, 'message' => 'Không đọc được Clerk user id từ token.']);
}

if (!clerk_column_exists($conn, 'clerk_user_id')) {
    clerk_json_response(500, [
        'ok' => false,
        'message' => 'Thiếu cột users.clerk_user_id. Hãy chạy migration trong database/migrations/20260418_add_clerk_user_id.sql',
    ]);
}

$hasRoleColumn = clerk_column_exists($conn, 'role');

$primaryEmail = '';
$usernameHint = '';

try {
    $sdk = ClerkBackend::builder()
        ->setSecurity($secretKey)
        ->build();

    $userResponse = $sdk->users->get($clerkUserId);
    $clerkUser = $userResponse->user;

    if ($clerkUser !== null) {
        $usernameHint = (string) ($clerkUser->username ?? '');
        if ($usernameHint === '') {
            $name = trim((string) ($clerkUser->firstName ?? '') . ' ' . (string) ($clerkUser->lastName ?? ''));
            $usernameHint = $name;
        }

        $primaryEmailId = (string) ($clerkUser->primaryEmailAddressId ?? '');
        if (!empty($clerkUser->emailAddresses)) {
            foreach ($clerkUser->emailAddresses as $emailAddress) {
                if (!isset($emailAddress->emailAddress)) {
                    continue;
                }

                if ($primaryEmailId !== '' && isset($emailAddress->id) && (string) $emailAddress->id === $primaryEmailId) {
                    $primaryEmail = (string) $emailAddress->emailAddress;
                    break;
                }

                if ($primaryEmail === '') {
                    $primaryEmail = (string) $emailAddress->emailAddress;
                }
            }
        }
    }
} catch (Throwable $error) {
    // Continue with token-only claims if Clerk profile fetch fails.
}

$user = clerk_find_user_by_clerk_id($conn, $clerkUserId, $hasRoleColumn);

if ($user === null && $primaryEmail !== '') {
    $userByEmail = clerk_find_user_by_email($conn, $primaryEmail, $hasRoleColumn);
    if ($userByEmail !== null) {
        $existingClerkUserId = trim((string) ($userByEmail['clerk_user_id'] ?? ''));
        if ($existingClerkUserId !== '' && $existingClerkUserId !== $clerkUserId) {
            clerk_json_response(409, [
                'ok' => false,
                'message' => 'Email này đã liên kết với một tài khoản Clerk khác.',
            ]);
        }

        if ($existingClerkUserId === '') {
            $linkStmt = $conn->prepare('UPDATE users SET clerk_user_id = ? WHERE id = ?');
            if ($linkStmt) {
                $id = (int) $userByEmail['id'];
                $linkStmt->bind_param('si', $clerkUserId, $id);
                $linkStmt->execute();
                $linkStmt->close();
            }
        }

        $user = clerk_find_user_by_clerk_id($conn, $clerkUserId, $hasRoleColumn);
    }
}

if ($user === null) {
    $usernameSource = $usernameHint;
    if ($usernameSource === '' && $primaryEmail !== '') {
        $usernameSource = (string) strstr($primaryEmail, '@', true);
    }
    if ($usernameSource === '') {
        $usernameSource = 'user_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $clerkUserId), -8);
    }

    $newUsername = clerk_generate_unique_username($conn, $usernameSource);
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $emailForInsert = $primaryEmail !== '' ? $primaryEmail : null;

    $insertStmt = $conn->prepare('INSERT INTO users (clerk_user_id, username, password, email) VALUES (?, ?, ?, ?)');
    if (!$insertStmt) {
        clerk_json_response(500, ['ok' => false, 'message' => 'Không thể tạo tài khoản nội bộ cho Clerk user.']);
    }

    $insertStmt->bind_param('ssss', $clerkUserId, $newUsername, $passwordHash, $emailForInsert);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        clerk_json_response(500, ['ok' => false, 'message' => 'Tạo người dùng nội bộ thất bại.']);
    }
    $insertStmt->close();

    $user = clerk_find_user_by_clerk_id($conn, $clerkUserId, $hasRoleColumn);
}

if ($user === null) {
    clerk_json_response(500, ['ok' => false, 'message' => 'Không thể đồng bộ tài khoản Clerk với hệ thống.']);
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    clerk_json_response(500, ['ok' => false, 'message' => 'Tài khoản nội bộ không hợp lệ.']);
}

$role = 'user';
if ($hasRoleColumn) {
    $resolvedRole = trim((string) ($user['role'] ?? 'user'));
    $role = $resolvedRole !== '' ? $resolvedRole : 'user';
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = (string) ($user['username'] ?? 'user');
$_SESSION['role'] = $role;
$_SESSION['clerk_user_id'] = $clerkUserId;
$_SESSION['toast'] = ['msg' => 'Đăng nhập Clerk thành công!', 'type' => 'success'];

if ($role === 'admin') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $userId;
}

$ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$logStmt = $conn->prepare("INSERT INTO login_logs(user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
if ($logStmt) {
    $logStmt->bind_param('is', $userId, $ipAddress);
    $logStmt->execute();
    $logStmt->close();
}

$redirect = $role === 'admin' ? base_url('admin/admin.php') : base_url('index.php');

clerk_json_response(200, [
    'ok' => true,
    'user_id' => $userId,
    'redirect' => $redirect,
]);
