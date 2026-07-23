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
