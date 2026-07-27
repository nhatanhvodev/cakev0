# Admin Redesign — Design Spec

Date: 2026-07-27
Status: Approved (design), pending spec review
Owner: Võ Lý Nhật Anh

## Goal

Redesign the entire admin UI/UX for Gấu Bakery. Two coupled changes:

1. **Architecture** — split the 4177-line `admin/admin.php` monolith into modular files (front controller + per-tab views + shared components + POST handlers by domain).
2. **Visual** — new **dark-first SaaS dashboard** look with an optional light toggle, keeping the bakery brand accent (sage/caramel) as accents on a neutral slate base.

Real Figma file to be generated once the user's Figma seat is upgraded from View → Edit. Until then, validation happens through a self-contained HTML mockup.

## Non-goals

- No JS framework / SPA rewrite. Server-rendered PHP stays.
- No change to database schema, business logic, auth, or the `?tab=` URL contract.
- No change to the customer-facing site.
- No new features — this is restructure + restyle of existing functionality.

## Current state (baseline)

- `admin/admin.php`: 4177 lines, ~200 KB. Everything inline: ~20 `isset($_POST['<action>'])` POST handlers (each CSRF-guarded with `hash_equals`), a large inline `<style>` block (still has hardcoded hex, e.g. `#e74c3c`), sidebar nav, per-tab `<section>` blocks toggled by a `showTab` JS function, ~12 custom `.confirm-modal` dialogs, Chart.js revenue chart at the bottom.
- CSS split between the inline block and `assets/css/pages/admin.css` (~766 lines, partially tokenized to design-system CSS variables).
- Navigation: `admin.php?tab=<name>#<name>` with matching `data-tab` attributes.
- 11 tabs: `dashboard`, `orders`, `products`, `best-selling`, `testimonials`, `password-requests`, `users`, `promotions`, `coupons`, `contacts`, `chat`.
- Chat has a state machine: Chờ hỗ trợ → Đang xử lý → Đã đóng, with admin assignment tracking.
- Known bug: 390px mobile viewport horizontal overflow caused by the 260px fixed sidebar.

## Target architecture

```
admin/
  index.php              Thin front controller: session_start, config requires,
                         auth guard, CSRF ensure, dispatch POST -> handler,
                         resolve ?tab, render layout.
  admin.php              Kept as-is until each tab is ported; then reduced/removed.
  handlers/              POST logic grouped by domain, each function CSRF-checked:
    products.php         add/update/delete product, delete product image, best-selling
    orders.php           update order statuses
    promotions.php       add/update/delete promotion
    coupons.php          add/update/delete coupon
    contacts.php         reply/delete contact
    reviews.php          update review status (testimonials)
    users.php            user-related actions
    password_requests.php update password-request status
  views/
    layout.php           Shell: sidebar + topbar + theme wrapper + toast host.
    tabs/<tab>.php        One file per tab (11). Only the active tab is included,
                         so only its queries run.
    components/
      stat-card.php       KPI card (label, value, trend delta, icon).
      data-table.php      Table wrapper: sticky header, zebra, overflow-x scroll.
      modal.php           Reusable confirm/edit modal (keeps .confirm-modal aria).
      badge.php           Status pill.
      button.php          Button variants.
assets/
  css/admin/
    tokens.css            Color/spacing/z-index/typography tokens + dark & light themes.
    layout.css            Shell: sidebar, topbar, grid, responsive/drawer.
    components.css         Cards, tables, badges, buttons, modals, forms.
  js/admin/
    theme.js              Theme toggle (persist to localStorage).
    nav.js                Sidebar collapse + mobile drawer + tab handling.
    dashboard.js          Chart.js init, themed from CSS tokens.
```

**Dispatch pattern**: front controller reads which `$_POST['<action>']` key is set, includes the matching handler file, calls its function, sets a toast, redirects back to the tab (preserving current `redirectToTab` behavior).

**Migration strategy** (incremental, low risk):
1. Build shell (`layout.php`) + `dashboard` tab + all shared components + new CSS/JS. New entry at `admin/index.php`.
2. Validate look via HTML mockup, then live dashboard.
3. Port remaining 10 tabs one at a time into `views/tabs/` + `handlers/`, each keeping its `?tab=` URL. Old `admin.php` continues serving un-ported tabs during the transition.
4. When all tabs ported, switch the canonical entry to `index.php` and retire `admin.php`.

## Visual design

### Theme tokens (dark-first, light optional via `[data-theme="light"]`)

Dark (default):
- `--bg`: `#0f1115` (app background)
- `--surface`: `#171a21` (cards, sidebar, topbar)
- `--surface-2`: `#1e222b` (hover, raised)
- `--border`: `#242833`
- `--text`: `#e6e8ec`
- `--text-muted`: `#9aa0ac`
- `--accent`: sage (brand) — buttons, active nav, focus rings
- `--accent-2`: caramel (brand) — secondary accents, chart series
- Status: `--warn` amber (pending), `--info` blue (processing), `--success` green (done), `--danger` red (cancelled).

Light theme overrides the same variable names (cream/white surfaces, dark text). Toggle persists in `localStorage`; default dark.

### Shell

- **Sidebar** (left, fixed): brand lockup, grouped nav links with icons. Collapses to an icon rail `<1200px`. Becomes an off-canvas drawer with backdrop on mobile (`<768px`) — this fixes the 390px overflow. Active state uses `--accent`.
- **Topbar**: page title + breadcrumb, global search input, theme toggle, notifications bell, admin avatar with logout menu. Sticky.
- **Toast host**: reuses existing `admin_toast` session mechanism, restyled.

### Dashboard tab

- **KPI row**: 4 stat cards — Revenue, Orders, New users, Pending orders — each with value + trend delta vs previous period + icon.
- **Revenue chart**: existing Chart.js re-themed for dark (grid, ticks, legend colors read from tokens; series use accent + caramel).
- **Recent orders**: `data-table` showing latest orders with status pills.
- Responsive: KPI row wraps to 2×2 then 1 column; chart and table stack.

### Shared component contracts

- `stat-card(label, value, delta, icon, trend)` — trend ∈ {up, down, flat} sets delta color.
- `data-table(headers, rows)` — always wrapped in `.table-scroll` (overflow-x auto) so tables never overflow the page body on mobile.
- `badge(status)` — maps status string → pill color token.
- `modal(id, title, body, actions)` — keeps `aria-modal`, focus trap, and `--z-modal` stacking.

## Accessibility & responsiveness

- Maintain WCAG 2.1 AA contrast in both themes (verify token pairs).
- All interactive controls keyboard-reachable; visible focus ring using `--accent`.
- No horizontal page overflow at 375px (drawer sidebar + scroll-wrapped tables).
- Tap targets ≥ 44px on mobile controls.

## Testing / verification

- Each ported tab: load via `?tab=`, confirm data renders, confirm every POST action still works (add/update/delete) with CSRF intact.
- Dashboard: 4 KPI cards render real values, chart renders in dark and light, recent-orders table populated.
- Responsive check at 375 / 768 / 1280 px: no horizontal overflow; sidebar drawer works on mobile.
- Theme toggle persists across reloads.
- Regression: no change to `?tab=` URLs, toast behavior, or auth guard.

## Deliverable order

1. HTML mockup of shell + dashboard (dark SaaS) — no Figma seat needed; user approves look.
2. Push approved design to Figma (after seat upgraded to Edit).
3. Implement shell + dashboard on new modular architecture (PHP).
4. Port remaining 10 tabs incrementally.

## Open items

- Figma seat upgrade (View → Edit) — user handling; blocks step 2 only, not 1/3/4.
- Confirm whether `chat` tab (state machine + `admin-chat.js`) is ported as-is or restyled only — decide when that tab is reached.
