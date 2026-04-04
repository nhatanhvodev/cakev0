<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/Cake/pages/forgot-password.php' . ($qs ? ('?' . $qs) : '');
header('Location: ' . $target, true, 301);
exit;
?>
