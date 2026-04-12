
<?php

session_start();
ob_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$pageTitle = 'Giỏ hàng';

require_once '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];

if (isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'];

    if ($action === 'add') {
        $banh_id = (int)$_POST['banh_id'];
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        if ($banh_id <= 0) {
            echo json_encode(['success' => false]);
            exit;
        }

        $check = $conn->query("
            SELECT id FROM cart 
            WHERE user_id = $user_id AND banh_id = $banh_id
        ");

        $is_new = ($check->num_rows === 0);

        if (!$is_new) {
            $conn->query("
                UPDATE cart 
                SET quantity = quantity + $qty
                WHERE user_id = $user_id AND banh_id = $banh_id
            ");
        } else {
            $conn->query("
                INSERT INTO cart (user_id, banh_id, quantity)
                VALUES ($user_id, $banh_id, $qty)
            ");
        }

        // Dếm lại số loại sản phẩm (số dòng) trong giỏ
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM cart WHERE user_id = $user_id");
        $cartCount = (int)($countRes->fetch_assoc()['cnt'] ?? 0);

        $msg = $is_new ? 'Đã thêm sản phẩm vào giỏ hàng.' : 'Đã tăng số lượng trong giỏ.';
        echo json_encode(['success' => true, 'is_new' => $is_new, 'cart_count' => $cartCount, 'message' => $msg]);
        exit;
    }
if ($action === 'add_custom') {
    $cart_id = (int)$_POST['cart_id'];
    $add_qty = max(1, (int)$_POST['add_qty']);

    $conn->query("
        UPDATE cart 
        SET quantity = quantity + $add_qty
        WHERE id = $cart_id AND user_id = $user_id
    ");

    echo json_encode(['success' => true, 'message' => 'Đã cập nhật số lượng sản phẩm.']);
    exit;
}

    $cart_id = (int)$_POST['cart_id'];

    if ($action === 'increase') {
        $conn->query("
            UPDATE cart 
            SET quantity = quantity + 1 
            WHERE id = $cart_id AND user_id = $user_id
        ");
    }

    if ($action === 'decrease') {

    $res = $conn->query("
        SELECT quantity FROM cart 
        WHERE id = $cart_id AND user_id = $user_id
    ");

    if ($res && $row = $res->fetch_assoc()) {
        if ($row['quantity'] > 1) {

            $conn->query("
                UPDATE cart 
                SET quantity = quantity - 1
                WHERE id = $cart_id AND user_id = $user_id
            ");
        } else {

            $conn->query("
                DELETE FROM cart 
                WHERE id = $cart_id AND user_id = $user_id
            ");
        }
    }
}

    if ($action === 'remove') {
        $conn->query("
            DELETE FROM cart 
            WHERE id = $cart_id AND user_id = $user_id
        ");
    }

    $actionMessages = [
        'increase' => 'Đã tăng số lượng sản phẩm.',
        'decrease' => 'Đã cập nhật số lượng sản phẩm.',
        'remove' => 'Đã xóa sản phẩm khỏi giỏ hàng.'
    ];
    echo json_encode(['success' => true, 'message' => $actionMessages[$action] ?? 'Đã cập nhật giỏ hàng.']);
    exit;
}

function buildImageUrl($path) {
    if (!$path) return '/Cake/assets/img/no-image.jpg';
    if (strpos($path, 'admin/img/') === 0 || strpos($path, 'admin/') === 0) {
        return '/Cake/' . ltrim($path, '/');
    }
    if (strpos($path, 'assets/') === false && strpos($path, 'img/') === 0) {
        $path = str_replace('img/', 'assets/img/', $path);
    }
    return '/Cake/' . ltrim($path, '/');
}

$sql = "SELECT c.id AS cart_id, c.quantity, b.ten_banh, b.hinh_anh, b.gia 
        FROM cart c 
        JOIN banh b ON c.banh_id = b.id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['gia'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        
        body {
            background: #ffffff;
            color: #272727;
            font-family: 'Poppins', sans-serif;
        }

        .cart-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 28px;
            border: 1px solid #f3e0be;
            box-shadow: 0 20px 40px rgba(74, 29, 31, 0.12);
            position: relative;
            overflow: hidden;
            margin-top: 20px;
        }

        .cart-card::before {
            content: "";
        }

        .cart-title {
            font-size: 26px; font-weight: 700; color: #4a1d1f;
            margin-bottom: 26px; display: flex; align-items: center; gap: 10px; padding-left: 20px;
        }

        table thead th { background: #fdf1db; color: #4a1d1f; font-weight: 600; }
        table tbody tr:hover { background: #fff8ee; transition: .2s; }
        table img { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }

        .qty-btn { width: 32px; height: 32px; padding: 0; border-radius: 50%; }

        .summary-box {
            background: #fdf7ef; padding: 24px;
            border-radius: 18px; border: 1.5px dashed #4a1d1f;
        }
        .summary-box h5 { font-weight: 700; color: #4a1d1f; display: flex; align-items: center; gap: 8px; }

        .checkout-btn {
            background: #4a1d1f;
            border: none; box-shadow: 0 12px 24px rgba(74, 29, 31, 0.25);
        }
        .checkout-btn:hover {
            background: #2f1415; transform: translateY(-2px);
        }

        .empty-cart {
            background: #fff; padding: 60px; border-radius: 20px;
            text-align: center; box-shadow: 0 14px 30px rgba(74, 29, 31, 0.12);
        }

        @media (max-width: 768px) {
            .cart-card {
                padding: 20px;
                border-radius: 20px;
            }

            .cart-title {
                font-size: 22px;
                padding-left: 0;
            }

            table img {
                width: 54px;
            }

            .qty-btn {
                width: 28px;
                height: 28px;
            }
        }

        @media (max-width: 576px) {
            .cart-card {
                padding: 16px;
            }

            .table-responsive {
                margin: 0 -8px;
            }

            table {
                font-size: 13px;
            }

            .summary-box {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<section class="pt-5 pb-5">
    <div class="container-xl">
        <div class="cart-card">
            <div class="cart-title">Giỏ hàng của bạn</div>

            <div class="table-responsive mb-4">
                <table class="table align-middle text-center">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-circle-xmark" style="color: #000000;"></i></th>
                            <th><i class="fa-regular fa-image" style="color: #000000;"></i></th>
                            <th>Sản phẩm</th>
                            <th><i class="fa-solid fa-tags" style="color: #000000;"></i> Đơn giá</th>
                            <th><i class="fa-solid fa-box-open" style="color: #000000;"></i> Số lượng</th>
                            <th><i class="fa-solid fa-money-bill-wave" style="color: #000000;"></i> Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cartItems)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-cart text-muted fs-5" style="box-shadow:none;">
                                        <div style="font-size:48px"><i class="fa-solid fa-basket-shopping" style="color: #8b4513;"></i></div>
                                        <p class="mt-3">Giỏ hàng của bạn đang trống!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cartItems as $item): 
                                $rowTotal = $item['gia'] * $item['quantity'];
                            ?>
                            <tr>
                                
                                <td>
                                    <button class="btn btn-sm btn-danger btn-remove" data-id="<?= $item['cart_id'] ?>"><i class="fa-solid fa-trash-can"></i>️</button>
                                </td>
                                
                                <td>
                                    <img src="<?= buildImageUrl($item['hinh_anh']) ?>" width="70" alt="Bánh">
                                </td>
                                
                                <td><?= htmlspecialchars($item['ten_banh']) ?></td>
                                
                                <td><?= number_format($item['gia'], 0, ',', '.') ?> VNĐ</td>
                                
                                <td>
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <button class="btn btn-sm btn-outline-secondary qty-btn btn-decrease" data-id="<?= $item['cart_id'] ?>">−</button>
                                        <span class="fw-bold" style="min-width:20px;"><?= $item['quantity'] ?></span>
                                        <button class="btn btn-sm btn-outline-secondary qty-btn btn-increase" data-id="<?= $item['cart_id'] ?>">+</button>

                                    </div>
                                </td>
                                
                                <td class="fw-bold text-success">
                                    <?= number_format($rowTotal, 0, ',', '.') ?> VNĐ
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($cartItems)): ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="input-group mb-4">
                        <input type="text" class="form-control" placeholder="Nhập mã giảm giá">
                        <button class="btn btn-outline-secondary">Áp dụng</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="summary-box">
                        <h5><i class="fa-solid fa-money-bills" style="color: #000000;"></i> Tổng giỏ hàng</h5>
                        <table class="table mb-3">
                            <tr>
                                <td>Tạm tính</td>
                                <td class="text-end"><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</td>
                            </tr>
                            <tr class="fw-bold">
                                <td>Tổng cộng</td>
                                <td class="text-end text-danger"><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</td>
                            </tr>
                        </table>
                        <div class="d-flex justify-content-between">
                            <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="fa-solid fa-arrows-rotate"></i> Cập nhật</button>
                            <a href="/Cake/pages/checkout.php" class="btn checkout-btn text-white px-4"><i class="fa-regular fa-credit-card" style="color: #ffffff;"></i> Thanh toán</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include '../includes/footer.html'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    function ajaxCart(action, cartId) {

        document.body.style.cursor = 'wait';
        
        fetch('cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&cart_id=${cartId}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                if (data.message) {
                    window.showToast(data.message, 'success');
                }
                setTimeout(() => location.reload(), 600);
            } else {
                window.showToast('Có lỗi xảy ra!', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            window.showToast('Lỗi kết nối server', 'error');
        })
        .finally(() => {
            document.body.style.cursor = 'default';
        });
    }

    document.querySelectorAll('.btn-increase').forEach(btn => {
    btn.addEventListener('click', () => {
        const qtySpan = btn.parentElement.querySelector('span');
        let currentQty = parseInt(qtySpan.innerText);

        if (currentQty < 5) {
            ajaxCart('increase', btn.dataset.id);
        } else {
            let addQty = prompt('Nhập số lượng muốn thêm:', '1');
            if (addQty === null) return;

            addQty = parseInt(addQty);
            if (isNaN(addQty) || addQty <= 0) {
                window.showToast('Số lượng không hợp lệ', 'error');
                return;
            }

            let newQty = currentQty + addQty;

            qtySpan.innerText = newQty;

            fetch('cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add_custom&cart_id=${btn.dataset.id}&add_qty=${addQty}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success && d.message) {
                    window.showToast(d.message, 'success');
                }
                if (!d.success) {
                    window.showToast('Lỗi cập nhật số lượng', 'error');
                    location.reload();
                }
            });
        }
    });
});

    document.querySelectorAll('.btn-decrease').forEach(btn => {
        btn.addEventListener('click', () => {
            ajaxCart('decrease', btn.dataset.id);
        });
    });

    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            window.showConfirm('Bạn có chắc chắn muốn xóa sản phẩm này?').then(ok => { if (!ok) return; ajaxCart('remove', btn.dataset.id); })
        });
    });
});
</script>
</body>
</html>
<?php 

if(isset($conn)) $conn->close(); 
?>
