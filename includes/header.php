<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$baseConfigPath = dirname(__DIR__) . '/config/config.php';
if (!defined('BASE_URL') && file_exists($baseConfigPath)) {
  require_once $baseConfigPath;
}
$role = $_SESSION['role'] ?? 'guest';

// Lấy số loại sản phẩm (số dòng) trong giỏ hàng
$cartItemCount = 0;
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
  }

  #site-header {
    background: var(--header-bg);
    width: 100%;
    margin: 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    padding: 0;
    border-bottom: 0.5px solid var(--header-border);
  }

  #site-header.scrolled .header-inner {
    transition: 0.3s ease;
  }

  .header-inner {
    width: min(1200px, calc(100% - 104px));
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
    justify-content: flex-start;
    gap: 16px;
    width: 100%;
  }

  .logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }

  .logo a {
    font-size: 30px;
    font-weight: 500;
    color: var(--header-accent);
    letter-spacing: 0.09em;
  }

  #main-nav {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #main-nav ul {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 46px;
    white-space: nowrap;
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
    flex-shrink: 0;
  }

  .search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
  }

  .search-box input {
    padding: 0 52px 0 16px;
    border-radius: 0;
    border: 0.5px solid var(--header-accent);
    outline: none;
    width: 251px;
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
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .search-result {
    position: absolute;
    top: 40px;
    left: 0;
    width: 100%;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
    display: none;
    z-index: 20;
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
    align-items: center;
    gap: 10px;
    padding: 8px;
    text-decoration: none;
    color: #333;
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
  }

  .search-name {
    font-size: 14px;
    font-weight: 500;
    line-height: 1.3;
  }

  .search-price {
    font-size: 13px;
    color: #8b4513;
  }

  #user-actions {
    display: flex;
    align-items: center;
    gap: 12px;
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

  @media (max-width: 900px) {
    .header-inner {
      width: calc(100% - 32px);
      height: auto;
      padding: 12px 0;
      flex-wrap: nowrap;
    }

    .header-top {
      flex-wrap: nowrap;
      gap: 10px;
    }

    .logo a {
      font-size: 24px;
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

    #main-nav ul::-webkit-scrollbar {
      display: none;
    }

    .header-actions {
      order: 3;
      width: auto;
      justify-content: flex-start;
      flex-wrap: nowrap;
      gap: 10px;
      margin-left: auto;
    }

    .search-box {
      flex: 1 1 auto;
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
    #main-nav a {
      font-size: 14px;
    }

    #user-actions a {
      font-size: 20px;
    }

    .search-box input {
      width: 200px;
    }
  }

  .cart-badge.pop {
    transform: scale(1.3);
  }

  .notify-box {
    display: none;
  }

</style>

<header id="site-header">
  <div class="header-inner">
    <div class="header-top">
      <div class="logo">
        <a href="<?= BASE_URL ?>index.php">Gấu Bakery</a>
      </div>

      <nav id="main-nav">
        <ul>
          <li><a href="<?= BASE_URL ?>index.php">Trang chủ</a></li>
          <li><a href="<?= BASE_URL ?>pages/product.php">Menu Bánh</a></li>
          <li><a href="<?= BASE_URL ?>pages/about.php">Về chúng tôi</a></li>
          <li><a href="<?= BASE_URL ?>pages/contact.php">Liên hệ với chúng tôi</a></li>
        </ul>
      </nav>

      <div class="header-actions">
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Tìm kiếm">
        <button type="button" id="searchBtn">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        <div class="search-result" id="searchResult"></div>
      </div>

      <div id="user-actions">
        <a href="<?= BASE_URL ?>pages/account.php"><i class="fa-regular fa-user"></i></a>
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
              <div class="notification-item" onclick="goToEvent()">
                <i class="fa-solid fa-champagne-glasses" style="color: #ffb703;"></i> Có sự kiện mới
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function () {
    function doAutocomplete() {
      let keyword = $("#searchInput").val().trim();
      if (keyword.length < 2) {
        $("#searchResult").hide().html("");
        return;
      }

      $.ajax({
        url: "/Cake/index.php",
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
<a class="search-item" href="/Cake/product/${encodeURIComponent(p.slug)}">
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

    function submitSearch() {
      let keyword = $("#searchInput").val().trim();
      if (!keyword) return;
      window.location.href = "<?= BASE_URL ?>pages/product.php?search=" + encodeURIComponent(keyword);
    }

    $("#searchInput").on("keyup", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        submitSearch();
        return;
      }
      doAutocomplete();
    });

    $("#searchBtn").on("click", submitSearch);

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
  function goToEvent() {
    document.getElementById("notifyList").style.display = "none";
    window.location.href = "/Cake/pages/events.php";
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
</script>