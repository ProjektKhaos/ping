<?php

declare(strict_types=1);

namespace PingFloodWatch;

final class Api
{
    /** @param mixed $data @param array<string,mixed> $meta */
    public static function success(mixed $data, array $meta = [], int $status = 200): never
    {
        self::headers();
        http_response_code($status);
        echo json_encode(['ok' => true, 'data' => $data, 'meta' => $meta], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $code, string $message, int $status = 400): never
    {
        self::headers();
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function period(?string $period, array $allowed = ['24h', '48h', '72h'], ?string $language = null): int
    {
        $period ??= $allowed[0];
        if (!in_array($period, $allowed, true)) {
            self::error('INVALID_PERIOD', t('error.api.invalid_period', [], $language));
        }
        return (int) rtrim($period, 'h');
    }

    public static function language(?string $language): string
    {
        $language ??= locale();
        if (!in_array($language, Config::get('app.supported_languages', ['en', 'th']), true)) {
            self::error('INVALID_LANGUAGE', t('error.api.invalid_language', [], locale()));
        }
        return $language;
    }

    public static function requirePostJsonSameOrigin(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            self::error('METHOD_NOT_ALLOWED', t('error.api.method_not_allowed'), 405);
        }
        $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType !== 'application/json') {
            self::error('INVALID_CONTENT_TYPE', t('error.api.invalid_content_type'), 415);
        }
        $expected = rtrim((string) Config::get('app.public_origin', ''), '/');
        $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
        if ($expected === '' || !hash_equals($expected, $origin)) {
            self::error('INVALID_ORIGIN', t('error.api.invalid_origin'), 403);
        }
    }

    /** @return array<string,mixed> */
    public static function jsonBody(int $maxBytes = 16384): array
    {
        $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null;
        if ($length !== null && $length > $maxBytes) {
            self::error('REQUEST_TOO_LARGE', t('error.api.request_too_large'), 413);
        }
        $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (!is_string($raw) || strlen($raw) > $maxBytes) {
            self::error('REQUEST_TOO_LARGE', t('error.api.request_too_large'), 413);
        }
        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::error('INVALID_JSON', t('error.api.invalid_json'));
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            self::error('INVALID_JSON', t('error.api.invalid_json'));
        }
        return $decoded;
    }

    private static function headers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
}
