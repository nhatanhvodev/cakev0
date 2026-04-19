<?php

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('normalize_base_path')) {
	function normalize_base_path(string $basePath): string
	{
		$basePath = trim(str_replace('\\', '/', $basePath));
		if ($basePath === '' || $basePath === '/') {
			return '';
		}

		if (!str_starts_with($basePath, '/')) {
			$basePath = '/' . $basePath;
		}

		return rtrim($basePath, '/');
	}
}

$defaultBasePath = '/cakev0';
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

if ($scriptName !== '' && preg_match('#^(.*/(?:cakev0|Cake))(?:/|$)#i', str_replace('\\', '/', $scriptName), $matches)) {
	$defaultBasePath = $matches[1];
}

$configuredBasePath = env_value('APP_BASE_PATH', $defaultBasePath);
$normalizedBasePath = normalize_base_path((string) $configuredBasePath);

if (!defined('APP_BASE_PATH')) {
	define('APP_BASE_PATH', $normalizedBasePath);
}

if (!defined('BASE_URL')) {
	define('BASE_URL', APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/');
}

if (!function_exists('base_url')) {
	function base_url(string $path = ''): string
	{
		$base = BASE_URL;
		if ($path === '') {
			return $base;
		}

		return rtrim($base, '/') . '/' . ltrim($path, '/');
	}
}

if (!function_exists('app_origin')) {
	function app_origin(): string
	{
		$configuredOrigin = env_value('APP_ORIGIN', null);
		if ($configuredOrigin !== null && $configuredOrigin !== '') {
			return rtrim($configuredOrigin, '/');
		}

		$https = $_SERVER['HTTPS'] ?? '';
		$scheme = ($https !== '' && strtolower((string) $https) !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

		return $scheme . '://' . $host;
	}
}

if (!function_exists('absolute_url')) {
	function absolute_url(string $path = ''): string
	{
		$localPath = base_url($path);
		if (preg_match('#^https?://#i', $localPath)) {
			return $localPath;
		}

		return rtrim(app_origin(), '/') . $localPath;
	}
}
