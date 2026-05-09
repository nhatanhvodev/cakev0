# Email Verification Registration Design

Date: 2026-05-09
Project: `cakev0`
Status: Approved for planning

## Goal

Change the registration flow so a new account is created only after the user verifies their email through a Gmail-delivered verification link. At the same time, enforce a modern password policy across registration and all password update paths.

Confirmed requirements:
- Verification method: email link
- Verification link expiry: 24 hours
- Before verification: do not create a `users` record
- After submit: redirect the user to the home page and keep them as a guest
- Login behavior: only verified accounts can log in because only verified accounts exist in `users`
- Password rule:
  - minimum 12 characters
  - at least 1 lowercase letter
  - at least 1 uppercase letter
  - at least 1 digit
  - at least 1 special character

## Current state

Relevant files and behavior:
- `pages/register.php`
  - validates email
  - only requires password length >= 6
  - inserts directly into `users`
  - auto-logs the user in and redirects to `index.php`
- `pages/login.php`
  - authenticates only against `users` by `username`
- `pages/forgot-password.php`
  - only requires new password length >= 6
  - stores pending password reset requests for admin approval
- `pages/account.php`
  - only requires new password length >= 6
  - stores pending password reset requests for admin approval
- `includes/mailer.php`
  - Gmail API sending is already available
- `database/banh_store.sql`
  - `users.username` is unique
  - `users.email` is not unique

## Chosen approach

Use a separate pending registration table and create the `users` record only after the verification link is opened successfully.

Why this approach:
- It matches the requirement that an unverified registrant should still behave as a guest.
- It keeps `users` clean and avoids storing abandoned, unverified accounts.
- It removes the need for special login blocking logic for unverified accounts.
- It fits the current codebase better than introducing a generic token subsystem.

## Scope

In scope:
- Registration by email verification link
- 24-hour registration token expiry
- Strong password validation in registration
- Strong password validation in forgot-password
- Strong password validation in account password change
- Supporting SQL migration and schema updates
- Basic success and failure pages/messages for verification

Out of scope:
- OTP code verification
- resend-verification UI
- rate limiting / abuse throttling
- email change verification for existing users
- automatic cleanup job for expired pending registrations
- admin review changes for registration

## Data design

### Existing `users` table

Keep `users` as the source of truth for real accounts.

Required schema change:
- add a unique index for `email`

Reason:
- email verification is not reliable if multiple real accounts can share the same email address.

### New `pending_registrations` table

Create a new table:

- `id` INT AUTO_INCREMENT PRIMARY KEY
- `username` VARCHAR(255) NOT NULL
- `email` VARCHAR(255) NOT NULL
- `password_hash` VARCHAR(255) NOT NULL
- `verification_token` VARCHAR(64) NOT NULL
- `expires_at` DATETIME NOT NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

Indexes and constraints:
- `UNIQUE KEY uniq_pending_registrations_username (username)`
- `UNIQUE KEY uniq_pending_registrations_email (email)`
- `UNIQUE KEY uniq_pending_registrations_token (verification_token)`

Notes:
- Store the password already hashed with `password_hash()`.
- The token is a random opaque value from `bin2hex(random_bytes(...))`.
- The table only represents registrations waiting for verification.

## Shared validation rules

Introduce a shared helper, for example `includes/auth_helpers.php`, with functions such as:

- `validate_password_strength(string $password): ?string`
- `generate_verification_token(): string`

Password validation rule:
- length >= 12
- contains lowercase `[a-z]`
- contains uppercase `[A-Z]`
- contains digit `[0-9]`
- contains special character `[^A-Za-z0-9]`

Expected behavior:
- return `null` when valid
- return a user-facing Vietnamese error message when invalid

This helper must be reused in:
- `pages/register.php`
- `pages/forgot-password.php`
- `pages/account.php`

## Registration flow

### Request: `GET /pages/register.php`

Show the existing registration form with:
- updated password hint text
- optional client-side `minlength="12"`
- optional client-side `pattern` for early feedback

Server-side validation remains mandatory and authoritative.

### Request: `POST /pages/register.php`

New flow:

1. Read and trim `username`, `email`, `password`
2. Validate:
   - username is not empty
   - email is valid
   - password passes strong-password validation
3. Check `users`:
   - no existing user with same username
   - no existing user with same email
4. Prepare:
   - `password_hash = password_hash($password, PASSWORD_DEFAULT)`
   - `verification_token = generate_verification_token()`
   - `expires_at = NOW() + INTERVAL 24 HOUR`
5. Upsert-like behavior in `pending_registrations`:
   - if same username or email already exists in pending registrations, update that row with the new submitted values, token, and expiry
   - otherwise insert a new row
6. Send verification email through `send_custom_mail()`
7. On success:
   - do not create session login
   - do not create a `users` record
   - set a success toast/session message
   - redirect to `index.php`

Success message content:
- tell the user registration is almost complete
- ask them to check email
- mention the 24-hour expiry

Example:
- `Đăng ký gần xong. Vui lòng kiểm tra email để xác thực tài khoản trong 24 giờ.`

### Email content

The email should include:
- a clear subject line
- a greeting using username
- a button or direct link to verify the account
- a reminder that the link expires in 24 hours
- a note to ignore the email if they did not register

Verification URL:
- `pages/verify-registration.php?token=<token>`

Use `base_url(...)` so the link matches the deployed environment.

## Verification flow

### New endpoint: `pages/verify-registration.php`

Responsibilities:

1. Read `token` from query string
2. Validate token shape:
   - non-empty
   - treat invalid/missing token as failure
3. Look up `pending_registrations` by `verification_token`
4. If not found:
   - show an error state: invalid link or already used
5. If found but expired:
   - show an error state: link expired
   - keep the row untouched or delete it later; immediate cleanup is optional
6. If found and valid:
   - re-check `users` for username conflict
   - re-check `users` for email conflict
   - if conflict exists, fail with a clear message asking the user to register again
7. Insert into `users`:
   - `username`
   - `password`
   - `email`
8. If insert succeeds:
   - delete the corresponding row from `pending_registrations`
   - set a success toast/session message
   - redirect to `pages/login.php`

Recommended success message:
- `Xác thực email thành công. Bạn có thể đăng nhập ngay bây giờ.`

Recommended failure states:
- invalid or used token
- expired token
- username already taken
- email already used
- unexpected system error

## Login behavior

`pages/login.php` should not require large changes.

Reason:
- only verified accounts exist in `users`
- the login query can remain focused on `users`

Optional cleanup:
- no changes are required unless small UX copy updates are desired.

## Password update flows

The new password policy must be enforced in every server-side path that can set a password.

### `pages/forgot-password.php`

Replace the current minimum-length-only validation with the shared password-strength helper.

Behavior:
- reject weak passwords before hashing or creating/updating `password_reset_requests`
- update hint text and `minlength` in the form

### `pages/account.php`

Replace the current minimum-length-only validation with the shared password-strength helper.

Behavior:
- reject weak passwords before hashing or creating/updating `password_reset_requests`
- update hint text and `minlength` in the form

## Error handling

### Registration submission

If the email cannot be sent:
- do not create a `users` record
- keep or update `pending_registrations`
- show a user-facing error message asking them to try again

Recommended message:
- `Không thể gửi email xác thực. Vui lòng thử lại.`

Rationale:
- the system should not claim the registration is ready when the verification step was not delivered.

### Verification link errors

Cases and expected responses:

- Missing token:
  - fail with invalid link message
- Unknown token:
  - fail with invalid or already used message
- Expired token:
  - fail with expired message
- Username/email conflict at verification time:
  - fail and ask the user to register again with different information
- Database insert failure:
  - fail with a generic system error message

### Security and privacy

- Do not auto-login after verification.
- Do not leak whether a pending registration exists through special-case login behavior.
- Use random token generation only from `random_bytes`.
- Do not store plain-text passwords in pending registrations.

## File-level change plan

Expected files to add or change:

- add `includes/auth_helpers.php`
- update `pages/register.php`
- add `pages/verify-registration.php`
- update `pages/forgot-password.php`
- update `pages/account.php`
- add a migration in `database/migrations/`
- update `database/banh_store.sql`
- optionally update `README.md`

## SQL change outline

Add migration steps along these lines:

1. Create `pending_registrations`
2. Add unique index on `users.email`
3. Resolve existing duplicate emails first if any are found before applying the unique constraint

Important implementation note:
- before shipping the migration to a real environment, inspect current data for duplicate emails in `users`; the repository sample data appears non-duplicated, but production data must be checked before adding the unique index.

## Testing strategy

### Registration validation

Verify rejection for:
- fewer than 12 characters
- no lowercase letter
- no uppercase letter
- no digit
- no special character
- invalid email
- duplicate username in `users`
- duplicate email in `users`

### Pending registration creation

Verify that a valid submission:
- stores or updates a row in `pending_registrations`
- stores a hashed password, not the raw password
- creates a 24-hour expiry
- triggers email sending
- does not log in the user
- redirects to the home page as a guest

### Verification

Verify:
- valid token creates the `users` row and removes the pending row
- invalid token fails
- expired token fails
- reused token fails
- username/email conflict at verification time fails cleanly

### Password policy consistency

Verify the same strong-password rules in:
- registration
- forgot-password
- account password change

## Decisions recorded

- Use email verification link, not OTP
- Token lifetime is 24 hours
- Use pending registrations table, not `users.email_verified_at`
- No user session before verification
- Redirect post-registration to home page as guest
- Do not auto-login after successful verification
- Enforce one strong password policy across all password-setting flows

## Open implementation notes

These are implementation notes, not unresolved product questions:

- Use one shared helper for password validation to avoid rule drift.
- Prefer a dedicated verification page over embedding the logic in `register.php`.
- Keep login behavior unchanged unless a small UX message improvement is needed.
- Consider a later cleanup task for expired pending registrations if the table grows over time.
