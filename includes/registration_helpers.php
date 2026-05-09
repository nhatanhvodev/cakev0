<?php

require_once __DIR__ . '/../config/config.php';

function build_registration_verification_url(string $token): string
{
    return base_url('pages/verify-registration.php?token=' . urlencode($token));
}

function build_registration_verification_mail(string $username, string $verificationUrl): array
{
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    return [
        'subject' => 'Xác thực tài khoản Gấu Bakery',
        'body' => sprintf(
            "Chào %s,\n\nVui lòng xác thực tài khoản của bạn bằng liên kết sau:\n%s\n\nLiên kết này có hiệu lực trong 24 giờ.",
            $safeUsername,
            $safeUrl
        ),
    ];
}
