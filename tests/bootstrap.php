<?php

require_once __DIR__ . '/../config/bootstrap.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "Assertion failed: {$message}\n"
            . 'Expected: ' . var_export($expected, true) . "\n"
            . 'Actual: ' . var_export($actual, true) . "\n"
        );
        exit(1);
    }
}
