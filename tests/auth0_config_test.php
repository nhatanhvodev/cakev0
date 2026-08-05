<?php
require __DIR__ . '/bootstrap.php';

// Nạp env giả lập cho builder
putenv('AUTH0_DOMAIN=gaubakery.eu.auth0.com');
putenv('AUTH0_CLIENT_ID=cid123');
putenv('AUTH0_CLIENT_SECRET=secret123');
putenv('AUTH0_COOKIE_SECRET=0123456789abcdef0123456789abcdef');
$_ENV['AUTH0_DOMAIN'] = 'gaubakery.eu.auth0.com';
$_ENV['AUTH0_CLIENT_ID'] = 'cid123';
$_ENV['AUTH0_CLIENT_SECRET'] = 'secret123';
$_ENV['AUTH0_COOKIE_SECRET'] = '0123456789abcdef0123456789abcdef';

require __DIR__ . '/../includes/auth0.php';

$cfg = auth0_config();
assert_same('gaubakery.eu.auth0.com', $cfg['domain'], 'domain tu env');
assert_same('cid123', $cfg['clientId'], 'clientId tu env');
assert_same('secret123', $cfg['clientSecret'], 'clientSecret tu env');
assert_true(strlen($cfg['cookieSecret']) >= 32, 'cookieSecret du dai');
assert_true(str_contains($cfg['redirectUri'], '/cakev0/pages/auth/callback.php'), 'redirectUri co callback path');

echo "auth0_config_test ... ok\n";
