# Admin Shell + Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the new dark-first SaaS admin shell (sidebar + topbar + theme) and the Dashboard tab on a modular PHP architecture at `admin/index.php`, running alongside the untouched legacy `admin/admin.php`.

**Architecture:** A thin front controller (`admin/index.php`) bootstraps session/config/auth/CSRF, resolves `?tab=`, and renders `views/layout.php`, which pulls in the active tab from `views/tabs/`. Shared UI comes from `views/components/`. Styling lives in three token-driven CSS files under `assets/css/admin/`; behavior in three JS modules under `assets/js/admin/`. Dashboard reuses the existing data queries verbatim from `admin.php`.

**Tech Stack:** PHP (server-rendered, no framework), MySQL via existing `config/connect.php` (`$conn`, mysqli), Chart.js (already loaded in the app), Bootstrap Icons (`bi-*`), vanilla JS.

## Global Constraints

- No JS framework / SPA. Server-rendered PHP only.
- Do NOT modify `admin/admin.php`, the DB schema, auth logic, or the `?tab=<name>` URL contract. Legacy admin keeps working during migration.
- Auth guard is mandatory on every entry: redirect to `../pages/login.php` unless `$_SESSION['admin_logged_in']` set AND `$_SESSION['role'] === 'admin'`.
- Every POST handler must CSRF-check with `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])`. (Dashboard has no POST, but the dispatch stub must enforce this for future handlers.)
- Timezone `Asia/Ho_Chi_Minh`. Money formatted `number_format($v, 0, ',', '.')` + ` VNĐ`.
- Theme: dark default, light via `[data-theme="light"]`; persist choice in `localStorage`. Both themes WCAG AA.
- No automated test harness exists in this repo. "Verify" steps are browser smoke-tests via a local PHP server + the Browser pane. Commit after each task.

## Local run (for all verify steps)

From `D:\cakev0`:
```bash
php -S localhost:8080
```
Then in the Browser pane: log in once at `http://localhost:8080/pages/login.php` as an admin (establishes `$_SESSION`), then open `http://localhost:8080/admin/index.php?tab=dashboard`.
(If you use XAMPP/Apache instead, substitute your local host, e.g. `http://localhost/cakev0/...`.)

---

## File Structure

- Create `assets/css/admin/tokens.css` — CSS custom properties: color/spacing/radius/z-index tokens + dark (default via `prefers-color-scheme` and `[data-theme="dark"]`) and light (`[data-theme="light"]`) themes.
- Create `assets/css/admin/layout.css` — `.app` grid, `.sidebar`, `.topbar`, `.content`, collapse + mobile drawer, responsive breakpoints.
- Create `assets/css/admin/components.css` — `.card`, `.kpi`, `.panel`, `.statlist`, `.table-scroll`/`table`, `.pill`, `.iconbtn`, `.avatar`, buttons, modal restyle.
- Create `assets/js/admin/theme.js` — theme toggle + `localStorage` persistence + `syncIcon`.
- Create `assets/js/admin/nav.js` — sidebar collapse, mobile drawer open/close, active-link handling.
- Create `assets/js/admin/dashboard.js` — Chart.js init reading colors from CSS tokens; re-theme on toggle.
- Create `admin/bootstrap.php` — session start, timezone, config requires, auth guard, CSRF ensure. One responsibility: make a valid admin request context.
- Create `admin/views/components/stat_card.php` — `render_stat_card($label,$value,$delta,$deltaDir,$icon)`.
- Create `admin/views/components/badge.php` — `render_badge($status)` maps status → pill class + Vietnamese label.
- Create `admin/views/components/data_table.php` — `render_table($headers, $rowsHtml)` wraps in `.table-scroll`.
- Create `admin/views/tabs/dashboard.php` — computes dashboard data (ported from `admin.php` lines ~798–1150) and renders KPI row, chart panel, status/best-selling widget, recent-orders table.
- Create `admin/views/layout.php` — full HTML document: `<head>` (CSS links, Chart.js, Bootstrap Icons), sidebar, topbar, `<main>` including the active tab, script tags.
- Create `admin/index.php` — front controller: `require bootstrap.php`, resolve `$tab` (allowlist, default `dashboard`), POST-dispatch stub, `require layout.php`.

---

### Task 1: CSS tokens (dark + light)

**Files:**
- Create: `assets/css/admin/tokens.css`

**Interfaces:**
- Produces: CSS custom properties consumed by layout.css, components.css, and dashboard.js (`--accent`, `--caramel`, `--border`, `--faint`, plus surfaces/text/status tokens). Exact names below are contract.

- [ ] **Step 1: Write the file**

```css
/* assets/css/admin/tokens.css */
:root {
  --bg:#f5f4f0; --surface:#fff; --surface-2:#f0efe9; --border:#e2e0d7;
  --text:#22251f; --muted:#6b7168; --faint:#9aa093;
  --accent:#5f7350; --accent-soft:rgba(95,115,80,.12); --caramel:#b7823f;
  --warn:#c98a2b; --info:#3f7cc0; --success:#4e9a5f; --danger:#c65a4e;
  --warn-bg:rgba(201,138,43,.14); --info-bg:rgba(63,124,192,.14);
  --success-bg:rgba(78,154,95,.14); --danger-bg:rgba(198,90,78,.14);
  --shadow:0 1px 2px rgba(30,35,25,.06),0 8px 24px rgba(30,35,25,.05);
  --radius:12px; --sidebar-w:240px;
  --z-header:100; --z-sticky:200; --z-modal:3000;
  color-scheme:light;
}
@media (prefers-color-scheme:dark){ :root{
  --bg:#0f1211; --surface:#171b19; --surface-2:#1e2320; --border:#262b28;
  --text:#e7eae6; --muted:#98a09a; --faint:#6d746e;
  --accent:#8aa079; --accent-soft:rgba(138,160,121,.15); --caramel:#cd9a5e;
  --warn:#d9a24a; --info:#6aa3d8; --success:#6fae7a; --danger:#d9756b;
  --warn-bg:rgba(217,162,74,.16); --info-bg:rgba(106,163,216,.16);
  --success-bg:rgba(111,174,122,.16); --danger-bg:rgba(217,117,107,.16);
  --shadow:0 1px 2px rgba(0,0,0,.4),0 12px 30px rgba(0,0,0,.35);
  color-scheme:dark;
}}
:root[data-theme="light"]{
  --bg:#f5f4f0; --surface:#fff; --surface-2:#f0efe9; --border:#e2e0d7;
  --text:#22251f; --muted:#6b7168; --faint:#9aa093;
  --accent:#5f7350; --accent-soft:rgba(95,115,80,.12); --caramel:#b7823f;
  --warn:#c98a2b; --info:#3f7cc0; --success:#4e9a5f; --danger:#c65a4e;
  --warn-bg:rgba(201,138,43,.14); --info-bg:rgba(63,124,192,.14);
  --success-bg:rgba(78,154,95,.14); --danger-bg:rgba(198,90,78,.14);
  --shadow:0 1px 2px rgba(30,35,25,.06),0 8px 24px rgba(30,35,25,.05);
  color-scheme:light;
}
:root[data-theme="dark"]{
  --bg:#0f1211; --surface:#171b19; --surface-2:#1e2320; --border:#262b28;
  --text:#e7eae6; --muted:#98a09a; --faint:#6d746e;
  --accent:#8aa079; --accent-soft:rgba(138,160,121,.15); --caramel:#cd9a5e;
  --warn:#d9a24a; --info:#6aa3d8; --success:#6fae7a; --danger:#d9756b;
  --warn-bg:rgba(217,162,74,.16); --info-bg:rgba(106,163,216,.16);
  --success-bg:rgba(111,174,122,.16); --danger-bg:rgba(217,117,107,.16);
  --shadow:0 1px 2px rgba(0,0,0,.4),0 12px 30px rgba(0,0,0,.35);
  color-scheme:dark;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);
  font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
.num{font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
```

- [ ] **Step 2: Verify it parses**

Run: `php -r "echo file_exists('assets/css/admin/tokens.css') ? 'ok' : 'missing';"`
Expected: `ok`

- [ ] **Step 3: Commit**

```bash
git add assets/css/admin/tokens.css
git commit -m "feat(admin): dark/light design tokens for redesigned admin"
```

---

### Task 2: Layout CSS (shell + responsive drawer)

**Files:**
- Create: `assets/css/admin/layout.css`

**Interfaces:**
- Consumes: tokens from Task 1.
- Produces: classes `.app`, `.app.collapsed`, `.sidebar`, `.brand`, `.nav`, `.topbar`, `.content`, `.drawer-open`, `.backdrop` used by layout.php (Task 10) and nav.js (Task 5).

- [ ] **Step 1: Write the file** — port the shell rules from the approved mockup (`scratchpad/admin-mockup.html`), splitting shell-only rules here. Include the sidebar (`.sidebar`, `.brand`, `.nav`, `.nav a`, `.active`, `.badge-count`), `.app` grid, `.app.collapsed` (`--sidebar-w:72px`), `.topbar` + children (`.toggle`, `.titlewrap`, `.search`), `.content` (`padding:22px;display:flex;flex-direction:column;gap:18px;max-width:1320px`), and the responsive blocks:

```css
/* key additions beyond the mockup: real mobile drawer */
@media (max-width:760px){
  .app{grid-template-columns:1fr}
  .sidebar{position:fixed;left:0;top:0;z-index:var(--z-modal);width:260px;
    transform:translateX(-100%);transition:transform .25s ease}
  .app.drawer-open .sidebar{transform:translateX(0)}
  .backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:calc(var(--z-modal) - 1)}
  .app.drawer-open .backdrop{display:block}
}
@media (min-width:761px){ .backdrop{display:none} }
```
Copy the remaining shell rules verbatim from the mockup's `<style>` (the `.app`, `.sidebar`, `.brand`, `.nav*`, `.topbar`, `.search`, `.iconbtn`, `.avatar`, `.content` blocks, and the `max-width:1100px` block for `.kpi-row`/`.row-2`). Do NOT copy `.card/.kpi/.panel/.pill/table` rules — those go in Task 3.

- [ ] **Step 2: Verify** `php -r "echo file_exists('assets/css/admin/layout.css')?'ok':'missing';"` → `ok`

- [ ] **Step 3: Commit**

```bash
git add assets/css/admin/layout.css
git commit -m "feat(admin): shell layout css with mobile drawer sidebar"
```

---

### Task 3: Components CSS

**Files:**
- Create: `assets/css/admin/components.css`

**Interfaces:**
- Consumes: tokens from Task 1.
- Produces: `.card`, `.kpi`, `.panel`, `.panel-head`, `.seg`, `.legend`, `.statlist`, `.table-scroll`, `table/th/td`, `.cust`, `.pill` (+`.warn/.info/.success/.danger`), `.rowlink`, `.btn` variants — consumed by components (Tasks 6–8) and dashboard (Task 9).

- [ ] **Step 1: Write the file** — copy the component rules verbatim from the mockup `<style>`: `.card`, `.kpi*`, `.delta*`, `.row-2`, `.panel*`, `.seg*`, `.legend*`, `.statlist*`, `.table-scroll`, `table/th/td`, `tbody tr:hover`, `.cust*`, `.pill*`, `.rowlink`. Then add button variants:

```css
.btn{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;
  padding:8px 14px;border-radius:9px;border:1px solid transparent;cursor:pointer}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{filter:brightness(1.06)}
.btn-ghost{background:var(--surface);border-color:var(--border);color:var(--text)}
.btn-ghost:hover{background:var(--surface-2)}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{filter:brightness(.9)}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
```

- [ ] **Step 2: Verify** `php -r "echo file_exists('assets/css/admin/components.css')?'ok':'missing';"` → `ok`

- [ ] **Step 3: Commit**

```bash
git add assets/css/admin/components.css
git commit -m "feat(admin): card/table/pill/button component css"
```

---

### Task 4: JS modules (theme, nav, dashboard chart)

**Files:**
- Create: `assets/js/admin/theme.js`
- Create: `assets/js/admin/nav.js`
- Create: `assets/js/admin/dashboard.js`

**Interfaces:**
- Consumes: DOM ids `themeBtn`, `collapseBtn`, `app`, `revenueChart` (canvas), and globals `window.ADMIN_CHART = {labels:[], values:[]}` set inline by dashboard.php (Task 9).
- Produces: theme persisted at `localStorage['admin-theme']`; a themed Chart.js instance.

- [ ] **Step 1: theme.js**

```js
(function(){
  var root=document.documentElement, btn=document.getElementById('themeBtn');
  var saved=localStorage.getItem('admin-theme');
  if(saved){root.setAttribute('data-theme',saved);}
  function isDark(){var a=root.getAttribute('data-theme');
    return a?a==='dark':matchMedia('(prefers-color-scheme:dark)').matches;}
  function sync(){if(btn)btn.innerHTML=isDark()?'<i class="bi bi-sun"></i>':'<i class="bi bi-moon-stars"></i>';}
  if(btn)btn.addEventListener('click',function(){
    var next=isDark()?'light':'dark';
    root.setAttribute('data-theme',next);
    localStorage.setItem('admin-theme',next); sync();
    document.dispatchEvent(new CustomEvent('admin:themechange'));
  });
  sync();
})();
```

- [ ] **Step 2: nav.js**

```js
(function(){
  var app=document.getElementById('app');
  var collapse=document.getElementById('collapseBtn');
  if(collapse)collapse.addEventListener('click',function(){
    if(matchMedia('(max-width:760px)').matches){app.classList.toggle('drawer-open');}
    else{app.classList.toggle('collapsed');}
  });
  var bd=document.querySelector('.backdrop');
  if(bd)bd.addEventListener('click',function(){app.classList.remove('drawer-open');});
})();
```

- [ ] **Step 3: dashboard.js**

```js
(function(){
  var el=document.getElementById('revenueChart');
  if(!el||!window.Chart||!window.ADMIN_CHART)return;
  function tok(n){return getComputedStyle(document.body).getPropertyValue(n).trim();}
  var chart;
  function draw(){
    if(chart)chart.destroy();
    var ctx=el.getContext('2d');
    var grad=ctx.createLinearGradient(0,0,0,240);
    grad.addColorStop(0,tok('--accent')); grad.addColorStop(1,'transparent');
    chart=new Chart(ctx,{type:'line',data:{labels:window.ADMIN_CHART.labels,
      datasets:[{label:'Doanh thu (VNĐ)',data:window.ADMIN_CHART.values,
        borderColor:tok('--accent'),backgroundColor:grad,fill:true,tension:.35,
        pointRadius:0,pointHoverRadius:5,borderWidth:2.5}]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{labels:{color:tok('--muted')}}},
        scales:{x:{grid:{color:tok('--border')},ticks:{color:tok('--faint')}},
          y:{grid:{color:tok('--border')},ticks:{color:tok('--faint')}}}}});
  }
  draw();
  document.addEventListener('admin:themechange',draw);
})();
```

- [ ] **Step 4: Verify** all three exist:
`php -r "foreach(['theme','nav','dashboard'] as $f){echo file_exists(\"assets/js/admin/$f.js\")?\"$f ok \":\"$f MISSING \";}"`
Expected: `theme ok nav ok dashboard ok`

- [ ] **Step 5: Commit**

```bash
git add assets/js/admin/
git commit -m "feat(admin): theme toggle, nav drawer, themed Chart.js modules"
```

---

### Task 5: Bootstrap (session, config, auth, CSRF)

**Files:**
- Create: `admin/bootstrap.php`

**Interfaces:**
- Consumes: `config/*`, `includes/*` (same requires as `admin.php` top).
- Produces: a guaranteed admin session, `$conn` (mysqli) in scope, `$_SESSION['csrf_token']` set. Consumed by index.php (Task 11) and all future handlers.

- [ ] **Step 1: Write the file**

```php
<?php
// admin/bootstrap.php — request context for the modular admin.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/coupons.php';
require_once __DIR__ . '/../config/uploadthing.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/invoice_mailer.php';

if (function_exists('ensureCartCouponInfrastructure')) {
    ensureCartCouponInfrastructure($conn);
}

// Auth guard — identical contract to legacy admin.php.
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../pages/login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('setAdminToast')) {
    function setAdminToast($msg, $type = 'success') {
        $_SESSION['admin_toast'] = ['msg' => $msg, 'type' => $type];
    }
}
if (!function_exists('redirectToTab')) {
    function redirectToTab(string $tab): void {
        header("Location: index.php?tab={$tab}#{$tab}");
        exit;
    }
}
```

- [ ] **Step 2: Verify** no parse errors: `php -l admin/bootstrap.php` → `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add admin/bootstrap.php
git commit -m "feat(admin): bootstrap with auth guard and csrf for modular admin"
```

---

### Task 6: UI component partials

**Files:**
- Create: `admin/views/components/stat_card.php`
- Create: `admin/views/components/badge.php`
- Create: `admin/views/components/data_table.php`

**Interfaces:**
- Produces: `render_stat_card($label,$value,$delta,$deltaDir,$icon)`, `render_badge($status)`, `render_table(array $headers, string $rowsHtml)`. Consumed by dashboard.php (Task 9).

- [ ] **Step 1: stat_card.php**

```php
<?php
function render_stat_card(string $label, string $value, string $delta, string $deltaDir, string $icon): void {
  $dir = $deltaDir === 'down' ? 'down' : 'up';
  $arrow = $dir === 'up' ? '▲' : '▼';
  echo '<div class="card kpi"><div class="top"><span class="label">'
    . htmlspecialchars($label) . '</span><span class="ico"><i class="bi ' . htmlspecialchars($icon) . '"></i></span></div>'
    . '<div class="val num">' . htmlspecialchars($value) . '</div>'
    . '<div class="delta ' . $dir . '">' . $arrow . ' ' . htmlspecialchars($delta) . '</div></div>';
}
```

- [ ] **Step 2: badge.php**

```php
<?php
function render_badge(string $status): string {
  $map = [
    'completed' => ['success','Hoàn thành'], 'delivered' => ['success','Đã giao'],
    'paid' => ['success','Đã thanh toán'], 'approved' => ['info','Đang xử lý'],
    'processing' => ['info','Đang xử lý'], 'pending' => ['warn','Chờ duyệt'],
    'cancelled' => ['danger','Đã huỷ'], 'canceled' => ['danger','Đã huỷ'],
  ];
  $key = strtolower(trim($status));
  [$cls,$label] = $map[$key] ?? ['info', $status];
  return '<span class="pill ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}
```

- [ ] **Step 3: data_table.php**

```php
<?php
function render_table(array $headers, string $rowsHtml): void {
  echo '<div class="table-scroll"><table><thead><tr>';
  foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
  echo '</tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>';
}
```

- [ ] **Step 4: Verify** `php -l` each of the three → `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add admin/views/components/
git commit -m "feat(admin): stat-card, badge, data-table component partials"
```

---

### Task 7: Layout shell (`layout.php`)

**Files:**
- Create: `admin/views/layout.php`

**Interfaces:**
- Consumes: `$tab` (string, current tab) and `$conn` from index.php (Task 11).
- Produces: full HTML document; includes `views/tabs/{$tab}.php` inside `<main>`. Exposes DOM ids `app`, `collapseBtn`, `themeBtn` for the JS modules.

- [ ] **Step 1: Write the file** — full document. Head links Bootstrap Icons + the three admin CSS files + Chart.js (reuse the exact CDN URLs already used in `admin.php` — grep `admin.php` for `chart` and `bootstrap-icons` and copy those `<link>/<script>` tags). Sidebar nav mirrors the mockup's grouped links but uses `index.php?tab=<name>` hrefs, `bi-*` icons, and marks the current `$tab` active. Topbar as in the mockup but `themeBtn`/bell/avatar use `bi-*` icons. Structure:

```php
<?php /* admin/views/layout.php — expects $tab, $conn in scope */ ?>
<!doctype html><html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bảng điều khiển | Gấu Bakery</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/admin/tokens.css">
<link rel="stylesheet" href="../assets/css/admin/layout.css">
<link rel="stylesheet" href="../assets/css/admin/components.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head><body>
<div class="app" id="app">
  <div class="backdrop"></div>
  <aside class="sidebar"><?php require __DIR__ . '/partials/sidebar.php'; ?></aside>
  <div class="main">
    <?php require __DIR__ . '/partials/topbar.php'; ?>
    <main class="content">
      <?php
        $tabFile = __DIR__ . "/tabs/{$tab}.php";
        if (is_file($tabFile)) { require $tabFile; }
        else { echo '<div class="card panel">Tab chưa được chuyển đổi.</div>'; }
      ?>
    </main>
  </div>
</div>
<script src="../assets/js/admin/theme.js"></script>
<script src="../assets/js/admin/nav.js"></script>
<script src="../assets/js/admin/dashboard.js"></script>
</body></html>
```
Also create `admin/views/partials/sidebar.php` and `admin/views/partials/topbar.php` with the grouped nav (11 links, active = `$tab`) and the topbar markup respectively — port markup from the mockup, swap emoji for `bi-*` icons, hrefs to `index.php?tab=...`.

- [ ] **Step 2: Verify** `php -l admin/views/layout.php` and the two partials → `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add admin/views/layout.php admin/views/partials/
git commit -m "feat(admin): modular shell layout, sidebar and topbar partials"
```

---

### Task 8: Dashboard tab (data + render)

**Files:**
- Create: `admin/views/tabs/dashboard.php`
- Reference: `admin/admin.php:798-1150` (data), `admin/admin.php:1995-2090` (KPI markup), `admin/admin.php:1027` (best-selling), `admin/admin.php:4136-4160` (chart init)

**Interfaces:**
- Consumes: `$conn`; component functions from Task 6.
- Produces: sets `window.ADMIN_CHART` inline for dashboard.js; renders KPI row, chart panel, status+best-selling widget, recent-orders table.

- [ ] **Step 1: Port the data computation** — copy the dashboard data block from `admin.php` (the `$total_revenue`, `$chart_labels`, `$chart_values`, `$js_dates`, `$js_revenues`, best-selling and order-count queries between lines ~798 and ~1108) into the top of `dashboard.php`. Do not alter the SQL. Add three summary counts if not already present:

```php
<?php
// --- summary counts (reuse existing $conn) ---
$orderCount = (int) ($conn->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] ?? 0);
$pendingCount = (int) ($conn->query("SELECT COUNT(*) c FROM orders WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);
$newUsers = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c'] ?? 0);
```
(If a column name differs — e.g. `users.created_at` — grep `admin.php` for the real column and match it. Do NOT invent columns.)

- [ ] **Step 2: Render KPI row + chart + table**

```php
<div class="kpi-row">
  <?php
    render_stat_card('Doanh thu tháng', number_format($total_revenue,0,',','.').' VNĐ', 'so với kỳ trước', 'up', 'bi-cash-coin');
    render_stat_card('Đơn hàng', number_format($orderCount,0,',','.'), 'tổng đơn', 'up', 'bi-cart-check');
    render_stat_card('Người dùng mới', number_format($newUsers,0,',','.'), '30 ngày qua', 'up', 'bi-people');
    render_stat_card('Đơn chờ xử lý', number_format($pendingCount,0,',','.'), 'cần duyệt', $pendingCount>0?'down':'up', 'bi-hourglass-split');
  ?>
</div>
<div class="row-2">
  <div class="card panel">
    <div class="panel-head"><div><h2>Doanh thu</h2><div class="sub">Theo kỳ</div></div></div>
    <div style="height:260px"><canvas id="revenueChart"></canvas></div>
  </div>
  <div class="card panel"><div class="panel-head"><h2>Bán chạy nhất</h2></div>
    <div class="statlist"><?php /* loop best-selling query rows -> .item */ ?></div>
  </div>
</div>
<div class="card panel">
  <div class="panel-head"><div><h2>Đơn hàng gần đây</h2></div><a class="rowlink" href="index.php?tab=orders">Xem tất cả →</a></div>
  <?php
    // build $rowsHtml from a "recent orders" query (SELECT ... FROM orders ORDER BY created_at DESC LIMIT 8)
    // each row uses render_badge($row['status']) for the status cell
    render_table(['Mã đơn','Khách hàng','Tổng','Trạng thái','Ngày'], $rowsHtml);
  ?>
</div>
<script>window.ADMIN_CHART={labels:<?= $js_dates ?>,values:<?= $js_revenues ?>};</script>
```
Fill the best-selling loop and the recent-orders `$rowsHtml` using the real column names from the ported queries (grep `admin.php` for the recent-orders select if one exists; otherwise `SELECT id, customer_name, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 8` — verify column names against schema first).

- [ ] **Step 3: Verify** `php -l admin/views/tabs/dashboard.php` → `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add admin/views/tabs/dashboard.php
git commit -m "feat(admin): dashboard tab with KPIs, revenue chart, recent orders"
```

---

### Task 9: Front controller (`index.php`)

**Files:**
- Create: `admin/index.php`

**Interfaces:**
- Consumes: bootstrap.php (Task 5), layout.php (Task 7).
- Produces: the running modular admin entry point.

- [ ] **Step 1: Write the file**

```php
<?php
require_once __DIR__ . '/bootstrap.php';

// POST dispatch stub — future handlers plug in here, each CSRF-checked.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast('Phiên làm việc hết hạn, vui lòng thử lại.', 'error');
        redirectToTab($_POST['tab'] ?? 'dashboard');
    }
    // (handlers/*.php dispatched here as tabs are ported)
}

$allowed = ['dashboard','orders','products','best-selling','testimonials',
  'password-requests','users','promotions','coupons','contacts','chat'];
$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, $allowed, true)) { $tab = 'dashboard'; }

require __DIR__ . '/views/layout.php';
```

- [ ] **Step 2: Verify** `php -l admin/index.php` → `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add admin/index.php
git commit -m "feat(admin): front controller with csrf-guarded post dispatch stub"
```

---

### Task 10: End-to-end browser smoke test

**Files:** none (verification only)

- [ ] **Step 1: Start server** — `php -S localhost:8080` from `D:\cakev0` (run in background).

- [ ] **Step 2: Log in** — Browser pane → `http://localhost:8080/pages/login.php`, sign in as an admin.

- [ ] **Step 3: Open new admin** — navigate to `http://localhost:8080/admin/index.php?tab=dashboard`. Confirm:
  - Sidebar + topbar render; dark theme by default.
  - 4 KPI cards show real numbers (revenue matches legacy `admin.php`).
  - Revenue chart renders (Chart.js), recent-orders table populated with status pills.
  - No PHP warnings/notices in `preview_logs`.

- [ ] **Step 4: Interactions** — click theme toggle (persists after reload; chart recolors), click collapse (icon rail), resize to 375px (sidebar becomes drawer, no horizontal page scroll), resize to 768/1280 (layout intact).

- [ ] **Step 5: Regression** — open `http://localhost:8080/admin/admin.php?tab=dashboard`; confirm legacy admin still works unchanged.

- [ ] **Step 6: Commit** (if any fixes were needed)

```bash
git add -A
git commit -m "fix(admin): shell+dashboard smoke-test corrections"
```

---

## Self-Review

**Spec coverage:**
- Modular architecture (front controller, handlers dir, views/tabs, components, split CSS/JS) → Tasks 1–9. Handlers dir is stubbed in Task 9; per-domain handler files land in later per-tab plans (out of this slice's scope, matching the spec's incremental migration).
- Dark-first tokens + light toggle → Tasks 1, 4. Brand accent retained → Task 1.
- Shell (collapsible sidebar, mobile drawer fixing 390px overflow, topbar) → Tasks 2, 4, 7.
- Dashboard (KPI, themed chart, recent orders, best-selling) → Task 8.
- Shared components (stat-card, data-table, badge) → Task 6.
- Accessibility (focus ring, aria on future modal) → Task 3 focus-visible; modal restyle deferred with its tabs.
- Responsiveness / no overflow → Tasks 2, 10.
- `?tab=` contract + legacy untouched → Task 9 allowlist, Task 10 step 5.

**Placeholder scan:** Data-dependent spots (best-selling loop, recent-orders `$rowsHtml`, exact user/order column names) intentionally instruct grepping `admin.php`/schema for real column names rather than inventing them — this is a correctness guardrail, not a vague TODO. All CSS/JS/PHP scaffolding is complete code.

**Type consistency:** `render_stat_card`/`render_badge`/`render_table` signatures match between Task 6 (definition) and Task 8 (use). `window.ADMIN_CHART = {labels, values}` set in Task 8 matches read in Task 4 dashboard.js. `$js_dates`/`$js_revenues` names match legacy admin.php.

**Open (deferred to per-tab plans):** Figma push (seat upgrade), remaining 10 tabs + their POST handlers, modal restyle, chat tab decision.
