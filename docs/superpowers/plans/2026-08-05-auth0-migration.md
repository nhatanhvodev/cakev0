# Auth0 Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuyển toàn bộ xác thực đăng nhập/đăng ký sang Auth0 (Universal Login, MFA, Google, bulk-import bcrypt) trong khi giữ nguyên tầng dữ liệu khoá theo `users.id` (int) qua một lớp cầu nối session.

**Architecture:** Auth0 SDK (`auth0/auth0-php` v8) là authority xác thực và quản session cookie riêng. Sau OIDC callback, một lớp bridge resolve/provision một dòng `users` (mirror, khoá `auth0_id`) rồi set các biến `$_SESSION` mà 23 file hiện có đang đọc (`user_id`, `username`, `role`, cờ admin). Nhờ vậy chat HMAC, checkout, admin guard, account chạy nguyên xi.

**Tech Stack:** PHP 8.2, MySQL 8 (mysqli, prepared statements), Auth0 PHP SDK v8, Composer, test là script standalone (`tests/bootstrap.php` + `assert_true`/`assert_same`).

## Global Constraints

- PHP 8.2, không framework; DB access dùng `prepare()` + `bind_param()`; escape output bằng `htmlspecialchars()`.
- Base path `/cakev0/` hardcoded trong URL/redirect/callback — luôn giữ prefix.
- Chuỗi/comment/commit tiếng Việt; khớp ngôn ngữ file xung quanh.
- Test là script chạy trực tiếp `php tests/<ten>_test.php`, exit != 0 khi fail, in `... ok` khi pass. Không PHPUnit.
- **Không có MySQL local** trong môi trường này. Test cần DB đánh dấu `[DOCKER]` và chạy qua `docker compose` (`docker compose exec web php tests/<ten>_test.php`). Test thuần (không DB) chạy trực tiếp bằng `php`.
- Giữ nguyên tên & kiểu biến session hiện có: `$_SESSION['user_id']` (int), `$_SESSION['username']`, `$_SESSION['role']`, `$_SESSION['admin_logged_in']` (bool), `$_SESSION['admin_id']` (int).
- Secret Auth0 chỉ đọc qua `env_value()` từ `.env`/Render, không commit.
- SDK v8 Auth0 API: `new Auth0(array $config)`, `$auth0->login(?string $redirectUrl)`, `$auth0->exchange()`, `$auth0->getCredentials()`, `$auth0->logout(?string $returnTo)`, `$auth0->clear()`.

---

### Task 1: Cài SDK + builder cấu hình Auth0 (thuần)

**Files:**
- Modify: `composer.json`
- Create: `includes/auth0.php`
- Test: `tests/auth0_config_test.php`

**Interfaces:**
- Consumes: `env_value()` (từ `config/bootstrap.php`), `absolute_url()` (từ `config/config.php`).
- Produces:
  - `auth0_config(): array` — trả mảng config cho SDK từ env. Keys: `domain, clientId, clientSecret, cookieSecret, redirectUri, cookieExpires`.
  - `auth0_client(): \Auth0\SDK\Auth0` — singleton dựng từ `auth0_config()`.

- [ ] **Step 1: Thêm dependency**

Sửa `composer.json`, thêm vào `require`:

```json
"auth0/auth0-php": "^8.14"
```

Chạy:

```bash
composer require auth0/auth0-php:^8.14
```

Expected: `vendor/auth0/` xuất hiện, `composer.lock` cập nhật.

- [ ] **Step 2: Viết test thất bại (thuần, không DB)**

Create `tests/auth0_config_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';

// Nạp env giả lập cho builder
putenv('AUTH0_DOMAIN=gaubakery.eu.auth0.com');
putenv('AUTH0_CLIENT_ID=cid123');
putenv('AUTH0_CLIENT_SECRET=secret123');
putenv('AUTH0_COOKIE_SECRET=0123456789abcdef0123456789abcdef');
$_ENV['AUTH0_DOMAIN'] = 'gaubakery.eu.auth0.com';
$_ENV['AUTH0_CLIENT_ID'] = 'cid123';
$_ENV['AUTH0_CLIENT_SECRET'] = 'secret123';
$_ENV['AUTH0_COOKIE_SECRET'] = '0123456789abcdef0123456789abcdef';

require __DIR__ . '/../includes/auth0.php';

$cfg = auth0_config();
assert_same('gaubakery.eu.auth0.com', $cfg['domain'], 'domain tu env');
assert_same('cid123', $cfg['clientId'], 'clientId tu env');
assert_same('secret123', $cfg['clientSecret'], 'clientSecret tu env');
assert_true(strlen($cfg['cookieSecret']) >= 32, 'cookieSecret du dai');
assert_true(str_contains($cfg['redirectUri'], '/cakev0/pages/auth/callback.php'), 'redirectUri co callback path');

echo "auth0_config_test ... ok\n";
```

- [ ] **Step 3: Chạy test — kỳ vọng FAIL**

Run: `php tests/auth0_config_test.php`
Expected: FAIL — `Call to undefined function auth0_config()`.

- [ ] **Step 4: Viết implementation tối thiểu**

Create `includes/auth0.php`:

```php
<?php
require_once __DIR__ . '/../config/config.php';

use Auth0\SDK\Auth0;

if (!function_exists('auth0_config')) {
    function auth0_config(): array
    {
        $callback = env_value('AUTH0_CALLBACK_URL', null);
        if ($callback === null || $callback === '') {
            $callback = absolute_url('pages/auth/callback.php');
        }

        return [
            'domain'        => (string) env_value('AUTH0_DOMAIN', ''),
            'clientId'      => (string) env_value('AUTH0_CLIENT_ID', ''),
            'clientSecret'  => (string) env_value('AUTH0_CLIENT_SECRET', ''),
            'cookieSecret'  => (string) env_value('AUTH0_COOKIE_SECRET', ''),
            'redirectUri'   => $callback,
            'cookieExpires' => 60 * 60 * 24 * 7,
        ];
    }
}

if (!function_exists('auth0_client')) {
    function auth0_client(): Auth0
    {
        static $client = null;
        if ($client instanceof Auth0) {
            return $client;
        }
        $client = new Auth0(auth0_config());
        return $client;
    }
}
```

- [ ] **Step 5: Chạy test — kỳ vọng PASS**

Run: `php tests/auth0_config_test.php`
Expected: PASS — in `auth0_config_test ... ok`.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock includes/auth0.php tests/auth0_config_test.php
git commit -m "feat(auth0): them SDK va builder cau hinh Auth0"
```

---

### Task 2: Migration DB — cột `users.auth0_id` + ensure idempotent

**Files:**
- Create: `database/migrations/2026_08_05_auth0.sql`
- Modify: `config/connect.php` (thêm gọi `ensureAuth0Infrastructure($conn)` cạnh `ensureProductVisibilityInfrastructure()`)
- Create: `includes/auth0_schema.php`
- Test: `tests/auth0_schema_test.php` `[DOCKER]`

**Interfaces:**
- Produces: `ensureAuth0Infrastructure(mysqli $conn): void` — thêm cột `auth0_id VARCHAR(64) NULL UNIQUE` vào `users` nếu chưa có (idempotent).

- [ ] **Step 1: Viết file SQL migration**

Create `database/migrations/2026_08_05_auth0.sql`:

```sql
-- Thêm liên kết Auth0 vào bảng users (mirror danh tinh).
ALTER TABLE `users`
  ADD COLUMN `auth0_id` VARCHAR(64) NULL AFTER `id`,
  ADD UNIQUE KEY `uniq_users_auth0_id` (`auth0_id`);
```

- [ ] **Step 2: Viết test thất bại `[DOCKER]`**

Create `tests/auth0_schema_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../includes/auth0_schema.php';

ensureAuth0Infrastructure($conn);

$res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
assert_true($res && $res->num_rows === 1, 'cot auth0_id ton tai');

// Idempotent: goi lai khong loi
ensureAuth0Infrastructure($conn);
$res2 = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
assert_true($res2 && $res2->num_rows === 1, 'goi lai van 1 cot');

echo "auth0_schema_test ... ok\n";
```

- [ ] **Step 3: Chạy test — kỳ vọng FAIL**

Run: `docker compose exec web php tests/auth0_schema_test.php`
Expected: FAIL — `Call to undefined function ensureAuth0Infrastructure()`.

- [ ] **Step 4: Viết implementation idempotent**

Create `includes/auth0_schema.php`:

```php
<?php
if (!function_exists('ensureAuth0Infrastructure')) {
    function ensureAuth0Infrastructure(mysqli $conn): void
    {
        $col = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
        if ($col && $col->num_rows > 0) {
            return;
        }
        // Cột chưa có: thêm cột + unique key.
        $conn->query(
            "ALTER TABLE `users` "
            . "ADD COLUMN `auth0_id` VARCHAR(64) NULL AFTER `id`, "
            . "ADD UNIQUE KEY `uniq_users_auth0_id` (`auth0_id`)"
        );
    }
}
```

Sửa `config/connect.php`: sau dòng gọi `ensureProductVisibilityInfrastructure(...)`, thêm:

```php
require_once __DIR__ . '/../includes/auth0_schema.php';
ensureAuth0Infrastructure($conn);
```

- [ ] **Step 5: Chạy test — kỳ vọng PASS**

Run: `docker compose exec web php tests/auth0_schema_test.php`
Expected: PASS — in `auth0_schema_test ... ok`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_05_auth0.sql includes/auth0_schema.php config/connect.php tests/auth0_schema_test.php
git commit -m "feat(auth0): them cot users.auth0_id va ensure idempotent"
```

---

### Task 3: Trích danh tính từ claims Auth0 (thuần)

**Files:**
- Create: `includes/auth_bridge.php` (khởi tạo file; task này thêm hàm extract)
- Test: `tests/auth_bridge_identity_test.php`

**Interfaces:**
- Produces: `auth0_extract_identity(array $claims): array` — nhận mảng claims profile Auth0, trả `['auth0_id'=>string,'email'=>string,'username'=>string,'role'=>string]`.
  - `auth0_id` = claim `sub`.
  - `email` = claim `email` (lowercase, trim).
  - `username` = ưu tiên custom claim `https://gaubakery.app/username`, rồi `nickname`, rồi phần trước `@` của email.
  - `role` = custom claim `https://gaubakery.app/role` nếu bằng `admin`, ngược lại `user`.

**Ghi chú cấu hình Auth0 (ngoài code, làm trên Dashboard):** tạo một Login Action gắn 2 custom claim vào id_token: `https://gaubakery.app/role` từ `event.user.app_metadata.role`, và `https://gaubakery.app/username` từ `event.user.user_metadata.username`. Namespace phải khớp hằng trong code.

- [ ] **Step 1: Viết test thất bại (thuần)**

Create `tests/auth_bridge_identity_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/auth_bridge.php';

// User thuong, co custom username claim
$id = auth0_extract_identity([
    'sub' => 'auth0|abc123',
    'email' => 'User@Example.COM',
    'https://gaubakery.app/username' => 'nhatanh',
    'https://gaubakery.app/role' => 'user',
]);
assert_same('auth0|abc123', $id['auth0_id'], 'sub');
assert_same('user@example.com', $id['email'], 'email lowercase');
assert_same('nhatanh', $id['username'], 'username tu custom claim');
assert_same('user', $id['role'], 'role user');

// Admin, khong co username claim -> fallback phan truoc @
$admin = auth0_extract_identity([
    'sub' => 'auth0|adminsub',
    'email' => 'admin@gau.vn',
    'https://gaubakery.app/role' => 'admin',
]);
assert_same('admin', $admin['role'], 'role admin');
assert_same('admin', $admin['username'], 'username fallback local-part');

// Role la la -> ep ve user
$weird = auth0_extract_identity([
    'sub' => 'google-oauth2|9',
    'email' => 'g@gmail.com',
    'https://gaubakery.app/role' => 'superuser',
]);
assert_same('user', $weird['role'], 'role la ep ve user');

echo "auth_bridge_identity_test ... ok\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL**

Run: `php tests/auth_bridge_identity_test.php`
Expected: FAIL — `Call to undefined function auth0_extract_identity()`.

- [ ] **Step 3: Viết implementation tối thiểu**

Create `includes/auth_bridge.php`:

```php
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
            $username = substr($email, 0, strpos($email, '@') ?: strlen($email));
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
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php tests/auth_bridge_identity_test.php`
Expected: PASS — in `auth_bridge_identity_test ... ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/auth_bridge.php tests/auth_bridge_identity_test.php
git commit -m "feat(auth0): trich danh tinh tu claims Auth0"
```

---

### Task 4: Resolve/provision dòng users local (DB)

**Files:**
- Modify: `includes/auth_bridge.php`
- Test: `tests/auth_bridge_resolve_test.php` `[DOCKER]`

**Interfaces:**
- Consumes: `auth0_extract_identity()` (Task 3).
- Produces: `resolve_local_user(mysqli $conn, array $identity): array` — trả dòng users `['id'=>int,'username'=>string,'role'=>string]`.
  - Khớp theo `auth0_id` trước.
  - Không thấy thì khớp theo `email`; nếu thấy, `UPDATE users SET auth0_id = ?` (link tài khoản cũ) và dùng dòng đó.
  - Vẫn không thấy thì `INSERT` dòng mới (`username`, `email`, `auth0_id`, `password=''`).
  - `role` trả về = `$identity['role']` (nguồn sự thật là Auth0, không lưu vào bảng).

- [ ] **Step 1: Viết test thất bại `[DOCKER]`**

Create `tests/auth_bridge_resolve_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../includes/auth_bridge.php';

// Don sach tai khoan test
$conn->query("DELETE FROM users WHERE email IN ('link@test.vn','new@test.vn')");

// 1) Tao user cu (chua co auth0_id), khop theo email -> phai link
$hash = password_hash('x', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
$u = 'linkuser'; $e = 'link@test.vn';
$stmt->bind_param('sss', $u, $hash, $e);
$stmt->execute();
$oldId = (int) $conn->insert_id;
$stmt->close();

$row = resolve_local_user($conn, [
    'auth0_id' => 'auth0|link', 'email' => 'link@test.vn',
    'username' => 'linkuser', 'role' => 'user',
]);
assert_same($oldId, $row['id'], 'khop email tra ve dung dong cu');

$check = $conn->query("SELECT auth0_id FROM users WHERE id = $oldId")->fetch_assoc();
assert_same('auth0|link', $check['auth0_id'], 'auth0_id da duoc link');

// 2) Goi lai -> khop theo auth0_id, khong tao trung
$row2 = resolve_local_user($conn, [
    'auth0_id' => 'auth0|link', 'email' => 'link@test.vn',
    'username' => 'linkuser', 'role' => 'user',
]);
assert_same($oldId, $row2['id'], 'khop auth0_id lan 2');

// 3) User hoan toan moi -> INSERT
$row3 = resolve_local_user($conn, [
    'auth0_id' => 'auth0|new', 'email' => 'new@test.vn',
    'username' => 'newuser', 'role' => 'admin',
]);
assert_true($row3['id'] > 0, 'tao user moi');
assert_same('admin', $row3['role'], 'role admin giu nguyen');

$conn->query("DELETE FROM users WHERE email IN ('link@test.vn','new@test.vn')");
echo "auth_bridge_resolve_test ... ok\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL**

Run: `docker compose exec web php tests/auth_bridge_resolve_test.php`
Expected: FAIL — `Call to undefined function resolve_local_user()`.

- [ ] **Step 3: Viết implementation**

Thêm vào `includes/auth_bridge.php`:

```php
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
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `docker compose exec web php tests/auth_bridge_resolve_test.php`
Expected: PASS — in `auth_bridge_resolve_test ... ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/auth_bridge.php tests/auth_bridge_resolve_test.php
git commit -m "feat(auth0): resolve va provision dong users local theo auth0_id"
```

---

### Task 5: Set session + whitelist redirect (thuần/session)

**Files:**
- Modify: `includes/auth_bridge.php`
- Test: `tests/auth_bridge_session_test.php`

**Interfaces:**
- Consumes: `resolve_local_user()` (Task 4).
- Produces:
  - `apply_session_for_user(array $user): void` — set `$_SESSION['user_id']`, `['username']`, `['role']`; nếu `role==='admin'` set thêm `['admin_logged_in']=true`, `['admin_id']=user_id`; nếu không phải admin thì `unset` cờ admin.
  - `safe_redirect_target(?string $raw, string $fallback): string` — chỉ chấp nhận path nội bộ bắt đầu `/cakev0/`; ngược lại trả `$fallback`.
  - `sync_session_from_auth0(mysqli $conn, array $claims): array` — nối `auth0_extract_identity` → `resolve_local_user` → `apply_session_for_user`, trả `$user`.

- [ ] **Step 1: Viết test thất bại (thuần, không DB cho phần session/redirect)**

Create `tests/auth_bridge_session_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/auth_bridge.php';

$_SESSION = [];
apply_session_for_user(['id' => 7, 'username' => 'nhatanh', 'role' => 'user']);
assert_same(7, $_SESSION['user_id'], 'user_id set');
assert_same('nhatanh', $_SESSION['username'], 'username set');
assert_same('user', $_SESSION['role'], 'role set');
assert_true(!isset($_SESSION['admin_logged_in']), 'user thuong khong co co admin');

$_SESSION = [];
apply_session_for_user(['id' => 1, 'username' => 'admin', 'role' => 'admin']);
assert_true($_SESSION['admin_logged_in'] === true, 'admin co co admin_logged_in');
assert_same(1, $_SESSION['admin_id'], 'admin_id set');

// Whitelist redirect
assert_same('/cakev0/pages/account.php', safe_redirect_target('/cakev0/pages/account.php', '/cakev0/index.php'), 'path noi bo ok');
assert_same('/cakev0/index.php', safe_redirect_target('https://evil.com', '/cakev0/index.php'), 'chan URL ngoai');
assert_same('/cakev0/index.php', safe_redirect_target('//evil.com', '/cakev0/index.php'), 'chan protocol-relative');
assert_same('/cakev0/index.php', safe_redirect_target(null, '/cakev0/index.php'), 'null -> fallback');

echo "auth_bridge_session_test ... ok\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL**

Run: `php tests/auth_bridge_session_test.php`
Expected: FAIL — `Call to undefined function apply_session_for_user()`.

- [ ] **Step 3: Viết implementation**

Thêm vào `includes/auth_bridge.php`:

```php
if (!function_exists('apply_session_for_user')) {
    function apply_session_for_user(array $user): void
    {
        $_SESSION['user_id']  = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role']     = $user['role'] === 'admin' ? 'admin' : 'user';

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
        // Chi cho path noi bo tuyet doi duoi /cakev0/, chan protocol-relative (//) va URL ngoai.
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
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php tests/auth_bridge_session_test.php`
Expected: PASS — in `auth_bridge_session_test ... ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/auth_bridge.php tests/auth_bridge_session_test.php
git commit -m "feat(auth0): set session buoc cau noi va whitelist redirect"
```

---

### Task 6: Trang login / callback / logout

**Files:**
- Create: `pages/auth/login.php`
- Create: `pages/auth/callback.php`
- Create: `pages/auth/logout.php`
- Test: kiểm thử thủ công e2e trên staging Auth0 (không unit test — chỉ orchestration SDK).

**Interfaces:**
- Consumes: `auth0_client()` (Task 1), `sync_session_from_auth0()` (Task 5), `safe_redirect_target()` (Task 5), `base_url()`.

- [ ] **Step 1: Viết `pages/auth/login.php`**

```php
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth0.php';

$auth0 = auth0_client();

// Giu diem den de quay lai sau khi dang nhap
$returnTo = safe_redirect_target($_GET['return'] ?? null, base_url('index.php'));
$_SESSION['auth_return_to'] = $returnTo;

// screen_hint=signup cho luong dang ky
$params = [];
if (($_GET['mode'] ?? '') === 'signup') {
    $params['screen_hint'] = 'signup';
}

header('Location: ' . $auth0->login(null, $params));
exit;
```

Lưu ý: `safe_redirect_target()` nằm trong `auth_bridge.php`; thêm `require_once __DIR__ . '/../../includes/auth_bridge.php';` ở đầu file này.

- [ ] **Step 2: Viết `pages/auth/callback.php`**

```php
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../includes/auth_bridge.php';
require_once __DIR__ . '/../../config/connect.php';

$auth0 = auth0_client();

try {
    $auth0->exchange();
} catch (Throwable $e) {
    header('Location: ' . base_url('index.php?toast=auth_error'));
    exit;
}

$credentials = $auth0->getCredentials();
if ($credentials === null || $credentials->user === null) {
    header('Location: ' . base_url('index.php?toast=auth_error'));
    exit;
}

$user = sync_session_from_auth0($conn, (array) $credentials->user);

// Ghi login_logs (giu audit noi bo)
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
if ($user['role'] === 'admin') { $target = base_url('admin/index.php'); }
unset($_SESSION['auth_return_to']);

header('Location: ' . $target);
exit;
```

- [ ] **Step 3: Viết `pages/auth/logout.php`**

```php
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../config/connect.php';

// Ghi log dang xuat truoc khi xoa session
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'logout')");
    if ($stmt) {
        $stmt->bind_param('is', $uid, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

$auth0 = auth0_client();
$returnTo = env_value('AUTH0_LOGOUT_RETURN_URL', null) ?: absolute_url('index.php');

// Xoa session app
session_unset();
session_destroy();

header('Location: ' . $auth0->logout($returnTo));
exit;
```

- [ ] **Step 4: Kiểm thử thủ công (staging)**

Cấu hình Auth0 tenant (Dashboard): Allowed Callback URLs thêm `http://localhost:8080/cakev0/pages/auth/callback.php`; Allowed Logout URLs thêm `http://localhost:8080/cakev0/index.php`. Set env local (`.env.local`) đủ 5 biến `AUTH0_*`.

Chạy `docker compose up`, mở `http://localhost:8080/cakev0/pages/auth/login.php`:
- Redirect sang Universal Login → đăng nhập user mẫu (đã import) → quay về `index.php`, header hiện đã đăng nhập.
- `pages/auth/logout.php` → đăng xuất, quay về trang chủ.
- Đăng nhập admin mẫu → vào `admin/index.php` không lỗi guard.

Expected: cả 3 luồng chạy đúng, không trắng trang, `login_logs` có dòng mới.

- [ ] **Step 5: Commit**

```bash
git add pages/auth/login.php pages/auth/callback.php pages/auth/logout.php
git commit -m "feat(auth0): trang login/callback/logout dung Universal Login"
```

---

### Task 7: Nối bootstrap, header, admin guard; nghỉ trang cũ

**Files:**
- Modify: `includes/header.php` (nút Đăng nhập/Đăng xuất trỏ luồng mới)
- Modify: `admin/bootstrap.php` (nạp session bridge nếu chưa có; guard giữ nguyên tên biến)
- Modify: `pages/login.php`, `pages/register.php`, `pages/logout.php`, `pages/verify-registration.php`, `pages/forgot-password.php` (rút gọn thành redirect stub)
- Test: `tests/auth_legacy_redirect_test.php` (thuần — kiểm tra stub chứa đúng đích redirect)

**Interfaces:**
- Consumes: `base_url()`.

- [ ] **Step 1: Viết test thất bại cho stub redirect (thuần)**

Create `tests/auth_legacy_redirect_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';

$map = [
    'pages/login.php'    => 'pages/auth/login.php',
    'pages/register.php' => 'mode=signup',
    'pages/logout.php'   => 'pages/auth/logout.php',
];
foreach ($map as $file => $needle) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    assert_true(str_contains($src, $needle), "$file redirect toi $needle");
    assert_true(!str_contains($src, 'password_verify'), "$file khong con verify mat khau");
}
echo "auth_legacy_redirect_test ... ok\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL**

Run: `php tests/auth_legacy_redirect_test.php`
Expected: FAIL — `pages/login.php` vẫn chứa `password_verify`.

- [ ] **Step 3: Rút gọn `pages/login.php` thành stub**

Thay TOÀN BỘ nội dung `pages/login.php` bằng:

```php
<?php
require_once __DIR__ . '/../config/config.php';
$return = isset($_GET['return']) ? '?return=' . rawurlencode((string) $_GET['return']) : '';
header('Location: ' . base_url('pages/auth/login.php') . $return);
exit;
```

Thay TOÀN BỘ `pages/register.php`:

```php
<?php
require_once __DIR__ . '/../config/config.php';
header('Location: ' . base_url('pages/auth/login.php?mode=signup'));
exit;
```

Thay TOÀN BỘ `pages/logout.php`:

```php
<?php
require_once __DIR__ . '/../config/config.php';
header('Location: ' . base_url('pages/auth/logout.php'));
exit;
```

Thay TOÀN BỘ `pages/verify-registration.php` và `pages/forgot-password.php` (Auth0 lo verify + reset):

```php
<?php
require_once __DIR__ . '/../config/config.php';
header('Location: ' . base_url('pages/auth/login.php'));
exit;
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php tests/auth_legacy_redirect_test.php`
Expected: PASS — in `auth_legacy_redirect_test ... ok`.

- [ ] **Step 5: Sửa header + admin guard**

Trong `includes/header.php`, đổi link đăng nhập trỏ `base_url('pages/auth/login.php')`, link đăng xuất trỏ `base_url('pages/auth/logout.php')`. (Tìm anchor hiện trỏ `pages/login.php`/`pages/logout.php` và thay href.)

Trong `admin/bootstrap.php`, ngay trước guard `if (!isset($_SESSION['admin_logged_in']) ...)`, đảm bảo session đã nạp từ Auth0 nếu app chưa set (phòng trường hợp truy cập admin trực tiếp sau khi login):

```php
// Neu co phien Auth0 nhung session app chua co, dong bo lai
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/auth0.php';
    require_once __DIR__ . '/../includes/auth_bridge.php';
    $creds = auth0_client()->getCredentials();
    if ($creds !== null && $creds->user !== null) {
        sync_session_from_auth0($conn, (array) $creds->user);
    }
}
```

Lưu ý: guard tên biến `admin_logged_in` + `role==='admin'` giữ nguyên — không đổi.

- [ ] **Step 6: Kiểm thử thủ công guard admin + commit**

`docker compose up`, đăng nhập admin mẫu qua Auth0, vào `admin/index.php` — vào được. Đăng nhập user thường vào `admin/index.php` — bị chặn về login.

```bash
git add includes/header.php admin/bootstrap.php pages/login.php pages/register.php pages/logout.php pages/verify-registration.php pages/forgot-password.php tests/auth_legacy_redirect_test.php
git commit -m "feat(auth0): noi header/guard va nghi trang xac thuc cu"
```

---

### Task 8: Script bulk-import bcrypt lên Auth0

**Files:**
- Create: `scripts/migrate_users_to_auth0.php`
- Test: `tests/auth_migration_payload_test.php` (thuần — kiểm payload builder)

**Interfaces:**
- Produces: `build_import_payload(array $row, bool $isAdmin): array` — dựng body cho Management API `POST /api/v2/users` từ một dòng DB.
  - `connection = 'Username-Password-Authentication'`, `email`, `email_verified=true`.
  - `custom_password_hash.algorithm='bcrypt'`, `custom_password_hash.hash.value = $row['password']`.
  - `user_metadata.username`, `user_metadata.phone`.
  - Nếu `$isAdmin`, thêm `app_metadata.role='admin'`.

- [ ] **Step 1: Viết test thất bại (thuần)**

Create `tests/auth_migration_payload_test.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../scripts/migrate_users_to_auth0.php';

$row = [
    'email' => 'u@test.vn', 'username' => 'nhatanh',
    'password' => '$2y$10$abcdefghijklmnopqrstuv', 'phone' => '0900',
];

$p = build_import_payload($row, false);
assert_same('Username-Password-Authentication', $p['connection'], 'connection');
assert_same('u@test.vn', $p['email'], 'email');
assert_true($p['email_verified'] === true, 'email_verified');
assert_same('bcrypt', $p['custom_password_hash']['algorithm'], 'algo bcrypt');
assert_same('$2y$10$abcdefghijklmnopqrstuv', $p['custom_password_hash']['hash']['value'], 'hash nguyen ven');
assert_same('nhatanh', $p['user_metadata']['username'], 'username metadata');
assert_true(!isset($p['app_metadata']), 'user thuong khong co app_metadata role');

$pa = build_import_payload($row, true);
assert_same('admin', $pa['app_metadata']['role'], 'admin co role metadata');

echo "auth_migration_payload_test ... ok\n";
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL**

Run: `php tests/auth_migration_payload_test.php`
Expected: FAIL — `Call to undefined function build_import_payload()`.

- [ ] **Step 3: Viết script + payload builder**

Create `scripts/migrate_users_to_auth0.php`:

```php
<?php
// Chay 1 lan, thu cong:
//   docker compose exec web php scripts/migrate_users_to_auth0.php
// Yeu cau env M2M: AUTH0_DOMAIN, AUTH0_MGMT_CLIENT_ID, AUTH0_MGMT_CLIENT_SECRET

if (!function_exists('build_import_payload')) {
    function build_import_payload(array $row, bool $isAdmin): array
    {
        $payload = [
            'connection'    => 'Username-Password-Authentication',
            'email'         => (string) $row['email'],
            'email_verified' => true,
            'custom_password_hash' => [
                'algorithm' => 'bcrypt',
                'hash' => ['value' => (string) $row['password']],
            ],
            'user_metadata' => [
                'username' => (string) ($row['username'] ?? ''),
                'phone'    => (string) ($row['phone'] ?? ''),
            ],
        ];
        if ($isAdmin) {
            $payload['app_metadata'] = ['role' => 'admin'];
        }
        return $payload;
    }
}

// --- Phan runtime (bo qua khi include tu test) ---
if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    require_once __DIR__ . '/../config/connect.php';

    $domain = (string) env_value('AUTH0_DOMAIN', '');
    $mgmtId = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
    $mgmtSecret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');

    // 1) Lay Management API token (client credentials)
    $tokenResp = json_decode((string) file_get_contents(
        "https://{$domain}/oauth/token",
        false,
        stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $mgmtId,
                'client_secret' => $mgmtSecret,
                'audience' => "https://{$domain}/api/v2/",
            ]),
            'ignore_errors' => true,
        ]])
    ), true);
    $mgmtToken = (string) ($tokenResp['access_token'] ?? '');
    if ($mgmtToken === '') { fwrite(STDERR, "Khong lay duoc Management token\n"); exit(1); }

    $importOne = function (array $row, bool $isAdmin) use ($domain, $mgmtToken, $conn): void {
        if (!empty($row['auth0_id'])) {
            echo "Bo qua (da co auth0_id): {$row['email']}\n";
            return;
        }
        $body = json_encode(build_import_payload($row, $isAdmin));
        $resp = json_decode((string) file_get_contents(
            "https://{$domain}/api/v2/users",
            false,
            stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$mgmtToken}\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ]])
        ), true);
        $sub = (string) ($resp['user_id'] ?? '');
        if ($sub === '') {
            fwrite(STDERR, "Loi import {$row['email']}: " . json_encode($resp) . "\n");
            return;
        }
        // Ghi auth0_id nguoc lai (chi cho bang users; admin map o buoc rieng)
        if (isset($row['id'])) {
            $stmt = $conn->prepare("UPDATE users SET auth0_id = ? WHERE id = ?");
            $id = (int) $row['id'];
            $stmt->bind_param('si', $sub, $id);
            $stmt->execute();
            $stmt->close();
        }
        echo "Import OK {$row['email']} -> {$sub}\n";
    };

    // 2) Users thuong
    $res = $conn->query("SELECT id, username, email, password, phone, auth0_id FROM users");
    while ($row = $res->fetch_assoc()) { $importOne($row, false); }

    // 3) Admins (bang admins khong co email -> dung username lam email neu can, hoac bo qua neu trung email user)
    $res = $conn->query("SELECT id, username, password FROM admins");
    while ($row = $res->fetch_assoc()) {
        // Admin dung email theo quy uoc; sua thu cong neu can
        $row['email'] = $row['username'] . '@gaubakery.vn';
        $row['phone'] = '';
        unset($row['id']); // khong ghi auth0_id vao bang users
        $importOne($row, true);
    }

    echo "Hoan tat migrate.\n";
}
```

Lưu ý: bảng `admins` không có cột email — script dùng quy ước `<username>@gaubakery.vn`. Nếu admin thực tế trùng email với một user, xử lý thủ công (gán `app_metadata.role=admin` cho user đó trên Dashboard) thay vì tạo tài khoản admin riêng.

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php tests/auth_migration_payload_test.php`
Expected: PASS — in `auth_migration_payload_test ... ok`.

- [ ] **Step 5: Chạy migrate thật (staging, thủ công) + commit**

Set env M2M, rồi:

```bash
docker compose exec web php scripts/migrate_users_to_auth0.php
```

Expected: mỗi dòng in `Import OK ... -> auth0|...`; `users.auth0_id` được điền; đăng nhập thử 1 tài khoản bằng mật khẩu cũ trên Universal Login thành công.

```bash
git add scripts/migrate_users_to_auth0.php tests/auth_migration_payload_test.php
git commit -m "feat(auth0): script bulk-import bcrypt len Auth0"
```

---

## Self-Review

**Spec coverage:**
- Mục tiêu giữ 23 file → Task 5 (`apply_session_for_user` giữ tên biến) + Task 4 (int id). ✔
- Auth0 authority + Universal Login → Task 1, Task 6. ✔
- Admin role metadata → Task 3 (extract role), Task 5 (set cờ admin), Task 7 (guard), Task 8 (import role). ✔
- Email/password migrate + Google + MFA → Task 8 (bcrypt import), Google/MFA là cấu hình Dashboard (ghi chú Task 6). ✔
- Bulk import bcrypt → Task 8. ✔
- `users.auth0_id` mirror → Task 2. ✔
- Bảng nghỉ (`admins`/`login_tokens`/`pending_registrations`) → Task 7 (stub trang), không xoá schema (đúng spec: dọn dẹp tách sau). ✔
- Chat HMAC không đổi → không có task sửa `api/chat/*` (đúng chủ ý). ✔
- Env vars → Task 1 (config) + ghi chú Task 6/8. ✔
- Bảo mật: SDK lo state/nonce, whitelist redirect (Task 5 `safe_redirect_target`), bỏ sentinel admin (Task 7 stub login.php xoá `password_verify`). ✔
- Rollback: bảng/hash giữ nguyên (Task 2 chỉ thêm cột; Task 7 không drop). ✔

**Placeholder scan:** không có TODO/TBD; mọi step có code hoặc lệnh cụ thể. ✔

**Type consistency:** `auth0_extract_identity` (Task 3) → keys `auth0_id/email/username/role` dùng nhất quán ở `resolve_local_user` (Task 4) và `apply_session_for_user` nhận `id/username/role` (Task 5). `sync_session_from_auth0(mysqli,array)` chữ ký khớp giữa Task 5 định nghĩa và Task 6/7 gọi. `build_import_payload(array,bool)` khớp Task 8. ✔

**Gap:** Google/MFA là cấu hình tenant (không phải code) — đã nêu là ghi chú Dashboard, không cần task code riêng. Chấp nhận.
