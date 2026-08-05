<?php
// Chay 1 lan, thu cong:
//   docker compose exec app php scripts/migrate_users_to_auth0.php
// Yeu cau env M2M: AUTH0_DOMAIN, AUTH0_MGMT_CLIENT_ID, AUTH0_MGMT_CLIENT_SECRET, AUTH0_CONNECTION_ID

if (!function_exists('auth0_import_user_id')) {
    function auth0_import_user_id(array $row, bool $isAdmin): string
    {
        if ($isAdmin) {
            return 'cakev0-admin-' . (int) ($row['id'] ?? 0);
        }

        return 'cakev0-user-' . (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('auth0_import_username')) {
    function auth0_import_username(array $row, bool $isAdmin): string
    {
        return str_replace('-', '_', auth0_import_user_id($row, $isAdmin));
    }
}

if (!function_exists('build_import_payload')) {
    function build_import_payload(array $row, bool $isAdmin): array
    {
        $payload = [
            'email' => (string) $row['email'],
            'email_verified' => true,
            'username' => auth0_import_username($row, $isAdmin),
            'user_id' => auth0_import_user_id($row, $isAdmin),
            'custom_password_hash' => [
                'algorithm' => 'bcrypt',
                'hash' => [
                    'value' => (string) $row['password'],
                    'encoding' => 'utf8',
                ],
            ],
            'user_metadata' => [
                'username' => (string) ($row['username'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
            ],
        ];

        if ($isAdmin) {
            $payload['app_metadata'] = ['role' => 'admin'];
        }

        return $payload;
    }
}

if (!function_exists('auth0_management_token')) {
    function auth0_management_token(string $domain, string $clientId, string $clientSecret): string
    {
        $tokenResp = json_decode((string) file_get_contents(
            "https://{$domain}/oauth/token",
            false,
            stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'audience' => "https://{$domain}/api/v2/",
                ]),
                'ignore_errors' => true,
            ]])
        ), true);

        return (string) ($tokenResp['access_token'] ?? '');
    }
}

if (!function_exists('auth0_post_user_import_job')) {
    function auth0_post_user_import_job(
        string $domain,
        string $mgmtToken,
        string $connectionId,
        array $records
    ): array {
        $boundary = '----cakev0Auth0Import' . bin2hex(random_bytes(12));
        $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $body = '';
        $fields = [
            'connection_id' => $connectionId,
            'upsert' => 'false',
            'send_completion_email' => 'false',
            'external_id' => 'cakev0-' . date('Ymd-His'),
        ];

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"users\"; filename=\"cakev0-auth0-users.json\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $json . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return json_decode((string) file_get_contents(
            "https://{$domain}/api/v2/jobs/users-imports",
            false,
            stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$mgmtToken}\r\nContent-Type: multipart/form-data; boundary={$boundary}\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ]])
        ), true) ?: [];
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    require_once __DIR__ . '/../config/config.php';

    $domain = (string) env_value('AUTH0_DOMAIN', '');
    $mgmtId = (string) env_value('AUTH0_MGMT_CLIENT_ID', '');
    $mgmtSecret = (string) env_value('AUTH0_MGMT_CLIENT_SECRET', '');
    $connectionId = (string) env_value('AUTH0_CONNECTION_ID', '');

    if ($domain === '' || $mgmtId === '' || $mgmtSecret === '' || $connectionId === '') {
        fwrite(STDERR, "Thieu env AUTH0_DOMAIN, AUTH0_MGMT_CLIENT_ID, AUTH0_MGMT_CLIENT_SECRET hoac AUTH0_CONNECTION_ID\n");
        exit(1);
    }

    require_once __DIR__ . '/../config/connect.php';

    $records = [];
    $pendingMap = [];
    $userEmails = [];

    $res = $conn->query("SELECT id, username, email, password, phone, auth0_id FROM users");
    while ($res && ($row = $res->fetch_assoc())) {
        $email = strtolower(trim((string) $row['email']));
        if ($email !== '') {
            $userEmails[$email] = true;
        }

        if (!empty($row['auth0_id'])) {
            echo "Bo qua (da co auth0_id): {$row['email']}\n";
            continue;
        }

        $records[] = build_import_payload($row, false);
        $pendingMap[] = [
            'local_table' => 'users',
            'local_id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'auth0_id' => 'auth0|' . auth0_import_user_id($row, false),
        ];
    }

    $res = $conn->query("SELECT id, username, password FROM admins");
    while ($res && ($row = $res->fetch_assoc())) {
        $row['email'] = $row['username'] . '@gaubakery.vn';
        $row['phone'] = '';
        $adminEmail = strtolower(trim((string) $row['email']));
        if (isset($userEmails[$adminEmail])) {
            fwrite(STDERR, "Email admin {$row['email']} trung voi users.email; gan app_metadata.role=admin thu cong tren Auth0 Dashboard.\n");
            exit(1);
        }

        $records[] = build_import_payload($row, true);
    }

    if ($records === []) {
        echo "Khong co user nao can import.\n";
        exit(0);
    }

    $mgmtToken = auth0_management_token($domain, $mgmtId, $mgmtSecret);
    if ($mgmtToken === '') {
        fwrite(STDERR, "Khong lay duoc Management token\n");
        exit(1);
    }

    $job = auth0_post_user_import_job($domain, $mgmtToken, $connectionId, $records);
    $jobId = (string) ($job['id'] ?? '');
    if ($jobId === '') {
        fwrite(STDERR, "Khong tao duoc import job: " . json_encode($job, JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }

    $mapDir = __DIR__ . '/../tmp/auth0-import-maps';
    if (!is_dir($mapDir) && !mkdir($mapDir, 0777, true) && !is_dir($mapDir)) {
        fwrite(STDERR, "Khong tao duoc thu muc {$mapDir}\n");
        exit(1);
    }

    $mapPath = $mapDir . '/' . $jobId . '.json';
    file_put_contents($mapPath, json_encode([
        'job_id' => $jobId,
        'created_at' => date(DATE_ATOM),
        'connection_id' => $connectionId,
        'users' => $pendingMap,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo "Da tao Auth0 import job {$jobId} voi " . count($records) . " user.\n";
    echo "Chua cap nhat users.auth0_id vi job con async. Sau khi job completed tren Auth0, dung map: {$mapPath}\n";
}
