<?php

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../includes/sepay_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
if ($authHeader === null && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strtolower((string) $name) === 'authorization') {
            $authHeader = (string) $value;
            break;
        }
    }
}

$result = sepay_process_webhook($conn, $payload, $authHeader, sepay_config());
http_response_code((int) $result['code']);
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
