<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../includes/auth_bridge.php';

$auth0 = auth0_client();
$auth0->clear();

$returnTo = safe_redirect_target($_GET['return'] ?? null, base_url('index.php'));
$_SESSION['auth_return_to'] = $returnTo;

$params = [
    'ui_locales' => 'vi',
];
if (($_GET['mode'] ?? '') === 'signup') {
    $params['screen_hint'] = 'signup';
}

header('Location: ' . $auth0->login(null, $params));
exit;
