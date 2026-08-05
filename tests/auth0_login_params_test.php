<?php
require __DIR__ . '/bootstrap.php';

$src = file_get_contents(__DIR__ . '/../pages/auth/login.php');

assert_true(str_contains($src, "'ui_locales' => 'vi'"), 'Universal Login yeu cau tieng Viet');
assert_true(str_contains($src, "\$params['screen_hint'] = 'signup';"), 'signup van truyen screen_hint');
assert_true(str_contains($src, '$auth0->login(null, $params)'), 'login dung authorize params');

echo "auth0_login_params_test ... ok\n";
