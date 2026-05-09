<?php

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Mật khẩu phải có ít nhất 8 ký tự.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ in hoa.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ thường.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 chữ số.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Mật khẩu phải có ít nhất 1 ký tự đặc biệt.';
    }

    return null;
}

function generate_verification_token(): string
{
    return bin2hex(random_bytes(32));
}
