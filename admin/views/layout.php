<?php /* admin/views/layout.php — expects $tab, $conn in scope */ ?>
<!doctype html><html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bảng điều khiển | Gấu Bakery</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script>(function(){var t=localStorage.getItem('admin-theme');if(t){document.documentElement.setAttribute('data-theme',t);}})();</script>
<link rel="stylesheet" href="../assets/css/admin/tokens.css">
<link rel="stylesheet" href="../assets/css/admin/layout.css">
<link rel="stylesheet" href="../assets/css/admin/components.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
