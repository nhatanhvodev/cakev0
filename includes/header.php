<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
  session_start();
}
$baseConfigPath = dirname(__DIR__) . '/config/config.php';
if (!defined('BASE_URL') && file_exists($baseConfigPath)) {
  require_once $baseConfigPath;
}
$role = $_SESSION['role'] ?? 'guest';

// Lấy số loại sản phẩm (số dòng) trong giỏ hàng
$cartItemCount = 0;
$favoriteItemCount = 0;
$favoritesTableReady = false;
$passwordResetTableReady = false;
if (isset($conn)) {
  $favoriteTableResult = $conn->query("SHOW TABLES LIKE 'favorites'");
  if ($favoriteTableResult) {
    $favoritesTableReady = $favoriteTableResult->num_rows > 0;
  }

  $passwordResetTableResult = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
  if ($passwordResetTableResult) {
    $passwordResetTableReady = $passwordResetTableResult->num_rows > 0;
  }
}
if (isset($conn) && isset($_SESSION['user_id'])) {
  $uid = (int) $_SESSION['user_id'];
  $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
  if ($stmtCount) {
    $stmtCount->bind_param("i", $uid);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result()->fetch_assoc();
    $cartItemCount = (int) ($resCount['total'] ?? 0);
    $stmtCount->close();
  }

  if ($favoritesTableReady) {
    $stmtFavoriteCount = $conn->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
    if ($stmtFavoriteCount) {
      $stmtFavoriteCount->bind_param("i", $uid);
      $stmtFavoriteCount->execute();
      $favoriteResCount = $stmtFavoriteCount->get_result()->fetch_assoc();
      $favoriteItemCount = (int) ($favoriteResCount['total'] ?? 0);
      $stmtFavoriteCount->close();
    }
  }

  if ($passwordResetTableReady) {
    $stmtApprovedReset = $conn->prepare(
      "SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'approved' ORDER BY approved_at DESC, id DESC LIMIT 1"
    );

    if ($stmtApprovedReset) {
      $stmtApprovedReset->bind_param("i", $uid);
      $stmtApprovedReset->execute();
      $approvedReset = $stmtApprovedReset->get_result()->fetch_assoc();
      $stmtApprovedReset->close();

      if ($approvedReset) {
        $approvedId = (int) ($approvedReset['id'] ?? 0);
        if (!isset($_SESSION['seen_password_reset_approved_ids']) || !is_array($_SESSION['seen_password_reset_approved_ids'])) {
          $_SESSION['seen_password_reset_approved_ids'] = [];
        }

        if ($approvedId > 0 && !in_array($approvedId, $_SESSION['seen_password_reset_approved_ids'], true)) {
          $_SESSION['password_reset_approved_toast'] = true;
          $_SESSION['seen_password_reset_approved_ids'][] = $approvedId;

          // Giữ session gọn nhẹ nếu user có rất nhiều lần đổi mật khẩu.
          if (count($_SESSION['seen_password_reset_approved_ids']) > 30) {
            $_SESSION['seen_password_reset_approved_ids'] = array_slice($_SESSION['seen_password_reset_approved_ids'], -30);
          }
        }
      }
    }
  }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<?php if (!empty($extraLinks))
  echo $extraLinks; ?>

<style>
  :root {
    --header-bg: #ffffff;
    --header-text: #272727;
    --header-accent: #4a1d1f;
    --header-border: rgba(86, 178, 128, 0.2);
    --menu-bg: #fbedcd;
    --menu-surface: #ffffff;
    --menu-text: #2f3a37;
    --menu-soft: #ffffff;
    --header-sticky-offset: 75px;
  }

  html,
  body {
    max-width: 100%;
    overflow-x: hidden;
  }

  #site-header {
    background: var(--header-bg);
    width: 100%;
    margin: 0;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 2000;
    padding: 0;
    border-bottom: 0.5px solid var(--header-border);
    transition: all 0.3s ease;
  }

  #site-header.scrolled {
    box-shadow: 0 4px 20px rgba(74, 29, 31, 0.12);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(8px);
  }

  .header-inner {
    width: min(1200px, calc(100% - 32px));
    margin: 0 auto;
    height: 75px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
  }

  .header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    width: 100%;
  }

  .logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }

  .logo a {
    font-size: 18px;
    font-weight: 500;
    color: var(--header-accent);
    letter-spacing: 0.09em;
  }

  #main-nav {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: flex-start;
  }

  #main-nav ul {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0;
    white-space: nowrap;
  }

  .menu-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    min-width: auto;
    min-height: auto;
    border: 1px solid rgba(74, 29, 31, 0.22);
    border-radius: 999px;
    background: var(--menu-surface);
    color: var(--header-accent);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(74, 29, 31, 0.08);
    transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
  }

  .menu-toggle-btn i {
    font-size: 14px;
  }

  .menu-toggle-btn:hover {
    background: var(--header-accent);
    border-color: var(--header-accent);
    color: #fff;
    transform: translateY(-1px);
  }

  body {
    padding-top: 126px; /* Header (75px) + Menu (51px) */
  }

  .menu-container {
    width: 100%;
    background: #ffffff;
    border-bottom: 1px solid var(--header-border);
    position: fixed;
    top: 75px;
    left: 0;
    z-index: 1999;
    transition: all 0.3s ease;
  }

  .menu-container #main-nav {
    width: min(1200px, calc(100% - 32px));
    margin: 0 auto;
    padding: 10px 0;
  }

  .menu-tag-list {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: 12px;
    min-width: 0;
    flex: 1 1 auto;
    flex-wrap: nowrap;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  .menu-tag-list::-webkit-scrollbar {
    display: none;
  }

  .menu-search-tag {
    border: 1px solid rgba(74, 29, 31, 0.2);
    background: #fff;
    color: var(--header-accent);
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }

  .menu-search-tag:hover {
    background: var(--header-accent);
    color: #fff;
    border-color: var(--header-accent);
  }

  .catePanelJs {
    display: none;
    color: #fff;
    width: 100%;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    position: fixed;
    top: 126px;
    z-index: 1998;
    margin-top: 0;
    background: var(--menu-bg);
  }



  .catePanelJs.open {
    display: block;
    background: var(--menu-bg);
    color: var(--header-text);
    border-top: 1px solid var(--header-border);
    border-bottom: 1px solid var(--header-border);
  }

  .cate-panel-inner {
    width: min(1200px, calc(100% - 32px));
    margin: 0 auto;
    padding: 24px 0;
    display: grid;
    grid-template-columns: 260px 1fr 220px;
    gap: 24px;
    align-items: start;
  }

  .cate-panel-col {
    min-width: 0;
  }

  .cate-panel-col + .cate-panel-col {
    border-left: 1px solid rgba(74, 29, 31, 0.16);
    padding-left: 24px;
  }

  .cate-panel-links {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
  }

  .cate-panel-links a {
    color: var(--header-accent);
    text-decoration: none;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.35;
    transition: color 0.2s ease;
  }

  .cate-panel-links a:hover {
    color: var(--header-text);
  }

  .cate-panel-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 12px;
    opacity: 0.95;
    color: var(--header-accent);
  }

  .cate-panel-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 22px;
  }

  .cate-panel-grid a {
    color: var(--header-text);
    text-decoration: none;
    font-size: 17px;
    line-height: 1.4;
  }

  .cate-panel-grid a:hover {
    color: var(--header-accent);
  }

  .cate-panel-social {
    display: grid;
    gap: 10px;
    justify-items: start;
  }

  .cate-panel-social-links {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }

  .cate-panel-social-links a {
    color: #fff;
    background: var(--header-accent);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-shadow: 0 8px 16px rgba(74, 29, 31, 0.12);
    transition: background 0.2s ease;
  }

  .cate-panel-social-links a:hover {
    background: var(--header-text);
  }

  #main-nav a {
    font-size: 16px;
    font-weight: 500;
    color: var(--header-text);
    padding: 4px 2px;
    text-decoration: none;
    transition: 0.25s ease;
  }

  #main-nav a:hover,
  #main-nav a.active {
    color: var(--header-accent);
  }

  .nav-caret {
    font-size: 11px;
    margin-left: 4px;
    color: var(--header-text);
    vertical-align: middle;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
  }

  .search-box {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
  }

  .search-box input {
    padding: 0 52px 0 16px;
    border-radius: 24px;
    border: 0.5px solid var(--header-accent);
    outline: none;
    width: 100%;
    height: 45px;
    font-size: 16px;
    color: var(--header-text);
    line-height: 45px;
  }

  .search-box input::placeholder {
    color: #b1a7a3;
  }

  .search-box button {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    background: var(--header-accent);
    border: 0.5px solid var(--header-accent);
    cursor: pointer;
    color: #fbedcd;
    width: 45px;
    height: 45px;
    border-radius: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .search-result {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: 100%;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
    display: none;
    z-index: 20;
    max-height: min(60vh, 420px);
    overflow-y: auto;
  }

  .search-result div {
    padding: 10px;
    cursor: pointer;
  }

  .search-result div:hover {
    background: #f5f5f5;
  }

  .search-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px;
    text-decoration: none;
    color: #333;
    min-width: 0;
  }

  .search-item img {
    width: 56px;
    height: 56px;
    min-width: 56px;
    min-height: 56px;
    object-fit: cover;
    border-radius: 8px;
    background: #f2f2f2;
  }

  .search-item:hover {
    background: #f5f5f5;
  }

  .search-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
    flex: 1 1 auto;
  }

  .search-name {
    font-size: 14px;
    font-weight: 500;
    line-height: 1.3;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  .search-price {
    font-size: 13px;
    color: #8b4513;
    overflow-wrap: anywhere;
  }

  #user-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
    flex-shrink: 0;
  }

  #user-actions a {
    color: var(--header-text);
    font-size: 22px;
    transition: 0.25s ease;
  }

  #user-actions a:hover {
    color: var(--header-accent);
  }

  .cart-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
  }

  .favorite-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
  }

  .cart-badge {
    position: absolute;
    top: -8px;
    right: -10px;
    background: #ff0000;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
  }

  .favorite-badge {
    position: absolute;
    top: -8px;
    right: -10px;
    background: #ff0000;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
  }

  @media (max-width: 900px) {
    :root {
      --header-sticky-offset: 68px;
    }

    .header-inner {
      width: calc(100% - 32px);
      height: 64px;
      padding: 0;
      flex-wrap: nowrap;
    }

    body {
      padding-top: 112px; /* 64px header + 48px menu */
    }

    .menu-container {
      top: 64px;
    }

    .menu-container #main-nav {
      padding: 8px 0;
    }

    .catePanelJs {
      top: 112px;
    }

    .logo a {
      font-size: 20px;
      white-space: nowrap;
    }

    .logo {
      order: 1;
      align-self: center;
    }

    #main-nav {
      order: 2;
      flex: 1 1 auto;
      min-width: 0;
      justify-content: flex-start;
    }

    #main-nav ul {
      gap: 16px;
      flex-wrap: nowrap;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .menu-toggle-btn {
      font-size: 14px;
    }

    .cate-panel-inner {
      width: calc(100% - 32px);
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
      padding: 18px 0;
    }

    .cate-panel-col + .cate-panel-col {
      border: 0;
      padding: 0;
    }

    .cate-panel-col:nth-child(2) {
      border-left: 1px solid rgba(74, 29, 31, 0.16);
      padding-left: 16px;
    }

    .cate-panel-col:nth-child(3) {
      grid-column: 1 / -1;
      border-top: 1px solid rgba(74, 29, 31, 0.16);
      padding-top: 14px;
    }

    .cate-panel-links a {
      font-size: 18px;
    }

    .cate-panel-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px 16px;
    }

    .cate-panel-grid a {
      font-size: 15px;
    }

    #main-nav ul::-webkit-scrollbar {
      display: none;
    }

    .header-actions {
      order: 3;
      min-width: 0;
      width: auto;
      justify-content: flex-start;
      flex-wrap: nowrap;
      gap: 10px;
      margin-left: auto;
    }

    .search-box {
      flex: 1 1 auto;
      min-width: 0;
      width: auto;
      max-width: none;
    }

    #user-actions {
      margin-left: auto;
    }

    .search-box input {
      width: 100%;
      height: 40px;
      font-size: 14px;
      line-height: 40px;
    }

    .search-box button {
      width: 40px;
      height: 40px;
    }
  }

  @media (max-width: 600px) {
    .header-inner {
      height: 60px;
    }
    body {
      padding-top: 104px; /* 60px header + 44px menu */
    }
    .menu-container {
      top: 60px;
    }
    .catePanelJs {
      top: 104px;
    }

    .logo a {
      font-size: 17px;
    }

    .header-top {
      gap: 6px;
    }

    #main-nav a {
      font-size: 14px;
    }

    #user-actions {
      gap: 8px;
    }

    #user-actions a {
      font-size: 18px;
    }

    .cate-panel-links a {
      font-size: 16px;
    }

    .menu-container #main-nav {
      width: calc(100% - 32px);
      padding: 8px 0;
    }

    .menu-toggle-btn {
      width: 100%;
      justify-content: center;
    }

    .menu-tag-list {
      margin-left: 8px;
      gap: 6px;
    }

    .menu-search-tag {
      font-size: 12px;
      padding: 6px 10px;
    }

    .search-result {
      border-radius: 16px;
    }

    .search-item {
      gap: 8px;
      padding: 10px;
    }

    .search-item img {
      width: 52px;
      height: 52px;
      min-width: 52px;
      min-height: 52px;
    }

    .search-name {
      font-size: 13px;
    }

    .search-price {
      font-size: 12px;
    }
  }

  @media (max-width: 480px) {
    .header-inner {
      width: calc(100% - 20px);
      gap: 8px;
    }

    .header-top,
    .header-actions {
      gap: 8px;
    }

    .logo a {
      font-size: 15px;
      letter-spacing: 0.05em;
    }

    .search-box input {
      padding: 0 42px 0 12px;
      height: 38px;
      font-size: 13px;
      border-radius: 20px;
    }

    .search-box button {
      width: 38px;
      height: 38px;
      border-radius: 20px;
    }

    #user-actions {
      gap: 7px;
    }

    #user-actions a,
    .notify-box > i {
      font-size: 17px;
    }

    .cart-badge,
    .favorite-badge {
      top: -6px;
      right: -8px;
      min-width: 16px;
      font-size: 10px;
      padding: 1px 5px;
    }

    .menu-container #main-nav {
      width: calc(100% - 20px);
    }

    .menu-tag-list {
      margin-left: 6px;
    }

    .menu-search-tag {
      padding: 6px 9px;
      font-size: 11px;
    }

    .cate-panel-inner {
      width: calc(100% - 20px);
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 14px 12px;
      align-items: start;
      padding: 16px 0;
    }

    .cate-panel-col {
      min-width: 0;
    }

    .cate-panel-col:nth-child(2) {
      border-left: 1px solid rgba(74, 29, 31, 0.16);
      padding-left: 12px;
    }

    .cate-panel-col:nth-child(3) {
      grid-column: 1 / -1;
      border-top: 1px solid rgba(74, 29, 31, 0.16);
      padding-top: 12px;
    }

    .cate-panel-links {
      gap: 10px;
    }

    .cate-panel-links a {
      font-size: 14px;
      line-height: 1.3;
    }

    .cate-panel-title {
      font-size: 12px;
      margin-bottom: 10px;
    }

    .cate-panel-grid {
      grid-template-columns: 1fr;
      gap: 8px;
    }

    .cate-panel-grid a {
      font-size: 14px;
      line-height: 1.35;
    }

    .cate-panel-social {
      gap: 8px;
    }

    .cate-panel-social-links {
      gap: 8px;
      flex-wrap: wrap;
    }

    .cate-panel-social-links a {
      width: 34px;
      height: 34px;
    }

    .notify-list {
      right: -6px;
      min-width: min(230px, calc(100vw - 24px));
    }
  }

  @media (max-width: 380px) {
    .header-inner {
      width: calc(100% - 16px);
    }

    .logo a {
      font-size: 14px;
      letter-spacing: 0.03em;
    }

    .search-box input {
      font-size: 12px;
      padding-left: 10px;
    }

    #user-actions {
      gap: 6px;
    }

    #user-actions a,
    .notify-box > i {
      font-size: 16px;
    }

    .menu-container #main-nav,
    .cate-panel-inner {
      width: calc(100% - 16px);
    }
  }

  .cart-badge.pop {
    transform: scale(1.3);
  }

  .favorite-badge.pop {
    transform: scale(1.3);
  }

  .notify-box {
    position: relative;
    display: inline-flex;
    align-items: center;
  }

  .notify-box > i {
    color: var(--header-text);
    font-size: 20px;
    cursor: pointer;
    transition: color 0.2s ease;
  }

  .notify-box > i:hover {
    color: var(--header-accent);
  }

  .notify-list {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 230px;
    background: #fff;
    border: 1px solid rgba(74, 29, 31, 0.15);
    border-radius: 12px;
    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.14);
    padding: 8px;
    z-index: 1200;
  }

  .notify-list div {
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: #4a1d1f;
    white-space: nowrap;
  }

  .notify-list div + div {
    margin-top: 4px;
  }

</style>


<header id="site-header">
  <div class="header-inner">
    <div class="header-top">
      <div class="logo">
        <a href="<?= BASE_URL ?>index.php">Gấu Bakery</a>
      </div>
      <div class="header-actions">
        <div class="search-box">
          <input type="text" id="searchInput" placeholder="Bạn muốn tìm bánh gì...">
          <button type="button" id="searchBtn">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
          <div class="search-result" id="searchResult"></div>
        </div>
        <div id="user-actions">
          <a href="<?= BASE_URL ?>pages/account.php"><i class="fa-regular fa-user"></i></a>
          <a href="<?= BASE_URL ?>pages/favorites.php" class="favorite-wrapper" aria-label="Sản phẩm yêu thích">
            <i class="fa-regular fa-heart"></i>
            <span id="header-favorite-badge" class="favorite-badge"
              style="<?= $favoriteItemCount > 0 ? '' : 'display:none;' ?>"><?= $favoriteItemCount ?></span>
          </a>
          <a href="<?= BASE_URL ?>pages/cart.php" class="cart-wrapper">
            <i class="fa-solid fa-cart-shopping"></i>
            <span id="header-cart-badge" class="cart-badge"
              style="<?= $cartItemCount > 0 ? '' : 'display:none;' ?>"><?= $cartItemCount ?></span>
          </a>
          <div class="notify-box">
            <i class="fa-solid fa-bell" onclick="toggleNotify()"></i>
            <div class="notify-list" id="notifyList">
              <?php if ($role === 'admin'): ?>
                <div><i class="fa-solid fa-box-open" style="color: #8b4513;"></i> Có đơn hàng mới</div>
              <?php elseif ($role === 'user'): ?>
                <div><i class="fa-solid fa-truck-fast" style="color: #8b4513;"></i> Đơn hàng đang giao</div>
              <?php else: ?>
                <div><i class="fa-solid fa-circle-info" style="color: #8b4513;"></i> Cập nhật mới từ Gấu Bakery</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
  // Script xử lý hiệu ứng cuộn cho Header
  window.addEventListener('scroll', function() {
    const header = document.getElementById('site-header');
    if (window.scrollY > 10) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  });
</script>

<div class="menu-container">
  <nav id="main-nav">
    <ul>
      <li>
        <button type="button" class="menu-toggle-btn" id="menuToggleBtn" aria-expanded="false" aria-controls="catePanelJs">
          <i class="fa-solid fa-bars"></i>
          <span>Menu</span>
        </button>
      </li>
      <li class="menu-tag-list" aria-label="Danh mục tìm kiếm nhanh">
        <button type="button" class="menu-search-tag" data-search-keyword="Bánh Kem">Bánh Kem</button>
        <button type="button" class="menu-search-tag" data-search-keyword="Bánh Kem Bắp">Bánh Kem Bắp</button>
        <button type="button" class="menu-search-tag" data-search-keyword="Cheese Cake">CheeseCake</button>
        <button type="button" class="menu-search-tag" data-search-keyword="Bánh Cưới">Bánh Cưới</button>
        <button type="button" class="menu-search-tag" data-search-keyword="Bánh Nướng">Bánh Nướng</button>
        <button type="button" class="menu-search-tag" data-search-keyword="Muffin">Muffin</button>
      </li>
    </ul>
  </nav>
</div>

<section class="catePanelJs" id="catePanelJs" aria-hidden="true">
  <div class="cate-panel-inner">
    <div class="cate-panel-col">
      <ul class="cate-panel-links">
        <li><a href="<?= BASE_URL ?>index.php">Trang chủ</a></li>
        <li><a href="<?= BASE_URL ?>pages/product.php">Sản phẩm</a></li>
        <li><a href="<?= BASE_URL ?>pages/favorites.php">Sản phẩm đã lưu</a></li>
        <li><a href="<?= BASE_URL ?>pages/about.php">Về chúng tôi</a></li>
        <li><a href="<?= BASE_URL ?>pages/contact.php">Liên hệ với chúng tôi</a></li>
      </ul>
    </div>

    <div class="cate-panel-col">
      <div class="cate-panel-title"><i class="fa-solid fa-cake-candles"></i> Danh mục nổi bật</div>
      <div class="cate-panel-grid">
        <a href="<?= BASE_URL ?>pages/product.php?loai=kem">Bánh Kem</a>
        <a href="<?= BASE_URL ?>pages/product.php?loai=ngot">Bánh Ngọt</a>
        <a href="<?= BASE_URL ?>pages/product.php?loai=man">Bánh Mặn</a>
        <a href="<?= BASE_URL ?>pages/product.php?loai=mi">Bánh Mì</a>
        <a href="<?= BASE_URL ?>pages/product.php?loai=khuyenmai">Bánh đang khuyến mãi</a>
      </div>
    </div>

    <div class="cate-panel-col cate-panel-social">
      <div class="cate-panel-title">Liên hệ với chúng tôi</div>
      <div class="cate-panel-social-links">
        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a>
        <a href="#" aria-label="Tiktok"><i class="fa-brands fa-tiktok"></i></a>
        <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
      </div>
    </div>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  (function () {
    const menuBtn = document.getElementById('menuToggleBtn');
    const panel = document.getElementById('catePanelJs');
    if (!menuBtn || !panel) return;

    function closePanel() {
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
      menuBtn.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
      const isOpen = panel.classList.toggle('open');
      panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      togglePanel();
    });

    document.addEventListener('click', function (e) {
      if (!panel.contains(e.target) && e.target !== menuBtn) {
        closePanel();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closePanel();
      }
    });
  })();

  $(document).ready(function () {
    const searchEndpoint = <?= json_encode(base_url('index.php')) ?>;
    const productBaseUrl = <?= json_encode(rtrim(BASE_URL, '/') . '/product/') ?>;

    function doAutocomplete() {
      let keyword = $("#searchInput").val().trim();
      if (keyword.length < 2) {
        $("#searchResult").hide().html("");
        return;
      }

      $.ajax({
        url: searchEndpoint,
        method: "POST",
        data: {
          search_products: true,
          keyword: keyword
        },
        dataType: "json",
        success: function (res) {
          if (res.success && res.products.length > 0) {
            let html = "";
            res.products.forEach(p => {
              html += `
<a class="search-item" href="${productBaseUrl}${encodeURIComponent(p.slug)}">
  <img src="${p.hinh_anh}" alt="${p.ten_banh}">
  <div class="search-info">
    <div class="search-name">${p.ten_banh}</div>
    <small class="search-price">${p.formatted_price}</small>
  </div>
</a>`;
            });
            $("#searchResult").html(html).show();
          } else {
            $("#searchResult").html("<div style='padding:10px'>Không tìm thấy</div>").show();
          }
        }
      });
    }

    function submitSearch(keywordOverride) {
      let keyword = '';
      if (typeof keywordOverride === 'string') {
        keyword = keywordOverride.trim();
      } else {
        keyword = ($("#searchInput").val() || '').trim();
      }
      if (!keyword) return;
      window.location.href = "<?= BASE_URL ?>pages/product.php?search=" + encodeURIComponent(keyword);
    }

    $("#searchInput").on("input", function () {
      doAutocomplete();
    });

    $("#searchInput").on("keydown", function (e) {
      if (e.key === "Enter" || e.which === 13) {
        e.preventDefault();
        submitSearch();
      }
    });

    $("#searchBtn").on("click", function (e) {
      e.preventDefault();
      submitSearch();
    });

    $(document).on("click", ".menu-search-tag", function () {
      const keyword = ($(this).data("search-keyword") || "").toString().trim();
      if (!keyword) return;
      $("#searchInput").val(keyword);
      submitSearch(keyword);
    });

    $(document).on("click", function (e) {
      if (!$(e.target).closest(".search-box").length) {
        $("#searchResult").hide();
      }
    });
  });

  function toggleNotify() {
    const box = document.getElementById("notifyList");
    box.style.display = box.style.display === "block" ? "none" : "block";
  }
</script>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<style>
  /* ===== Custom Confirm Dialog ===== */
  .confirm-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    backdrop-filter: blur(4px);
    z-index: 99999;
    justify-content: center;
    align-items: center;
  }

  .confirm-overlay.active {
    display: flex;
  }

  .confirm-box {
    background: #fff;
    border-radius: 22px;
    padding: 32px 28px 24px;
    max-width: 360px;
    width: 90%;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
    animation: confirmPop .25s cubic-bezier(.34, 1.56, .64, 1);
  }

  @keyframes confirmPop {
    from {
      transform: scale(.85);
      opacity: 0;
    }

    to {
      transform: scale(1);
      opacity: 1;
    }
  }

  .confirm-icon {
    font-size: 40px;
    margin-bottom: 14px;
    color: #4a1d1f;
  }

  .confirm-box h4 {
    margin: 0 0 8px;
    font-size: 18px;
    color: #222;
  }

  .confirm-box p {
    margin: 0 0 22px;
    color: #666;
    font-size: 15px;
    line-height: 1.5;
  }

  .confirm-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
  }

  .confirm-actions button {
    flex: 1;
    max-width: 140px;
    padding: 11px 0;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
  }

  .btn-confirm-ok {
    background:  #4a1d1f;
    color: #fff;
    box-shadow: 0 4px 12px rgba(255, 107, 156, .3);
  }

  .btn-confirm-ok:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 107, 156, .4);
  }

  .btn-confirm-cancel {
    background: #f0f0f0;
    color: #555;
  }

  .btn-confirm-cancel:hover {
    background: #e0e0e0;
  }
</style>

<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <h4 id="confirmTitle">Xác nhận</h4>
    <p id="confirmMsg">Bạn có chắc chắn muốn thực hiện hành động này?</p>
    <div class="confirm-actions">
      <button class="btn-confirm-cancel" id="confirmCancelBtn">Hủy</button>
      <button class="btn-confirm-ok" id="confirmOkBtn">Xác nhận</button>
    </div>
  </div>
</div>

<script>
  window.showToast = function (msg, type) {
    type = type || 'success';
    let config = {
      success: { bg: 'linear-gradient(135deg, #4a1d1f, #6a2d22)', icon: '✓' },
      error: { bg: 'linear-gradient(135deg, #b42318, #f04438)', icon: '✕' },
      info: { bg: 'linear-gradient(135deg, #1d4ed8, #3b82f6)', icon: 'ℹ' },
      warning: { bg: 'linear-gradient(135deg, #b45309, #f59e0b)', icon: '⚠' },
    };
    let c = config[type] || config.success;
    Toastify({
      text: c.icon + ' ' + msg,
      duration: 3500,
      close: true,
      gravity: 'top',
      position: 'right',
      style: {
        background: c.bg,
        borderRadius: '14px',
        fontFamily: "'Poppins', sans-serif",
        fontWeight: '600',
        fontSize: '14px',
        padding: '14px 20px',
        boxShadow: '0 8px 24px rgba(0,0,0,.18)',
        minWidth: '260px',
      }
    }).showToast();
  };

  <?php if (!empty($_SESSION['toast'])):
    $toast = $_SESSION['toast']; ?>
    window.showToast(<?= json_encode($toast['msg'] ?? '') ?>, <?= json_encode($toast['type'] ?? 'success') ?>);
    <?php unset($_SESSION['toast']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['password_reset_approved_toast'])): ?>
    window.showToast('Đã đổi mật khẩu thành công!', 'success');
    <?php unset($_SESSION['password_reset_approved_toast']); ?>
  <?php endif; ?>

  <?php if (!empty($_GET['toast']) && $_GET['toast'] === 'logout'): ?>
    window.showToast('Đăng xuất thành công!', 'success');
    if (history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('toast');
      history.replaceState({}, document.title, url.toString());
    }
  <?php endif; ?>

  window.showConfirm = function (message, title) {
    title = title || 'Xác nhận';
    return new Promise(function (resolve) {
      const overlay = document.getElementById('confirmOverlay');
      document.getElementById('confirmTitle').innerText = title;
      document.getElementById('confirmMsg').innerText = message;
      overlay.classList.add('active');

      const okBtn = document.getElementById('confirmOkBtn');
      const cancelBtn = document.getElementById('confirmCancelBtn');

      function cleanup() {
        overlay.classList.remove('active');
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
      }
      function onOk() { cleanup(); resolve(true); }
      function onCancel() { cleanup(); resolve(false); }

      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { cleanup(); resolve(false); }
      }, { once: true });
    });
  };

  window.setCartBadge = function (count) {
    let badge = document.getElementById('header-cart-badge');
    if (!badge) return;
    let n = parseInt(count) || 0;
    if (n > 0) {
      badge.innerText = n;
      badge.style.display = 'inline-block';
      badge.classList.add('pop');
      setTimeout(function () { badge.classList.remove('pop'); }, 300);
    } else {
      badge.style.display = 'none';
      badge.innerText = '0';
    }
  };

  window.updateCartBadge = function (isNew) {
    if (!isNew) return;
    let badge = document.getElementById('header-cart-badge');
    if (!badge) return;
    let current = parseInt(badge.innerText || '0');
    window.setCartBadge(current + 1);
  };

  window.setFavoriteBadge = function (count) {
    let badge = document.getElementById('header-favorite-badge');
    if (!badge) return;
    let n = parseInt(count) || 0;
    if (n > 0) {
      badge.innerText = n;
      badge.style.display = 'inline-block';
      badge.classList.add('pop');
      setTimeout(function () { badge.classList.remove('pop'); }, 300);
    } else {
      badge.style.display = 'none';
      badge.innerText = '0';
    }
  };
</script>
