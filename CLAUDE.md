# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Two applications in one repo for **Gấu Bakery** (a Vietnamese online cake shop):

1. **PHP web app** (repo root) — vanilla PHP 8.2 + MySQL 8, no framework. Customer storefront + admin panel. This is the primary app.
2. **`ai-service/`** — a separate Python FastAPI multiagent chatbot (customer-support chat: FAQ/policy Q&A, order lookup, order creation, human handoff, Messenger channel). Deployed independently; the PHP app talks to it over HTTP.

The two are decoupled: the PHP app proxies chat requests to the AI service, and the AI service calls back into the PHP app's internal order API. They share the same MySQL database.

## Commands

### Run the full stack (Docker — easiest)
```bash
docker compose up --build
```
- Website: `http://localhost:8080/cakev0/` — note the **`/cakev0/` base path** (see below)
- phpMyAdmin: `http://localhost:8081/`
- MySQL from host: `127.0.0.1:3307`
- AI service: `http://localhost:8000` (`/health` for status)

DB auto-imports `database/banh_store.sql` on first init.

### PHP app manually
```bash
composer install
mysql -u root -p banh_store < database/banh_store.sql
```
Config comes from `.env` then `.env.local` (see `config/bootstrap.php`).

### PHP tests
Tests are **standalone scripts**, not PHPUnit. Each file is run directly and exits non-zero on failure. There is no aggregate runner — run one at a time:
```bash
php tests/auth_helpers_test.php
```
`tests/bootstrap.php` provides `assert_true()` / `assert_same()` helpers and loads `config/bootstrap.php`. A passing test prints `... ok`.

### AI service
```bash
cd ai-service
python -m venv venv && venv\Scripts\activate
pip install -r requirements.txt
venv\Scripts\python -m uvicorn app.main:app --reload --port 8000
```
Tests use pytest:
```bash
cd ai-service && pytest
pytest tests/test_router.py            # single file
```

## Architecture

### `/cakev0/` base path — critical
The app is served under the `/cakev0/` sub-path, and many URLs are **hardcoded** with that prefix (e.g. `/cakev0/assets/...`, `/cakev0/api/internal/orders/create.php`). When adding links/redirects/asset paths, keep the prefix. Production (Render) serves the same layout at `https://cake-i8l0.onrender.com/cakev0/`.

### PHP bootstrap chain
- `config/bootstrap.php` — loads Composer autoload, parses `.env`/`.env.local` into `getenv`/`$_ENV`/`$_SERVER`, defines `env_value()` / `env_bool()`, sets timezone. Env vars set in the real environment (Docker/Render) win over `.env`.
- `config/connect.php` — requires bootstrap, opens the shared `$conn` mysqli, sets charset + DB `time_zone` to match `APP_TIMEZONE`, and calls `ensureProductVisibilityInfrastructure()`. **Including `connect.php` is how every page gets `$conn`.**
- Customer pages set up their own `session_start()` + `require config/connect.php` at the top of each file (see `index.php`).

### Customer frontend
`index.php` (home) + `pages/*.php` — one PHP file per page, view logic and request handling mixed in the same file. Shared UI in `includes/header.php` and `includes/footer.html`. Reusable logic lives in `includes/` (checkout, orders, invoices, mail, best-selling, notifications, chat proxy, registration, auth).

### Authentication — Auth0 Universal Login
Login/register/logout redirect through Auth0 (OIDC). `pages/auth/login.php` → Auth0, `pages/auth/callback.php` exchanges the code and builds the app session, `pages/auth/logout.php` clears both sessions. Config in `includes/auth0.php`; identity→local-user bridging in `includes/auth_bridge.php` (`sync_session_from_auth0()` links `users.auth0_id`, maps the admin role from the Auth0 role claim). Management API helpers (resend verification email, user import, email-provider config) in `includes/auth0_management.php`.

Auth0 owns credentials — `users.password` is empty. The callback **blocks login until `email_verified` is true** (unverified users go to `pages/auth/verify-notice.php`, which can resend the verification email via Management API). Password reset is handled entirely by Auth0; `pages/forgot-password.php` just redirects to Auth0. Needs `AUTH0_DOMAIN`/`AUTH0_CLIENT_ID`/`AUTH0_CLIENT_SECRET`/`AUTH0_COOKIE_SECRET` plus `AUTH0_MGMT_CLIENT_ID`/`AUTH0_MGMT_CLIENT_SECRET` for Management API. Legacy local-auth tables (`password_reset_requests`, `login_tokens`) remain but are no longer the credential authority.

### Payment — SePay only
COD + SePay VietQR. QR page in `sepay/`, webhook at `api/sepay/webhook.php` auto-marks an order `paid` when SePay reports a transfer whose memo contains `DH<order_id>` (auth via `SEPAY_WEBHOOK_API_KEY`). VNPAY and manual bank transfer were removed.

### Admin panel — modular, with a legacy file alongside
Two admins coexist:
- **Legacy**: `admin/admin.php` (monolith, still present).
- **Modular (preferred, work in progress)**: `admin/index.php` is a front controller that:
  1. requires `admin/bootstrap.php` (session, config, `$conn`, auth guard requiring `admin_logged_in` + `role === 'admin'`, CSRF token, `setAdminToast()` / `redirectToTab()`),
  2. dispatches `?delete_*_id` GET routes and POST actions to `admin/handlers/<domain>.php` functions,
  3. renders `admin/views/layout.php` for the selected `?tab=`.

**Porting contract** (when migrating a tab from legacy to modular): every GET-delete route MUST carry `&csrf=<token>` verified with `admin_csrf_get_ok()` (the legacy `?delete_*_id=N` links had no CSRF check — a known vuln the modular admin fixes). POST actions are CSRF-checked centrally in `index.php`, then routed to a `handle_*($conn)` function. Handlers call `setAdminToast()` then `redirectToTab()`. Views are split across `admin/views/{tabs,partials,components}/`; shared render helpers (tables, badges, modals) live in `admin/views/components/`. See `docs/superpowers/plans/` for the migration plan.

### PHP ↔ AI service integration
- `api/chat/*.php` — the PHP-side chat endpoints the widget hits; they proxy to the AI service at `AI_SERVICE_URL` (helpers in `includes/chat_proxy_helpers.php`). If `AI_SERVICE_URL` is unset, chat fails — the service must be reachable.
- `api/internal/orders/*` — the AI service calls back here to create orders. Guarded by a shared `INTERNAL_API_SECRET` (same value on both sides). See `includes/internal_order_api.php`.

### AI service internals (`ai-service/app/`)
- `main.py` — FastAPI app: in-process rate limiter (20 req/min on `/chat/send`), CORS (`CORS_ORIGINS`), two startup hooks (additive admin-chat schema migration; auto-reindex ChromaDB when empty, because free-tier Render has no persistent disk), `/health` + `/debug/*` diagnostics.
- `api/` (chat, messenger), `channels/` (Messenger), `engines/` — swappable via `ENGINE` env (`baseline`, `demo`, `multiagent`). The `multiagent/` engine is a LangGraph workflow: `router → retrieval / action / handoff / custom_quote / order_create → aggregate` (see `graph.py`, `state.py`).
- `llm.py` + `LLM_PROVIDER` — DeepSeek for chat in production, Gemini for embeddings (`gemini-embedding-001`); ChromaDB persists at `CHROMA_PERSIST_DIR`.
- `knowledge/` — retrieval + indexer; `services/` — order creation, custom quote, notify; `db/` — MySQL access (shares the PHP app's database).

## Deployment
- **PHP web**: Render Docker (`cake-i8l0.onrender.com`), created/managed manually (NOT in `render.yaml`). Must set `AI_SERVICE_URL` + `INTERNAL_API_SECRET`, the `AUTH0_*` + `AUTH0_MGMT_*` vars, and `SEPAY_*` vars.
- **AI service**: `render.yaml` blueprint (`gau-bakery-ai`, Singapore, free plan).
- **DB**: Aiven MySQL (`defaultdb`, SSL). Local DB name is `banh_store`, production is `defaultdb` — don't hardcode the DB name.
- Mail: SMTP locally; on Render, an HTTP driver (Resend, or Gmail API via `MAIL_DRIVER=gmail_api`) because SMTP port 587 times out there. Auth0's own emails (verify/reset) go through the Auth0 tenant email provider (Resend), configured with `scripts/configure_auth0_email_provider.php`.

## Conventions
- **Language**: user-facing strings, comments, commit messages, and docs are largely in Vietnamese. Match the surrounding language.
- **DB access**: use prepared statements (`prepare()` + `bind_param()`) — the pattern throughout. Escape output with `htmlspecialchars()`.
- **Domain tables** are Vietnamese-named: `banh` (products/cakes), `orders`, `order_items`, `promotions`, `coupons`/`cart_coupons`, `reviews`, `contact_requests`, `users`, `admins`.
