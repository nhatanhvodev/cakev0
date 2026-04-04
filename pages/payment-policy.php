<?php
$pageTitle = 'Chính sách Thanh toán';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        
        body {
            background: #ffffff;
            color: #272727;
            font-family: 'Poppins', sans-serif;
        }

        .policy-wrapper {
            background: #ffffff;
            border-radius: 32px;
            padding: 36px;
            border: 1px solid #f3e0be;
            box-shadow: 0 24px 48px rgba(74, 29, 31, 0.12);
        }

        .notebook {
        background: linear-gradient(180deg, #fff7ea 0%, #fdf1db 100%);

            border-radius: 24px;
            padding: 32px;
            position: relative;
        }

        .notebook-page {
            background: #fff;
            border-radius: 18px !important;
            margin-bottom: 18px;
            border: none;
            box-shadow: 0 8px 24px rgba(0,0,0,.08);
            overflow: hidden; 
        }

        .accordion-button {
            border-radius: 18px !important;
            font-weight: 600;
            font-size: 17px;
            padding: 20px 24px;
            background: #f7efe1;
            color: #4a1d1f;
            border: none;
            box-shadow: none !important; 
        }
        .accordion-button:not(.collapsed) {
            background: #f0e2c8;
            color: #4a1d1f;
        }
        
        .accordion-button::after { display: none; }

        .page-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center; justify-content: center;
            color: #fff;
            margin-right: 14px;
            font-size: 18px;
        }
        
        .bg-blue  { background: #3b82f6; }
        .bg-red   { background: #ef4444; }
        .bg-green { background: #10b981; }

        .policy-list { list-style: none; padding: 0; margin: 0; }
        
        .policy-list li {
            display: flex;
            gap: 16px;
            background: #fdf7ef;
            padding: 18px 20px;
            border-radius: 16px;
            margin-bottom: 16px;
            border: 1px solid #f3e0be;
        }
        
        .policy-list li:last-child { margin-bottom: 0; }

        .policy-item-icon { font-size: 22px; min-width: 30px; }
        
        .policy-item-content strong { display: block; margin-bottom: 4px; }
        
        .policy-item-content p {
            margin: 0;
            color: #4a4a4a;
            font-size: 15px;
            line-height: 1.6;
        }
    </style>
</head>

<body>

<?php include '../includes/header.php'; ?>
    
    <section class="container my-5">
        <div class="card shadow-lg border-0 rounded-4 p-4">

            <h1 class="text-center mb-4 fw-bold text-dark">
                <i class="fa-solid fa-wallet"></i> CHÍNH SÁCH THANH TOÁN
            </h1>

            <div class="notebook">
                <div class="accordion" id="paymentNotebook">

                    <div class="accordion-item notebook-page">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paymentA">
                                <span class="page-icon bg-blue"><i class="fa-solid fa-credit-card"></i></span>
                                Hình thức thanh toán
                            </button>
                        </h2>
                        <div id="paymentA" class="accordion-collapse collapse" data-bs-parent="#paymentNotebook">
                            <div class="accordion-body">
                                <ul class="policy-list">
                                    
                                    <li>
                                        <span class="policy-item-icon text-success"><i class="fa-solid fa-money-bill-wave"></i></span>
                                        <div class="policy-item-content">
                                            <strong>Thanh toán khi nhận hàng (COD)</strong>
                                            <p>Thanh toán trực tiếp bằng tiền mặt cho nhân viên giao hàng khi nhận sản phẩm.</p>
                                        </div>
                                    </li>
                                    
                                    <li>
                                        <span class="policy-item-icon text-primary"><i class="fa-solid fa-building-columns"></i></span>
                                        <div class="policy-item-content">
                                            <strong>Chuyển khoản ngân hàng</strong>
                                            <p>Thanh toán qua tài khoản ngân hàng theo thông tin Gấu Bakery cung cấp.</p>
                                        </div>
                                    </li>

                                    <li>
                                        <span class="policy-item-icon text-info"><i class="fa-solid fa-qrcode"></i></span>
                                        <div class="policy-item-content">
                                            <strong>Thanh toán qua VNPAY</strong>
                                            <p>Thanh toán nhanh chóng qua cổng VNPAY bằng QR hoặc thẻ nội địa, xác nhận tự động sau khi giao dịch hoàn tất.</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item notebook-page">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paymentB">
                                <span class="page-icon bg-red"><i class="fa-solid fa-circle-exclamation"></i></span>
                                Quy định thanh toán
                            </button>
                        </h2>
                        <div id="paymentB" class="accordion-collapse collapse" data-bs-parent="#paymentNotebook">
                            <div class="accordion-body">
                                <ul class="policy-list">
                                    
                                    <li>
                                        <span class="policy-item-icon text-warning"><i class="fa-solid fa-file-invoice"></i></span>
                                        <div class="policy-item-content">
                                            <strong>Xác nhận đơn hàng</strong>
                                            <p>Đơn hàng sẽ chỉ được xử lý và giao đi sau khi hệ thống xác nhận thanh toán thành công (đối với chuyển khoản).</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item notebook-page">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paymentC">
                                <span class="page-icon bg-green"><i class="fa-solid fa-shield-halved"></i></span>
                                Bảo mật thanh toán
                            </button>
                        </h2>
                        <div id="paymentC" class="accordion-collapse collapse" data-bs-parent="#paymentNotebook">
                            <div class="accordion-body">
                                <ul class="policy-list">
                                    
                                    <li>
                                        <span class="policy-item-icon text-success"><i class="fa-solid fa-lock"></i></span>
                                        <div class="policy-item-content">
                                            <strong>Bảo mật thông tin</strong>
                                            <p>Mọi thông tin giao dịch và thanh toán của khách hàng đều được cam kết bảo mật tuyệt đối.</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div> 

                <p class="text-center fw-semibold mt-4 text-secondary">
                    <i class="fa-regular fa-credit-card" style="color: #8b4513;"></i> Cảm ơn Quý khách đã tin tưởng <strong>Gấu Bakery</strong>
                </p>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.html'; ?>

</body>
</html>
