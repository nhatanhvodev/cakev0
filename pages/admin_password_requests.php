<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/Cake/pages/admin-password-requests.php' . ($qs ? ('?' . $qs) : '');
header('Location: ' . $target, true, 301);
exit;
?>
