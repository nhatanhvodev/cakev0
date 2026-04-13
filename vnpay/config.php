<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

// VNPAY Sandbox Configuration
$vnp_TmnCode = "GO1IEGFS";
$vnp_HashSecret = "FALJ15YAAF9B9AEWXHVGBVQL6WO4SONA";
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";

$vnp_Returnurl = "http://localhost/Cake/vnpay/vnpay_return.php";
$vnp_apiUrl = "http://sandbox.vnpayment.vn/merchant_webapi/merchant.html";
$apiUrl = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction";

// Config input format
// Expire
$startTime = date("YmdHis");
$expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));
