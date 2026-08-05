<?php
require_once __DIR__ . '/../config/config.php';

use Auth0\SDK\Auth0;

if (!function_exists('auth0_config')) {
    function auth0_config(): array
    {
        $callback = env_value('AUTH0_CALLBACK_URL', null);
        if ($callback === null || $callback === '') {
            $callback = absolute_url('pages/auth/callback.php');
        }

        return [
            'domain'        => (string) env_value('AUTH0_DOMAIN', ''),
            'clientId'      => (string) env_value('AUTH0_CLIENT_ID', ''),
            'clientSecret'  => (string) env_value('AUTH0_CLIENT_SECRET', ''),
            'cookieSecret'  => (string) env_value('AUTH0_COOKIE_SECRET', ''),
            'redirectUri'   => $callback,
            'cookieExpires' => 60 * 60 * 24 * 7,
        ];
    }
}

if (!function_exists('auth0_client')) {
    function auth0_client(): Auth0
    {
        static $client = null;
        if ($client instanceof Auth0) {
            return $client;
        }
        $client = new Auth0(auth0_config());
        return $client;
    }
}
