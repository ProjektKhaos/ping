<?php

declare(strict_types=1);

use PingFloodWatch\Config;
use PingFloodWatch\Translator;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = '/' . trim((string) Config::get('app.base_url', '/'), '/') . '/';
    $base = preg_replace('#/+#', '/', $base) ?: '/';
    return $path === '' ? $base : $base . ltrim($path, '/');
}

function locale(): string
{
    static $locale;
    if (is_string($locale)) {
        return $locale;
    }

    $supported = Config::get('app.supported_languages', ['en', 'th']);
    $requested = PHP_SAPI !== 'cli' ? ($_GET['lang'] ?? null) : null;
    $cookie = PHP_SAPI !== 'cli' ? ($_COOKIE['pfw_lang'] ?? null) : null;
    $browser = PHP_SAPI !== 'cli' ? substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2) : null;
    foreach ([$requested, $cookie, $browser, Config::get('app.default_language', 'en')] as $candidate) {
        if (is_string($candidate) && in_array($candidate, $supported, true)) {
            $locale = $candidate;
            break;
        }
    }
    $locale ??= 'en';

    if (PHP_SAPI !== 'cli' && is_string($requested) && in_array($requested, $supported, true) && !headers_sent()) {
        setcookie('pfw_lang', $requested, [
            'expires' => time() + 31536000,
            'path' => url(),
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return $locale;
}

/** @param array<string,string|int|float> $parameters */
function t(string $key, array $parameters = [], ?string $language = null): string
{
    return Translator::translate($key, $parameters, $language);
}

function language_url(string $language): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? url());
    $path = parse_url($uri, PHP_URL_PATH) ?: url();
    $query = parse_url($uri, PHP_URL_QUERY);
    $parameters = [];
    if (is_string($query) && $query !== '') {
        parse_str($query, $parameters);
    }
    $parameters['lang'] = $language;
    return $path . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function asset_url(string $path): string
{
    $separator = str_contains($path, '?') ? '&' : '?';
    return url($path . $separator . 'v=' . rawurlencode((string) Config::get('app.asset_version', '1.2.4')));
}

function format_level(mixed $value): string
{
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') . ' m' : '—';
}

function format_rain(mixed $value): string
{
    return is_numeric($value) ? number_format((float) $value, 1, '.', '') . ' mm' : '—';
}

function format_local_time(?string $utc, string $format = 'H:i · d M'): string
{
    if (!$utc) {
        return '—';
    }
    try {
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone((string) Config::get('app.timezone')))->format($format);
    } catch (Throwable) {
        return '—';
    }
}

function trend_display(mixed $metres): string
{
    if (!is_numeric($metres)) {
        return '—';
    }
    $cm = round((float) $metres * 100);
    $arrow = $cm > 0 ? '↑' : ($cm < 0 ? '↓' : '→');
    $sign = $cm > 0 ? '+' : '';
    return sprintf('%s %s%d cm', $arrow, $sign, $cm);
}
