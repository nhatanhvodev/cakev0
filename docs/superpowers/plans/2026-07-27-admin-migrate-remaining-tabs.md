# Admin — Migrate Remaining 10 Tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Port the remaining 10 admin tabs (orders, products, users, best-selling, testimonials, password-requests, promotions, coupons, contacts, chat) from the legacy `admin/admin.php` monolith into the new modular `admin/index.php` system, restyled to the dark-SaaS design — so the new UI can become the production entry point.

**Architecture:** Each tab becomes `admin/views/tabs/<tab>.php` (data queries + render via shared components), and its state-changing actions become a domain handler in `admin/handlers/<domain>.php` dispatched from `admin/index.php`. A shared modal component + `modals.js` provide the confirm/edit dialogs the legacy `.confirm-modal` system provided. Legacy `admin.php` stays live and untouched until every tab is ported and the login redirect is flipped (final task).

**Tech Stack:** PHP (server-rendered), MySQL via `$conn` (mysqli), Chart.js (dashboard only), Bootstrap Icons, vanilla JS. Existing components: `render_stat_card`, `render_badge`, `render_table`.

## Global Constraints

- Do NOT modify `admin/admin.php`, DB schema, or the `?tab=<name>` URL contract until the final flip task.
- Every POST handler CSRF-checks with `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])` before mutating.
- **GET-triggered deletes** (`delete_order_id`, `delete_product_id`, `delete_user_id`, `delete_promotion_id`, `delete_coupon_id`) exist in legacy as `?delete_*_id=N` links. Port them faithfully BUT add a CSRF token to the delete URL and verify it server-side (`&csrf=<token>` + `hash_equals`) — the legacy GET-delete-without-CSRF is a known vulnerability; hardening it during the port is in-scope and required. Keep the same `data-delete-url` confirm-modal UX.
- Reuse EXISTING queries/logic from `admin.php` — port, do not redesign SQL. Use only real columns (verify against `admin.php` usage; never invent).
- Escape all output with `htmlspecialchars`. Money: `number_format($v,0,',','.').' VNĐ'`. Timezone Asia/Ho_Chi_Minh.
- Preserve Vietnamese UI copy from legacy.
- Dark-first theme already in place; new markup must use the design tokens/components — no hardcoded hex.
- No automated test framework — verify with `php -l` + browser smoke on `php -S 127.0.0.1:8080 -t D:/` (docroot=parent so `/cakev0/` base resolves) with XAMPP MySQL. Commit after each task. NO `Co-Authored-By` or any commit trailer.

## Porting Contract (applies to every tab task)

For tab `<tab>` with domain `<domain>`:

1. **Handler** `admin/handlers/<domain>.php`: one function per action, e.g. `handle_<action>(mysqli $conn): void`. Move the legacy action body (from the cited `admin.php` line) verbatim in logic, wrapped so it: CSRF-checks (POST) or CSRF-token-checks (GET deletes), performs the mutation with prepared statements (keep legacy's prepared-statement usage), calls `setAdminToast(...)`, then `redirectToTab('<tab>')`.
2. **Dispatch**: in `admin/index.php`, inside the POST block add `if (isset($_POST['<action>'])) { require_once __DIR__.'/handlers/<domain>.php'; handle_<action>($conn); }`; for GET deletes add a GET-dispatch section (guarded by the new csrf token) near the top after bootstrap, before rendering.
3. **View** `admin/views/tabs/<tab>.php`: port the legacy content section (cited line range) — its data queries at the top (reuse legacy SELECTs, real columns), then render using `render_stat_card`/`render_table`/`render_badge` and the new `.card/.panel/.pill/.btn` classes. Replace Bootstrap-ish/legacy markup with the design-system components. `require_once` the component partials at the top (idempotent, `function_exists`-guarded).
4. **Modals**: for each legacy modal the tab uses, render via the shared `render_modal(...)` component (Task 0) with the same fields/actions; JS open/close handled by `modals.js`.
5. **Verify**: `php -l` on both new files; browser smoke (tab renders, each action works, CSRF enforced). Legacy tab must remain reachable via `admin.php` unchanged.

---

### Task 0: Shared modal component + modals.js + dispatch scaffolding

**Files:**
- Create `admin/views/components/modal.php` — `render_modal(string $id, string $title, string $bodyHtml, string $actionsHtml): void` producing a `.confirm-modal` (role=dialog, aria-modal, `aria-labelledby`) styled via tokens.
- Create `assets/js/admin/modals.js` — open/close (`[data-modal-open="id"]`, `[data-modal-close]`, backdrop click, Esc), focus trap, and `[data-delete-url]` → show a confirm modal then `window.location = url`.
- Modify `assets/css/admin/components.css` — add `.confirm-modal`, `.confirm-modal__panel`, backdrop, header/body/footer, using `--surface/--border/--z-modal`.
- Modify `admin/views/layout.php` — add `<script src="../assets/js/admin/modals.js"></script>` after nav.js; add a `<div id="modal-root"></div>` before the closing body if needed.
- Modify `admin/index.php` — expand the POST-dispatch block with a `require`-based router keyed on which `$_POST['<action>']` is set, and add a GET-delete dispatch section (csrf-checked) that runs after bootstrap and before layout render.

**Interfaces:**
- Produces: `render_modal($id,$title,$bodyHtml,$actionsHtml):void`; JS contract `data-modal-open`, `data-modal-close`, `data-delete-url`, `.confirm-modal.is-open`.

- [ ] **Step 1:** Write `modal.php` (confirm-modal markup with `--z-modal`, focusable close, footer actions slot).
- [ ] **Step 2:** Write `modals.js` (open/close/Esc/backdrop, focus trap, delete-url confirm).
- [ ] **Step 3:** Add modal CSS to `components.css` (tokens only).
- [ ] **Step 4:** Wire `modals.js` into `layout.php`.
- [ ] **Step 5:** Refactor `index.php` dispatch: keep the CSRF stub, then route POST actions to `handlers/*` (empty routes for now, filled per task) and add the csrf-checked GET-delete dispatch scaffold.
- [ ] **Step 6:** Verify `php -l` on modal.php, layout.php, index.php; `node --check assets/js/admin/modals.js`.
- [ ] **Step 7:** Commit `feat(admin): shared modal component, modals.js, dispatch router scaffold`.

---

### Task 1: Orders tab
**Source:** content `admin/admin.php:2123-2237`; actions `update_order_statuses`(458), `delete_order_id` GET(875); modals `adminOrderModal`, `deleteOrderModal`; order list query + per-order items (grep `FROM orders`, `adminOrderModal` data attributes).
**Files:** Create `admin/handlers/orders.php`, `admin/views/tabs/orders.php`; Modify `admin/index.php` (dispatch).
- [ ] Port `update_order_statuses` + `delete_order_id` (add csrf token) into `handlers/orders.php`.
- [ ] Port orders table + order-detail modal + status-update controls into `views/tabs/orders.php` using `render_table`/`render_badge`/`render_modal`.
- [ ] Wire dispatch in `index.php`.
- [ ] Verify `php -l` both files; smoke: list renders, status update works (CSRF), delete works (CSRF-guarded), order-detail modal opens.
- [ ] Commit `feat(admin): port orders tab to modular admin`.

### Task 2: Products tab
**Source:** content `2238-2365`; actions `add_product`(212),`update_product`(300),`delete_product_image`(373),`delete_product_id` GET(905); modal `deleteProductModal`; image upload via `storeProductImageUpload`/uploadthing (reuse existing helpers); product list query (~905 area).
**Files:** Create `admin/handlers/products.php`, `admin/views/tabs/products.php`; Modify `index.php`.
- [ ] Port add/update/delete-image + GET delete (csrf) into `handlers/products.php`, reusing existing image helpers (`storeProductImageUpload`, `buildImageUrl`).
- [ ] Port product table + add/edit product form + delete modal into `views/tabs/products.php`.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list, add, edit, delete image, delete product all work with CSRF; images render via `buildImageUrl`.
- [ ] Commit `feat(admin): port products tab to modular admin`.

### Task 3: Users tab
**Source:** content `2366-2407`; action `delete_user_id` GET(813); user list + total_spent query(950); modals `deleteUserModal`, `userOrdersModal` (per-user orders loaded — grep JS `userOrders` around 2932).
**Files:** Create `admin/handlers/users.php`, `admin/views/tabs/users.php`; Modify `index.php`.
- [ ] Port `delete_user_id` (csrf) into `handlers/users.php`.
- [ ] Port users table (with total_spent) + delete modal + user-orders modal into `views/tabs/users.php`. If user-orders was rendered by inline JS building HTML, port that markup/JS faithfully (or render server-side into the modal).
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list renders with spend, delete works (CSRF), user-orders modal shows orders.
- [ ] Commit `feat(admin): port users tab to modular admin`.

### Task 4: Best-selling tab
**Source:** content `2408-2556`; action `update_best_selling`(405); best-selling query(1027).
**Files:** Create `admin/handlers/best_selling.php`, `admin/views/tabs/best-selling.php`; Modify `index.php`.
- [ ] Port `update_best_selling` into `handlers/best_selling.php`.
- [ ] Port best-selling management UI (product selection + sold quantities) into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list renders, best-selling flags save (CSRF).
- [ ] Commit `feat(admin): port best-selling tab to modular admin`.

### Task 5: Testimonials tab
**Source:** content `2557-2621`; action `update_review_status`(435); reviews query.
**Files:** Create `admin/handlers/reviews.php`, `admin/views/tabs/testimonials.php`; Modify `index.php`.
- [ ] Port `update_review_status` into `handlers/reviews.php`.
- [ ] Port reviews table + approve/hide controls (status via `render_badge`) into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list renders, status toggle works (CSRF).
- [ ] Commit `feat(admin): port testimonials tab to modular admin`.

### Task 6: Password-requests tab
**Source:** content `2622-2688`; action `update_password_request_status`(689); requests query.
**Files:** Create `admin/handlers/password_requests.php`, `admin/views/tabs/password-requests.php`; Modify `index.php`.
- [ ] Port `update_password_request_status` into the handler.
- [ ] Port requests table + status controls into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list renders, status update works (CSRF).
- [ ] Commit `feat(admin): port password-requests tab to modular admin`.

### Task 7: Promotions tab
**Source:** content `2689-2754`; actions `add_promotion`(515),`update_promotion`(524),`delete_promotion_id` GET(533); modals `deletePromotionModal`,`editPromotionModal`; promotions query.
**Files:** Create `admin/handlers/promotions.php`, `admin/views/tabs/promotions.php`; Modify `index.php`.
- [ ] Port add/update/delete(csrf) into `handlers/promotions.php`.
- [ ] Port promotions table + add form + edit modal + delete modal into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list, add, edit (modal), delete (CSRF) all work.
- [ ] Commit `feat(admin): port promotions tab to modular admin`.

### Task 8: Coupons tab
**Source:** content `2755-2857`; actions `add_coupon`(541),`update_coupon`(592),`delete_coupon_id` GET(646); modals `deleteCouponModal`,`editCouponModal`; coupons query; `ensureCartCouponInfrastructure` already run in bootstrap.
**Files:** Create `admin/handlers/coupons.php`, `admin/views/tabs/coupons.php`; Modify `index.php`.
- [ ] Port add/update/delete(csrf) into `handlers/coupons.php` (respect `is_active` checkbox handling).
- [ ] Port coupons table + add form + edit modal + delete modal into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list, add, edit (modal), delete (CSRF), active toggle all work.
- [ ] Commit `feat(admin): port coupons tab to modular admin`.

### Task 9: Contacts tab
**Source:** content `2858-2929`; actions `reply_contact`(657),`delete_contact`(768 POST); modals `contactDetailModal`,`contactReplyModal`,`contactDeleteModal`; contacts query; reply uses mailer (`includes/mailer.php`, already required in bootstrap).
**Files:** Create `admin/handlers/contacts.php`, `admin/views/tabs/contacts.php`; Modify `index.php`.
- [ ] Port `reply_contact` (sends email) + `delete_contact` into `handlers/contacts.php`.
- [ ] Port contacts table + detail/reply/delete modals into the view.
- [ ] Wire dispatch.
- [ ] Verify `php -l`; smoke: list, view detail, reply (email attempt), delete all work (CSRF).
- [ ] Commit `feat(admin): port contacts tab to modular admin`.

### Task 10: Chat tab
**Source:** content `admin/admin.php:2930+`; behavior in `assets/js/admin-chat.js` (state machine: Chờ hỗ trợ → Đang xử lý → Đã đóng, admin assignment). This tab is JS-driven against chat endpoints.
**Files:** Create `admin/views/tabs/chat.php`; Modify `index.php`; possibly Modify `layout.php` to include `admin-chat.js` only on the chat tab.
- [ ] Port the chat inbox container markup into `views/tabs/chat.php`, restyled with design tokens (conversation list + message panel + composer + status controls).
- [ ] Include existing `assets/js/admin-chat.js` for the chat tab (conditionally, when `$tab==='chat'`), confirming its DOM selectors match the new markup — adjust markup to match the JS contract rather than rewriting the JS.
- [ ] Verify `php -l`; smoke: chat tab loads, conversation list populates, reply/state transitions work against the live endpoints.
- [ ] Commit `feat(admin): port chat tab to modular admin`.

### Task 11: Flip entry to new admin + retire legacy
**Files:** Modify `pages/login.php` (admin redirect `admin/admin.php` → `admin/index.php`), Modify `admin/admin.php` (top: redirect to `index.php` preserving `?tab=`), update sidebar/links if any point to `admin.php`.
- [ ] Change admin login redirect (`pages/login.php:104`) to `/cakev0/admin/index.php`.
- [ ] Make `admin/admin.php` redirect to `index.php` (`header('Location: index.php?tab='.($_GET['tab']??'dashboard')); exit;`) at the very top after the auth guard, so old bookmarks land on the new UI. (Keep the file so nothing 404s.)
- [ ] Full regression smoke: every tab reachable + functional via `index.php`; login lands on new UI; `admin.php` redirects.
- [ ] Commit `feat(admin): make modular admin the entry point, redirect legacy admin.php`.

---

## Self-Review

**Spec coverage:** All 10 tabs (Tasks 1-10) + shared modal infra (Task 0) + entry flip (Task 11). Each tab's content range, actions, and modals are cited from the monolith map.

**Placeholder scan:** Tab tasks are porting instructions citing exact legacy source ranges + action keys + the shared Porting Contract, not vague TODOs — the code exists in `admin.php` and is relocated, the same method that succeeded for the dashboard tab. Handler/component signatures are fixed by the Porting Contract and Task 0.

**Type consistency:** Handlers expose `handle_<action>(mysqli $conn): void`; views consume `render_stat_card/render_badge/render_table/render_modal`; dispatch keys match the `$_POST['<action>']`/`$_GET['delete_*_id']` names from the map. GET deletes gain a `&csrf=` token verified server-side (constraint), consistently across Tasks 1,2,3,7,8.

**Ordering:** Task 0 (shared modal infra) must precede any tab using modals (1,2,3,7,8,9). Task 11 (flip) must be last. Tabs 4,5,6 (no modals) can go anytime after Task 0.

**Known issue addressed:** legacy GET-deletes lacked CSRF; the port hardens them (Global Constraints) rather than replicating the vulnerability into the new entry point.
