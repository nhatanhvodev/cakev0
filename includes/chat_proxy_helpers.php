<?php
// includes/chat_proxy_helpers.php

function chat_ai_service_url(): string
{
    $url = getenv('AI_SERVICE_URL');
    return $url !== false && $url !== '' ? rtrim($url, '/') : 'http://localhost:8000';
}

function chat_build_forward_payload(array $input, ?int $authenticatedUserId): array
{
    $payload = ['message' => trim((string) ($input['message'] ?? ''))];
    if (!empty($input['session_id'])) {
        $payload['session_id'] = (int) $input['session_id'];
    }
    if ($authenticatedUserId !== null) {
        $payload['user_id'] = $authenticatedUserId;
    } elseif (!empty($input['guest_token'])) {
        $payload['guest_token'] = substr((string) $input['guest_token'], 0, 64);
    }
    return $payload;
}

function chat_admin_bypass_header(): ?string
{
    $secret = getenv('INTERNAL_API_SECRET') ?: '';
    if ($secret === '') {
        return null;
    }
    $ts = time();
    return 'X-Admin-Bypass: ' . $ts . ':' . hash_hmac('sha256', 'admin:' . $ts, $secret);
}

/**
 * Signed proof that the request belongs to an authenticated user.
 * Format: "X-User-Identity: <ts>:<user_id>:<hmac>". The AI service verifies
 * this (mirroring the admin bypass) and uses the signed user_id for every
 * data-access decision, so a direct caller cannot spoof user_id in the body.
 * Returns null when INTERNAL_API_SECRET is unset (fail-closed: no signed id).
 */
function chat_user_identity_header(int $userId): ?string
{
    $secret = getenv('INTERNAL_API_SECRET') ?: '';
    if ($secret === '' || $userId <= 0) {
        return null;
    }
    $ts = time();
    return 'X-User-Identity: ' . $ts . ':' . $userId . ':'
        . hash_hmac('sha256', 'user:' . $ts . ':' . $userId, $secret);
}

function chat_admin_csrf_valid(?string $providedToken): bool
{
    if (
        $providedToken === null
        || $providedToken === ''
        || empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $providedToken);
}
