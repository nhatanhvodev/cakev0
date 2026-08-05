<?php
require_once __DIR__ . '/../config/config.php';

$return = isset($_GET['return']) ? '?return=' . rawurlencode((string) $_GET['return']) : '';
header('Location: ' . base_url('pages/auth/login.php') . $return);
exit;
