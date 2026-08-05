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
            'username' => $username,
            'role'     => $role,
        ];
    }
}

if (!function_exists('resolve_local_user')) {
    function resolve_local_user(mysqli $conn, array $identity): array
    {
        $auth0Id  = (string) $identity['auth0_id'];
        $email    = (string) $identity['email'];
        $username = (string) $identity['username'];
        $role     = $identity['role'] === 'admin' ? 'admin' : 'user';

        // 1) Khop theo auth0_id
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE auth0_id = ? LIMIT 1");
        $stmt->bind_param('s', $auth0Id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return ['id' => (int) $row['id'], 'username' => (string) $row['username'], 'role' => $role];
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
            return ['id' => $id, 'username' => (string) $row['username'], 'role' => $role];
        }

        // 3) Tao moi (password rong: Auth0 quan credential)
        $empty = '';
        $ins = $conn->prepare("INSERT INTO users (username, password, email, auth0_id) VALUES (?, ?, ?, ?)");
        $ins->bind_param('ssss', $username, $empty, $email, $auth0Id);
        $ins->execute();
        $newId = (int) $conn->insert_id;
        $ins->close();
        return ['id' => $newId, 'username' => $username, 'role' => $role];
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
            $_SESSION['admin_id'] = (int) $user['id'];
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

        if (str_starts_with($raw, '/cakev0/') && !str_starts_with($raw, '//')) {
            return $raw;
        }

        return $fallback;
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
