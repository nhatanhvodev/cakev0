<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('is_remote_media_url')) {
    function is_remote_media_url(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '') {
            return false;
        }

        return preg_match('#^(https?:)?//#i', $normalized) === 1
            || str_starts_with($normalized, 'data:image/');
    }
}

if (!function_exists('uploadthing_api_key')) {
    function uploadthing_api_key(): string
    {
        $apiKey = (string) env_value('UPLOADTHING_API_KEY', '');
        if ($apiKey !== '') {
            return $apiKey;
        }

        // Backward compatibility for previous env naming.
        return (string) env_value('UPLOADTHING_TOKEN', '');
    }
}

if (!function_exists('uploadthing_enabled')) {
    function uploadthing_enabled(): bool
    {
        return uploadthing_api_key() !== '';
    }
}

if (!function_exists('uploadthing_bootstrap_env')) {
    function uploadthing_bootstrap_env(): bool
    {
        $apiKey = uploadthing_api_key();
        if ($apiKey === '') {
            return false;
        }

        putenv('UPLOADTHING_API_KEY=' . $apiKey);
        $_ENV['UPLOADTHING_API_KEY'] = $apiKey;
        $_SERVER['UPLOADTHING_API_KEY'] = $apiKey;

        $baseUrl = (string) env_value('UPLOADTHING_BASE_URL', 'https://api.uploadthing.com');
        putenv('UPLOADTHING_BASE_URL=' . $baseUrl);
        $_ENV['UPLOADTHING_BASE_URL'] = $baseUrl;
        $_SERVER['UPLOADTHING_BASE_URL'] = $baseUrl;

        $apiVersion = (string) env_value('UPLOADTHING_API_VERSION', 'v6');
        putenv('UPLOADTHING_API_VERSION=' . $apiVersion);
        $_ENV['UPLOADTHING_API_VERSION'] = $apiVersion;
        $_SERVER['UPLOADTHING_API_VERSION'] = $apiVersion;

        $timeout = (string) env_value('UPLOADTHING_TIMEOUT', '8');
        putenv('UPLOADTHING_TIMEOUT=' . $timeout);
        $_ENV['UPLOADTHING_TIMEOUT'] = $timeout;
        $_SERVER['UPLOADTHING_TIMEOUT'] = $timeout;

        return true;
    }
}

if (!function_exists('uploadthing_upload_file')) {
    function uploadthing_upload_file(string $tmpFilePath, string $remoteName, ?string $mimeType = null): ?string
    {
        if (!is_file($tmpFilePath)) {
            return null;
        }

        if (!uploadthing_bootstrap_env()) {
            return null;
        }

        require_once APP_ROOT . '/vendor/autoload.php';

        try {
            $uploader = new \UploadThing\Resources\Uploads();
            $uploaded = $uploader->uploadFile($tmpFilePath, $remoteName, $mimeType);

            $url = trim((string) ($uploaded?->url ?? ''));
            return $url !== '' ? $url : null;
        } catch (\Throwable $error) {
            error_log('UploadThing upload failed: ' . $error->getMessage());
            return null;
        }
    }
}
