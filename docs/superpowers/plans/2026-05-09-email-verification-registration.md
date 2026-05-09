# Email Verification Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace direct account creation with a 24-hour email verification flow that creates `users` rows only after link verification, and enforce the same strong password policy across registration, forgot-password, and account password change flows.

**Architecture:** Add small shared PHP helpers for password validation and registration email content, persist unverified signups in a new `pending_registrations` table, and add a dedicated `verify-registration.php` endpoint that promotes pending rows into `users`. Keep login semantics simple by leaving `pages/login.php` focused on the `users` table, and use lightweight CLI PHP tests for pure helpers plus syntax/manual verification for the legacy page flows.

**Tech Stack:** PHP 8-style procedural app, MySQL via `mysqli`, existing `config/bootstrap.php` and `config/connect.php`, Gmail sending via `includes/mailer.php`, plain CLI PHP test scripts under `tests/`.

---

## File Structure

- `includes/auth_helpers.php`
  - shared password policy validation
  - random verification token generation
- `includes/registration_helpers.php`
  - verification URL builder
  - registration verification email subject/body builder
  - small pending-registration helper functions that are pure or near-pure
- `pages/register.php`
  - stop inserting into `users`
  - create/update `pending_registrations`
  - send verification email
  - redirect to home as guest
- `pages/verify-registration.php`
  - consume verification token
  - create verified `users` record
  - delete pending row
  - redirect to login with toast
- `pages/forgot-password.php`
  - use shared password validation
  - update form hint/minlength
- `pages/account.php`
  - use shared password validation
  - update form hint/minlength
- `database/migrations/2026-05-09_add_pending_registrations.sql`
  - create pending table
  - add unique index to `users.email`
- `database/banh_store.sql`
  - mirror schema changes for local rebuilds
- `tests/bootstrap.php`
  - tiny assertion helpers for CLI tests
- `tests/auth_helpers_test.php`
  - password-policy and token-generation tests
- `tests/registration_helpers_test.php`
  - verification URL/email content tests
- `README.md`
  - short note about registration verification and password policy

### Task 1: Add a Minimal CLI Test Harness and Auth Helper Tests

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/auth_helpers_test.php`
- Test: `tests/auth_helpers_test.php`

- [ ] **Step 1: Write the failing test harness**

```php
<?php
// tests/bootstrap.php
require_once __DIR__ . '/../config/bootstrap.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "Assertion failed: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
}
```

- [ ] **Step 2: Write the failing auth helper test**

```php
<?php
// tests/auth_helpers_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

assert_same(
    'Mật khẩu phải có ít nhất 12 ký tự.',
    validate_password_strength('Short1!'),
    'reject passwords shorter than 12 characters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ in hoa.',
    validate_password_strength('lowercase123!'),
    'reject passwords without uppercase letters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ thường.',
    validate_password_strength('UPPERCASE123!'),
    'reject passwords without lowercase letters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ số.',
    validate_password_strength('NoDigitsHere!'),
    'reject passwords without digits'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 ký tự đặc biệt.',
    validate_password_strength('NoSpecial1234'),
    'reject passwords without special characters'
);

assert_same(
    null,
    validate_password_strength('ValidPassword123!'),
    'accept strong passwords'
);

$tokenA = generate_verification_token();
$tokenB = generate_verification_token();

assert_true(strlen($tokenA) === 64, 'token length should be 64 hex chars');
assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $tokenA), 'token should be lowercase hex');
assert_true($tokenA !== $tokenB, 'generated tokens should differ');

echo "auth helpers ok\n";
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php tests/auth_helpers_test.php`  
Expected: FAIL with `Failed opening required .../includes/auth_helpers.php`

- [ ] **Step 4: Commit the failing test scaffold**

```bash
git add tests/bootstrap.php tests/auth_helpers_test.php
git commit -m "test: add auth helper CLI tests"
```

### Task 2: Implement Shared Auth Helpers

**Files:**
- Create: `includes/auth_helpers.php`
- Test: `tests/auth_helpers_test.php`

- [ ] **Step 1: Write the minimal helper implementation**

```php
<?php
// includes/auth_helpers.php

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 12) {
        return 'Mật khẩu phải có ít nhất 12 ký tự.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ in hoa.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ thường.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ số.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 ký tự đặc biệt.';
    }

    return null;
}

function generate_verification_token(): string
{
    return bin2hex(random_bytes(32));
}
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `php tests/auth_helpers_test.php`  
Expected: PASS with output `auth helpers ok`

- [ ] **Step 3: Lint the new helper**

Run: `php -l includes/auth_helpers.php`  
Expected: `No syntax errors detected in includes/auth_helpers.php`

- [ ] **Step 4: Commit**

```bash
git add includes/auth_helpers.php tests/auth_helpers_test.php
git commit -m "feat: add shared password validation helpers"
```

### Task 3: Add Registration Helper Tests and Email Builder Helpers

**Files:**
- Create: `tests/registration_helpers_test.php`
- Create: `includes/registration_helpers.php`
- Test: `tests/registration_helpers_test.php`

- [ ] **Step 1: Write the failing registration helper test**

```php
<?php
// tests/registration_helpers_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/registration_helpers.php';

$url = build_registration_verification_url('abc123');
assert_true(str_contains($url, 'verify-registration.php?token=abc123'), 'verification URL should include token');

$mail = build_registration_verification_mail('thanhnhan', 'https://example.test/verify?token=abc123');
assert_same('Xác thực tài khoản Gấu Bakery', $mail['subject'], 'mail subject should match');
assert_true(str_contains($mail['body'], 'thanhnhan'), 'mail body should include username');
assert_true(str_contains($mail['body'], 'https://example.test/verify?token=abc123'), 'mail body should include link');
assert_true(str_contains($mail['body'], '24 giờ'), 'mail body should mention expiry');

echo "registration helpers ok\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/registration_helpers_test.php`  
Expected: FAIL with `Failed opening required .../includes/registration_helpers.php`

- [ ] **Step 3: Implement the minimal registration helpers**

```php
<?php
// includes/registration_helpers.php
require_once __DIR__ . '/../config/config.php';

function build_registration_verification_url(string $token): string
{
    return base_url('pages/verify-registration.php?token=' . urlencode($token));
}

function build_registration_verification_mail(string $username, string $verificationUrl): array
{
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    return [
        'subject' => 'Xác thực tài khoản Gấu Bakery',
        'body' => "
            <h2>Xác thực tài khoản</h2>
            <p>Chào <strong>{$safeUsername}</strong>,</p>
            <p>Vui lòng xác thực tài khoản của bạn bằng liên kết bên dưới:</p>
            <p><a href=\"{$safeUrl}\">Xác thực tài khoản</a></p>
            <p>Liên kết này có hiệu lực trong 24 giờ.</p>
            <p>Nếu bạn không thực hiện đăng ký, hãy bỏ qua email này.</p>
        ",
    ];
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/registration_helpers_test.php`  
Expected: PASS with output `registration helpers ok`

- [ ] **Step 5: Lint the helper**

Run: `php -l includes/registration_helpers.php`  
Expected: `No syntax errors detected in includes/registration_helpers.php`

- [ ] **Step 6: Commit**

```bash
git add includes/registration_helpers.php tests/registration_helpers_test.php
git commit -m "feat: add registration verification mail helpers"
```

### Task 4: Add Database Migration and Update the Schema Dump

**Files:**
- Create: `database/migrations/2026-05-09_add_pending_registrations.sql`
- Modify: `database/banh_store.sql`
- Test: `database/migrations/2026-05-09_add_pending_registrations.sql`

- [ ] **Step 1: Write the migration**

```sql
CREATE TABLE IF NOT EXISTS `pending_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pending_registrations_username` (`username`),
  UNIQUE KEY `uniq_pending_registrations_email` (`email`),
  UNIQUE KEY `uniq_pending_registrations_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `users`
  MODIFY `email` varchar(255) NOT NULL,
  ADD UNIQUE KEY `uniq_users_email` (`email`);
```

- [ ] **Step 2: Mirror the schema in `database/banh_store.sql`**

```sql
CREATE TABLE `pending_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pending_registrations_username` (`username`),
  UNIQUE KEY `uniq_pending_registrations_email` (`email`),
  UNIQUE KEY `uniq_pending_registrations_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- [ ] **Step 3: Verify the schema changes are present**

Run: `rg -n "pending_registrations|uniq_users_email" database/migrations/2026-05-09_add_pending_registrations.sql database/banh_store.sql`  
Expected: matches for the new table, token unique index, and `uniq_users_email`

- [ ] **Step 4: Add an operator note about duplicates before prod migration**

```sql
-- Before applying the unique email index in production, verify there are no
-- duplicate email values in the live users table.
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026-05-09_add_pending_registrations.sql database/banh_store.sql
git commit -m "feat: add pending registration schema"
```

### Task 5: Rework `pages/register.php` to Persist Pending Registrations and Send Verification Email

**Files:**
- Modify: `pages/register.php`
- Modify: `includes/auth_helpers.php`
- Modify: `includes/registration_helpers.php`
- Test: `pages/register.php`

- [ ] **Step 1: Include the shared helpers at the top of `pages/register.php`**

```php
require_once '../config/config.php';
require_once '../config/connect.php';
require_once '../includes/mailer.php';
require_once '../includes/auth_helpers.php';
require_once '../includes/registration_helpers.php';
```

- [ ] **Step 2: Replace the weak password check with shared validation**

```php
$passwordError = validate_password_strength($password);

if (!$email) {
    $error_message = 'Email không hợp lệ!';
} elseif ($passwordError !== null) {
    $error_message = $passwordError;
}
```

- [ ] **Step 3: Replace direct user insertion with pending-registration persistence**

```php
$check = $conn->prepare(
    'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
);
$check->bind_param('ss', $username, $email);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    $error_message = 'Tên đăng nhập hoặc email đã tồn tại!';
} else {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationToken = generate_verification_token();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $pending = $conn->prepare(
        'SELECT id FROM pending_registrations WHERE username = ? OR email = ? LIMIT 1'
    );
    $pending->bind_param('ss', $username, $email);
    $pending->execute();
    $pendingRow = $pending->get_result()->fetch_assoc();
    $pending->close();

    if ($pendingRow) {
        $stmt = $conn->prepare(
            'UPDATE pending_registrations
             SET username = ?, email = ?, password_hash = ?, verification_token = ?, expires_at = ?, created_at = NOW()
             WHERE id = ?'
        );
        $stmt->bind_param('sssssi', $username, $email, $passwordHash, $verificationToken, $expiresAt, $pendingRow['id']);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO pending_registrations (username, email, password_hash, verification_token, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $username, $email, $passwordHash, $verificationToken, $expiresAt);
    }
}
```

- [ ] **Step 4: Send the verification email and redirect home as guest**

```php
$verificationUrl = build_registration_verification_url($verificationToken);
$mail = build_registration_verification_mail($username, $verificationUrl);

if (!$stmt->execute()) {
    $error_message = 'Không thể lưu yêu cầu đăng ký. Vui lòng thử lại.';
} elseif (!send_custom_mail($email, $mail['subject'], $mail['body'])) {
    $error_message = 'Không thể gửi email xác thực. Vui lòng thử lại.';
} else {
    $_SESSION['toast'] = [
        'msg' => 'Đăng ký gần xong. Vui lòng kiểm tra email để xác thực tài khoản trong 24 giờ.',
        'type' => 'success',
    ];
    header('Location: ' . base_url('index.php'));
    exit;
}
```

- [ ] **Step 5: Update the form hint and client-side length floor**

```html
<input
    type="password"
    name="password"
    class="form-control"
    placeholder="Tối thiểu 12 ký tự, gồm hoa, thường, số và ký tự đặc biệt"
    minlength="12"
    required
>
<small class="text-muted">Mật khẩu phải có ít nhất 12 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</small>
```

- [ ] **Step 6: Lint the page**

Run: `php -l pages/register.php`  
Expected: `No syntax errors detected in pages/register.php`

- [ ] **Step 7: Manual verification**

Run the app and verify:
- submit with `weakpass` -> error message from `validate_password_strength()`
- submit with a strong password -> redirected to home page without login session
- inspect DB -> row exists in `pending_registrations`, none in `users`

- [ ] **Step 8: Commit**

```bash
git add pages/register.php
git commit -m "feat: stage new registrations before email verification"
```

### Task 6: Add `pages/verify-registration.php`

**Files:**
- Create: `pages/verify-registration.php`
- Test: `pages/verify-registration.php`

- [ ] **Step 1: Write the verification endpoint**

```php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../config/connect.php';

$token = trim((string) ($_GET['token'] ?? ''));
$message = '';
$type = 'error';

if ($token === '') {
    $message = 'Liên kết xác thực không hợp lệ.';
} else {
    $stmt = $conn->prepare(
        'SELECT * FROM pending_registrations WHERE verification_token = ? LIMIT 1'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pending) {
        $message = 'Liên kết xác thực không hợp lệ hoặc đã được sử dụng.';
    } elseif (strtotime((string) $pending['expires_at']) < time()) {
        $message = 'Liên kết xác thực đã hết hạn. Vui lòng đăng ký lại.';
    } else {
        $conflict = $conn->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $conflict->bind_param('ss', $pending['username'], $pending['email']);
        $conflict->execute();

        if ($conflict->get_result()->num_rows > 0) {
            $message = 'Tên đăng nhập hoặc email đã được sử dụng. Vui lòng đăng ký lại.';
        } else {
            $insert = $conn->prepare(
                'INSERT INTO users (username, password, email) VALUES (?, ?, ?)'
            );
            $insert->bind_param('sss', $pending['username'], $pending['password_hash'], $pending['email']);

            if ($insert->execute()) {
                $delete = $conn->prepare('DELETE FROM pending_registrations WHERE id = ?');
                $delete->bind_param('i', $pending['id']);
                $delete->execute();
                $delete->close();

                $_SESSION['toast'] = [
                    'msg' => 'Xác thực email thành công. Bạn có thể đăng nhập ngay bây giờ.',
                    'type' => 'success',
                ];
                header('Location: ' . base_url('pages/login.php'));
                exit;
            }

            $message = 'Không thể tạo tài khoản. Vui lòng thử lại.';
        }

        $conflict->close();
    }
}
```

- [ ] **Step 2: Add a minimal failure/success view shell**

```php
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực tài khoản</title>
</head>
<body>
    <main>
        <h1>Xác thực tài khoản</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="<?= htmlspecialchars(base_url('pages/register.php'), ENT_QUOTES, 'UTF-8') ?>">Quay lại đăng ký</a></p>
        <p><a href="<?= htmlspecialchars(base_url('pages/login.php'), ENT_QUOTES, 'UTF-8') ?>">Đến trang đăng nhập</a></p>
    </main>
</body>
</html>
```

- [ ] **Step 3: Lint the endpoint**

Run: `php -l pages/verify-registration.php`  
Expected: `No syntax errors detected in pages/verify-registration.php`

- [ ] **Step 4: Manual verification**

Verify all four cases:
- valid token -> creates `users` row, deletes `pending_registrations` row, redirects to login
- invalid token -> shows invalid/used message
- expired token -> shows expiry message
- reused token -> shows invalid/used message

- [ ] **Step 5: Commit**

```bash
git add pages/verify-registration.php
git commit -m "feat: add registration verification endpoint"
```

### Task 7: Apply the Shared Password Policy to Forgot-Password and Account Password Change

**Files:**
- Modify: `pages/forgot-password.php`
- Modify: `pages/account.php`
- Modify: `tests/auth_helpers_test.php`
- Test: `pages/forgot-password.php`
- Test: `pages/account.php`

- [ ] **Step 1: Include the auth helper in both pages**

```php
require_once '../includes/auth_helpers.php';
```

- [ ] **Step 2: Replace the old length-only checks**

```php
$passwordError = validate_password_strength($new_password_raw);

if ($email_or_username === '' || $new_password_raw === '' || $confirm_password === '') {
    $message = 'Vui lòng nhập đầy đủ thông tin.';
    $message_class = 'error';
} elseif ($passwordError !== null) {
    $message = $passwordError;
    $message_class = 'error';
} elseif ($new_password_raw !== $confirm_password) {
    $message = 'Mật khẩu xác nhận không khớp.';
    $message_class = 'error';
}
```

```php
$passwordError = validate_password_strength($new_pass);

if (!password_verify($old_pass, $user['password'])) {
    $error = 'Mật khẩu hiện tại không đúng.';
} elseif ($new_pass !== $confirm_pass) {
    $error = 'Mật khẩu xác nhận không trùng khớp.';
} elseif ($passwordError !== null) {
    $error = $passwordError;
}
```

- [ ] **Step 3: Update both password inputs and helper copy**

```html
<input type="password" name="new_password" placeholder="Tối thiểu 12 ký tự" minlength="12" required>
<small>Mật khẩu phải có chữ hoa, chữ thường, số và ký tự đặc biệt.</small>
```

- [ ] **Step 4: Lint both pages**

Run: `php -l pages/forgot-password.php`  
Expected: `No syntax errors detected in pages/forgot-password.php`

Run: `php -l pages/account.php`  
Expected: `No syntax errors detected in pages/account.php`

- [ ] **Step 5: Manual verification**

Check both screens with:
- `weakpassword`
- `NoSpecial1234`
- `nouppercase123!`
- `NOLOWERCASE123!`
- `StrongPass123!`

Expected:
- the first four are rejected with rule-specific messages
- the last one is accepted and continues the existing flow

- [ ] **Step 6: Commit**

```bash
git add pages/forgot-password.php pages/account.php
git commit -m "feat: enforce strong passwords across reset flows"
```

### Task 8: Final Schema, Flow, and Documentation Verification

**Files:**
- Modify: `README.md`
- Test: `tests/auth_helpers_test.php`
- Test: `tests/registration_helpers_test.php`

- [ ] **Step 1: Update the README**

```md
- Đăng ký tài khoản qua email xác thực; tài khoản chỉ được tạo sau khi người dùng bấm liên kết xác thực trong vòng 24 giờ.
- Mật khẩu phải có tối thiểu 12 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt.
```

- [ ] **Step 2: Run the helper test suite**

Run: `php tests/auth_helpers_test.php`  
Expected: `auth helpers ok`

Run: `php tests/registration_helpers_test.php`  
Expected: `registration helpers ok`

- [ ] **Step 3: Lint all changed PHP files**

Run:

```bash
php -l includes/auth_helpers.php
php -l includes/registration_helpers.php
php -l pages/register.php
php -l pages/verify-registration.php
php -l pages/forgot-password.php
php -l pages/account.php
```

Expected: all files report `No syntax errors detected`

- [ ] **Step 4: Do the end-to-end manual pass**

Verify in order:
- strong registration creates pending row and sends mail
- home page load after registration shows guest behavior
- verification link creates a real account
- login works only after verification
- forgot-password rejects weak passwords
- account password change rejects weak passwords

- [ ] **Step 5: Commit**

```bash
git add README.md includes/auth_helpers.php includes/registration_helpers.php pages/register.php pages/verify-registration.php pages/forgot-password.php pages/account.php database/migrations/2026-05-09_add_pending_registrations.sql database/banh_store.sql tests/bootstrap.php tests/auth_helpers_test.php tests/registration_helpers_test.php
git commit -m "feat: add email-verified registration flow"
```
