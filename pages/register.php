<?php
require_once __DIR__ . '/../config/config.php';

$target = $_GET['return'] ?? $_GET['redirect'] ?? null;
if (is_string($target) && $target !== '' && !str_starts_with($target, '/') && !str_contains($target, '://')) {
    $target = base_url('pages/' . ltrim($target, '/'));
}

$query = ['mode' => 'signup'];
if (is_string($target) && $target !== '') {
    $query['return'] = $target;
}

header('Location: ' . base_url('pages/auth/login.php?' . http_build_query($query)));
exit;
