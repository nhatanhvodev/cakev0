<?php
use PHPMailer\PHPMailer\PHPMailer;

function mail_driver(): string {
    $driver = strtolower(trim((string) env_value('MAIL_DRIVER', 'smtp')));
    return in_array($driver, ['smtp', 'gmail_api'], true) ? $driver : 'smtp';
}

function mail_required_config_missing(): array {
    if (mail_driver() === 'gmail_api') {
        return gmail_api_config_missing();
    }

    $requiredKeys = [
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_FROM_ADDRESS',
    ];

    $missing = [];
    foreach ($requiredKeys as $key) {
        $value = env_value($key);
        if ($value === null || trim($value) === '') {
            $missing[] = $key;
        }
    }

    return $missing;
}

function mail_timeout_seconds(): int {
    $timeout = (int) env_value('MAIL_TIMEOUT', '300');
    if ($timeout < 1) {
        return 300;
    }

    return min($timeout, 300);
}

function mail_force_ipv4_enabled(): bool {
    return env_bool('MAIL_FORCE_IPV4', true);
}

function mail_resolve_smtp_host(string $host): string {
    if (!mail_force_ipv4_enabled() || filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }

    $records = dns_get_record($host, DNS_A);
    if (is_array($records)) {
        foreach ($records as $record) {
            $ip = $record['ip'] ?? null;
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
    }

    $ips = gethostbynamel($host);
    if (is_array($ips)) {
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
    }

    return $host;
}

function mail_debug_enabled(): bool {
    return env_bool('MAIL_DEBUG', false);
}

function mail_log_connection_context(string $smtpHost, string $resolvedHost, int $port, int $timeout): void {
    if (!mail_debug_enabled()) {
        return;
    }

    error_log(sprintf(
        'Mailer Debug: host=%s resolvedHost=%s port=%d timeout=%d forceIpv4=%s encryption=%s',
        $smtpHost,
        $resolvedHost,
        $port,
        $timeout,
        mail_force_ipv4_enabled() ? 'true' : 'false',
        (string) env_value('MAIL_ENCRYPTION', 'tls')
    ));
}

function gmail_api_config_missing(): array {
    $requiredKeys = [
        'GMAIL_CLIENT_ID',
        'GMAIL_CLIENT_SECRET',
        'GMAIL_REFRESH_TOKEN',
        'MAIL_FROM_ADDRESS',
    ];

    $missing = [];
    foreach ($requiredKeys as $key) {
        $value = env_value($key);
        if ($value === null || trim($value) === '') {
            $missing[] = $key;
        }
    }

    return $missing;
}

function mail_sanitize_header(string $value): string {
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
}

function mail_encode_header(string $value): string {
    $value = mail_sanitize_header($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    return $value;
}

function mail_format_address(string $email, ?string $name = null): string {
    $email = mail_sanitize_header($email);
    $name = mail_sanitize_header((string) ($name ?? ''));
    if ($name === '') {
        return '<' . $email . '>';
    }

    return mail_encode_header($name) . ' <' . $email . '>';
}

function gmail_api_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function gmail_api_build_raw_message(string $to, string $subject, string $body, string $fromAddress, ?string $fromName = null): string {
    $domain = substr(strrchr($fromAddress, '@') ?: '@localhost', 1) ?: 'localhost';
    $headers = [
        'MIME-Version: 1.0',
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'From: ' . mail_format_address($fromAddress, $fromName ?? env_value('MAIL_FROM_NAME', 'Gau Bakery')),
        'To: ' . mail_format_address($to),
        'Subject: ' . mail_encode_header($subject),
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];

    $message = implode("\r\n", $headers)
        . "\r\n\r\n"
        . chunk_split(base64_encode($body), 76, "\r\n");

    return gmail_api_base64url_encode($message);
}

function gmail_api_http_post(string $url, array $headers, string $body): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => mail_timeout_seconds(),
        ]);
        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => is_string($responseBody) ? $responseBody : '',
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => mail_timeout_seconds(),
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $statusCode = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches)) {
            $statusCode = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $statusCode,
        'body' => is_string($responseBody) ? $responseBody : '',
        'error' => $responseBody === false ? 'HTTP request failed.' : '',
    ];
}

function gmail_api_refresh_access_token(): ?string {
    $body = http_build_query([
        'client_id' => env_value('GMAIL_CLIENT_ID'),
        'client_secret' => env_value('GMAIL_CLIENT_SECRET'),
        'refresh_token' => env_value('GMAIL_REFRESH_TOKEN'),
        'grant_type' => 'refresh_token',
    ]);

    $response = gmail_api_http_post(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        $body
    );

    $payload = json_decode($response['body'], true);
    if ($response['status'] >= 200 && $response['status'] < 300 && is_array($payload) && !empty($payload['access_token'])) {
        return (string) $payload['access_token'];
    }

    $error = is_array($payload) ? ($payload['error_description'] ?? $payload['error'] ?? '') : '';
    if ($error === '' && $response['error'] !== '') {
        $error = $response['error'];
    }
    error_log('Mailer Error: Gmail API token refresh failed. HTTP ' . $response['status'] . ($error ? ' - ' . $error : ''));
    return null;
}

function gmail_api_send_raw_message(string $accessToken, string $rawMessage): bool {
    $userId = rawurlencode((string) env_value('GMAIL_USER_ID', 'me'));
    $response = gmail_api_http_post(
        'https://gmail.googleapis.com/gmail/v1/users/' . $userId . '/messages/send',
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        json_encode(['raw' => $rawMessage], JSON_UNESCAPED_SLASHES)
    );

    if ($response['status'] >= 200 && $response['status'] < 300) {
        return true;
    }

    $payload = json_decode($response['body'], true);
    $error = is_array($payload) ? ($payload['error']['message'] ?? $payload['error_description'] ?? '') : '';
    if ($error === '' && $response['error'] !== '') {
        $error = $response['error'];
    }
    error_log('Mailer Error: Gmail API send failed. HTTP ' . $response['status'] . ($error ? ' - ' . $error : ''));
    return false;
}

function send_custom_mail_gmail_api($to, $subject, $body, $fromName = null): bool {
    $missingConfig = gmail_api_config_missing();
    if (!empty($missingConfig)) {
        error_log('Mailer Error: Missing Gmail API config: ' . implode(', ', $missingConfig));
        return false;
    }

    $fromAddress = env_value('MAIL_FROM_ADDRESS');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        error_log('Mailer Error: Invalid sender or recipient email address.');
        return false;
    }

    $accessToken = gmail_api_refresh_access_token();
    if ($accessToken === null) {
        return false;
    }

    $rawMessage = gmail_api_build_raw_message($to, $subject, $body, $fromAddress, $fromName);
    return gmail_api_send_raw_message($accessToken, $rawMessage);
}

function mail_html_to_text(string $html): string {
    $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $withBlocks = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $withBreaks ?? $html);
    $text = html_entity_decode(strip_tags($withBlocks ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text ?? '');

    return trim($text ?? '') ?: ' ';
}

/**
 * Gui email qua driver da cau hinh: Gmail API hoac PHPMailer SMTP.
 *
 * @param string $to Email nguoi nhan
 * @param string $subject Tieu de email
 * @param string $body Noi dung email HTML
 * @param string|null $fromName Ten nguoi gui
 * @return bool true neu gui thanh cong, false neu that bai
 */
function send_custom_mail($to, $subject, $body, $fromName = null) {
    if (mail_driver() === 'gmail_api') {
        return send_custom_mail_gmail_api($to, $subject, $body, $fromName);
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('Mailer Error: PHPMailer is not available.');
        return false;
    }

    $missingConfig = mail_required_config_missing();
    if (!empty($missingConfig)) {
        error_log('Mailer Error: Missing SMTP config: ' . implode(', ', $missingConfig));
        return false;
    }

    $fromAddress = env_value('MAIL_FROM_ADDRESS');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        error_log('Mailer Error: Invalid sender or recipient email address.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $smtpHost         = env_value('MAIL_HOST', 'smtp.gmail.com');
        $mail->Host       = mail_resolve_smtp_host($smtpHost);
        $mail->SMTPAuth   = true;
        $mail->Username   = env_value('MAIL_USERNAME');
        $mail->Password   = env_value('MAIL_PASSWORD');
        $mail->SMTPSecure = env_value('MAIL_ENCRYPTION', 'tls');
        $mail->Port       = (int) env_value('MAIL_PORT', 587);
        $mail->Timeout    = mail_timeout_seconds();
        $mail->CharSet    = 'UTF-8';
        mail_log_connection_context($smtpHost, $mail->Host, $mail->Port, $mail->Timeout);
        if ($mail->Host !== $smtpHost) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'peer_name' => $smtpHost,
                ],
            ];
        }

        $defaultFromName = env_value('MAIL_FROM_NAME', 'Gau Bakery');
        $mail->setFrom($fromAddress, $fromName ?? $defaultFromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = mail_html_to_text($body);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        error_log('Mailer Error: ' . $error);
        return false;
    }
}
