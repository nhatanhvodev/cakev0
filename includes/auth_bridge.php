<?php

const AUTH0_CLAIM_ROLE = 'https://gaubakery.app/role';
const AUTH0_CLAIM_USERNAME = 'https://gaubakery.app/username';

if (!function_exists('auth0_extract_identity')) {
    function auth0_extract_identity(array $claims): array
    {
        $sub   = (string) ($claims['sub'] ?? '');
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        $username = (string) ($claims[AUTH0_CLAIM_USERNAME] ?? '');
        if ($username === '') {
            $username = (string) ($claims['nickname'] ?? '');
        }
        if ($username === '' && $email !== '') {
            $atPos = strpos($email, '@');
            $username = substr($email, 0, $atPos !== false ? $atPos : strlen($email));
        }

        $role = ((string) ($claims[AUTH0_CLAIM_ROLE] ?? '')) === 'admin' ? 'admin' : 'user';

        return [
            'auth0_id' => $sub,
            'email'    => $email,
            'email_verified' => ($claims['email_verified'] ?? true) !== false,
            'username' => $username,
            'role'     => $role,
        ];
    }
}

if (!function_exists('auth0_email_verified')) {
    // Auth0 gui email_verified=false cho tai khoan DB connection chua xac minh.
    // Thieu claim (vd social login cu) -> coi nhu da xac minh de khong chan nham.
    function auth0_email_verified(array $claims): bool
    {
        return ($claims['email_verified'] ?? true) !== false;
    }
}

if (!function_exists('resolve_local_admin')) {
    function resolve_local_admin(mysqli $conn, array $identity): ?array
    {
        $email = strtolower(trim((string) ($identity['email'] ?? '')));
        $username = trim((string) ($identity['username'] ?? ''));
        $roleIsAdmin = ($identity['role'] ?? '') === 'admin';
        $emailVerified = ($identity['email_verified'] ?? true) !== false;

        $candidates = [];
        if ($emailVerified && str_ends_with($email, '@gaubakery.vn')) {
            $local = substr($email, 0, -strlen('@gaubakery.vn'));
            if ($local !== '') {
                $candidates[] = $local;
            }
        }

        if ($roleIsAdmin && $username !== '') {
            $candidates[] = $username;
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $stmt = $conn->prepare("SELECT id, username FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['id' => (int) $row['id'], 'username' => (string) $row['username']];
            }
        }

        return null;
    }
}

if (!function_exists('resolve_local_user')) {
    function resolve_local_user(mysqli $conn, array $identity): array
    {
        $auth0Id  = (string) $identity['auth0_id'];
        $email    = (string) $identity['email'];
        $username = (string) $identity['username'];
        $admin    = resolve_local_admin($conn, $identity);
        $role     = $admin !== null || $identity['role'] === 'admin' ? 'admin' : 'user';
        $sessionUsername = $admin['username'] ?? $username;

        // 1) Khop theo auth0_id
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE auth0_id = ? LIMIT 1");
        $stmt->bind_param('s', $auth0Id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return [
                'id' => (int) $row['id'],
                'username' => $sessionUsername !== '' ? $sessionUsername : (string) $row['username'],
                'role' => $role,
                'admin_id' => $admin['id'] ?? null,
            ];
        }

        // 2) Khop theo email -> link auth0_id vao dong cu
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id = (int) $row['id'];
            $upd = $conn->prepare("UPDATE users SET auth0_id = ? WHERE id = ?");
            $upd->bind_param('si', $auth0Id, $id);
            $upd->execute();
            $upd->close();
            return [
                'id' => $id,
                'username' => $sessionUsername !== '' ? $sessionUsername : (string) $row['username'],
                'role' => $role,
                'admin_id' => $admin['id'] ?? null,
            ];
        }

        // 3) Tao moi (password rong: Auth0 quan credential)
        $empty = '';
        if ($sessionUsername !== '') {
            $username = $sessionUsername;
        }
        $ins = $conn->prepare("INSERT INTO users (username, password, email, auth0_id) VALUES (?, ?, ?, ?)");
        $ins->bind_param('ssss', $username, $empty, $email, $auth0Id);
        $ins->execute();
        $newId = (int) $conn->insert_id;
        $ins->close();
        return ['id' => $newId, 'username' => $username, 'role' => $role, 'admin_id' => $admin['id'] ?? null];
    }
}

if (!function_exists('apply_session_for_user')) {
    function apply_session_for_user(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = $user['role'] === 'admin' ? 'admin' : 'user';

        if ($_SESSION['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int) ($user['admin_id'] ?? $user['id']);
        } else {
            unset($_SESSION['admin_logged_in'], $_SESSION['admin_id']);
        }
    }
}

if (!function_exists('safe_redirect_target')) {
    function safe_redirect_target(?string $raw, string $fallback): string
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            return $fallback;
        }

        if (str_starts_with($raw, '/cakev0/') && !str_starts_with($raw, '//')) {
            return $raw;
        }

        return $fallback;
    }
}

if (!function_exists('auth0_callback_error_reason')) {
    function auth0_callback_error_reason(Throwable $error, array $query = []): string
    {
        $providerError = strtolower(trim((string) ($query['error'] ?? '')));
        if ($providerError !== '') {
            return 'provider_' . preg_replace('/[^a-z0-9_]+/', '_', $providerError);
        }

        $message = strtolower($error->getMessage());
        if (str_contains($message, 'invalid state')) {
            return 'invalid_state';
        }
        if (str_contains($message, 'missing code_verifier')) {
            return 'missing_code_verifier';
        }
        if (str_contains($message, 'missing code')) {
            return 'missing_code';
        }
        if (str_contains($message, 'code exchange was unsuccessful')) {
            return 'failed_code_exchange';
        }
        if (str_contains($message, 'nonce was not found')) {
            return 'missing_nonce';
        }
        if (str_contains($message, 'invalid access_token')) {
            return 'bad_access_token';
        }

        return 'exchange_failed';
    }
}

if (!function_exists('auth0_callback_error_detail')) {
    function auth0_callback_error_detail(Throwable $error, array $query = []): string
    {
        $detail = trim((string) ($query['error_description'] ?? ''));
        if ($detail === '') {
            $detail = trim($error->getMessage());
        }

        $detail = preg_replace('/[\r\n\t]+/', ' ', $detail) ?? '';
        $detail = preg_replace('/\s+/', ' ', $detail) ?? '';
        $detail = trim($detail);

        if (strlen($detail) > 180) {
            $detail = substr($detail, 0, 180);
        }

        return $detail;
    }
}

if (!function_exists('sync_session_from_auth0')) {
    function sync_session_from_auth0(mysqli $conn, array $claims): array
    {
        $identity = auth0_extract_identity($claims);
        $user = resolve_local_user($conn, $identity);
        apply_session_for_user($user);
        return $user;
    }
}
