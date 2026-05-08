<?php
use PHPMailer\PHPMailer\PHPMailer;

function mail_required_config_missing(): array {
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

function mail_html_to_text(string $html): string {
    $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $withBlocks = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $withBreaks ?? $html);
    $text = html_entity_decode(strip_tags($withBlocks ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text ?? '');

    return trim($text ?? '') ?: ' ';
}

/**
 * Gui email qua PHPMailer va SMTP.
 *
 * @param string $to Email nguoi nhan
 * @param string $subject Tieu de email
 * @param string $body Noi dung email HTML
 * @param string|null $fromName Ten nguoi gui
 * @return bool true neu gui thanh cong, false neu that bai
 */
function send_custom_mail($to, $subject, $body, $fromName = null) {
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
