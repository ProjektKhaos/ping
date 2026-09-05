<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use PingFloodWatch\Config;
use PingFloodWatch\Logger;
use Throwable;

final class VisitorCounter
{
    private const TOTAL_PATTERN = '/\*\*Total recorded page views:\*\* `([0-9]{12})`/';

    public static function record(string $language): void
    {
        if (PHP_SAPI === 'cli' || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return;
        }

        try {
            self::recordRequest($_SERVER, $_GET, $language);
        } catch (Throwable) {
            // Analytics must never affect page delivery or disclose request data in application logs.
            Logger::error('visitor_counter_write_failed', ['error_code' => 'VISITOR_COUNTER_WRITE_FAILED']);
        }
    }

    /** @param array<string,mixed> $server @param array<string,mixed> $query */
    public static function recordRequest(array $server, array $query, string $language): bool
    {
        if (!(bool) Config::get('visitor_counter.enabled', false)) {
            return false;
        }

        $path = (string) Config::get('visitor_counter.log_file', '');
        $key = (string) Config::get('visitor_counter.hmac_key', '');
        if ($key === '') {
            $key = (string) Config::get('security.rate_limit_key', '');
        }
        $ip = filter_var((string) ($server['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP);
        $userAgent = substr((string) ($server['HTTP_USER_AGENT'] ?? ''), 0, 512);
        if ($path === '' || $key === '' || !is_string($ip) || self::isBot($userAgent) || is_link($path)) {
            return false;
        }

        $utc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $bangkok = $utc->setTimezone(new DateTimeZone('Asia/Bangkok'));
        $visitorId = substr(hash_hmac('sha256', 'pfw-visitor-v1|' . $utc->format('Y-m-d') . '|' . $ip, $key), 0, 12);
        [$device, $os, $browser] = self::clientClass($userAgent);

        $fields = [
            $utc->format('Y-m-d H:i:s'),
            $bangkok->format('Y-m-d H:i:s'),
            $visitorId,
            self::pageName($server, $query),
            in_array($language, ['en', 'th'], true) ? $language : 'unknown',
            $device,
            $os,
            $browser,
            self::source($server),
            self::privacySignal($server),
        ];

        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $stats = fstat($handle);
            if (($stats['size'] ?? 0) === 0) {
                fwrite($handle, self::header());
                fflush($handle);
            }

            rewind($handle);
            $head = fread($handle, 4096);
            if (!is_string($head) || preg_match(self::TOTAL_PATTERN, $head, $match, PREG_OFFSET_CAPTURE) !== 1) {
                return false;
            }

            $total = min(999_999_999_999, ((int) $match[1][0]) + 1);
            $counter = str_pad((string) $total, 12, '0', STR_PAD_LEFT);
            if (fseek($handle, (int) $match[1][1]) !== 0 || fwrite($handle, $counter) !== 12) {
                return false;
            }

            fseek($handle, 0, SEEK_END);
            $row = '| ' . $total . ' | ' . implode(' | ', array_map(self::escape(...), $fields)) . " |\n";
            if (fwrite($handle, $row) !== strlen($row)) {
                return false;
            }
            fflush($handle);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function header(): string
    {
        return <<<'MD'
# Internal visitor counter

**Total recorded page views:** `000000000000`

This private log records server-rendered page visits only. Visitor IDs are one-way HMAC values that rotate daily. Raw IP addresses, full User-Agent strings, URL query parameters and full referrer URLs are never stored.

| # | UTC | Bangkok time | Daily visitor | Page | Language | Device | OS | Browser | Source | Privacy signal |
|---:|---|---|---|---|---|---|---|---|---|---|
MD
        . "\n";
    }

    /** @param array<string,mixed> $server @param array<string,mixed> $query */
    private static function pageName(array $server, array $query): string
    {
        $page = match (basename((string) ($server['SCRIPT_NAME'] ?? ''))) {
            'index.php' => '/',
            'stations.php' => '/stations.php',
            'station.php' => '/station.php',
            'alerts.php' => '/alerts.php',
            default => '/other',
        };
        if ($page === '/station.php' && in_array(($query['code'] ?? null), Config::get('stations.codes', []), true)) {
            $page .= ' (' . $query['code'] . ')';
        }
        return $page;
    }

    /** @return array{string,string,string} */
    private static function clientClass(string $userAgent): array
    {
        $device = preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $userAgent) ? 'Tablet'
            : (preg_match('/Mobile|iPhone|Android/i', $userAgent) ? 'Mobile' : 'Desktop');
        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent) => 'iOS',
            (bool) preg_match('/Android/i', $userAgent) => 'Android',
            (bool) preg_match('/Windows/i', $userAgent) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $userAgent) => 'macOS',
            (bool) preg_match('/Linux/i', $userAgent) => 'Linux',
            default => 'Other',
        };
        $browser = match (true) {
            (bool) preg_match('/EdgA|EdgiOS|Edg\//i', $userAgent) => 'Edge',
            (bool) preg_match('/SamsungBrowser/i', $userAgent) => 'Samsung Internet',
            (bool) preg_match('/CriOS|Chrome/i', $userAgent) => 'Chrome',
            (bool) preg_match('/FxiOS|Firefox/i', $userAgent) => 'Firefox',
            (bool) preg_match('/Version\/.*Safari/i', $userAgent) => 'Safari',
            default => 'Other',
        };
        return [$device, $os, $browser];
    }

    private static function isBot(string $userAgent): bool
    {
        return $userAgent !== '' && (bool) preg_match('/bot|crawler|spider|slurp|facebookexternalhit|preview|monitor|uptime|curl|wget/i', $userAgent);
    }

    /** @param array<string,mixed> $server */
    private static function source(array $server): string
    {
        $referrer = (string) ($server['HTTP_REFERER'] ?? '');
        if ($referrer === '') {
            return 'Direct';
        }
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        $publicHost = strtolower((string) parse_url((string) Config::get('app.public_origin', ''), PHP_URL_HOST));
        $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
        if ($host === '') {
            return 'Unknown';
        }
        return $host === $publicHost ? 'Internal' : 'External: ' . substr($host, 0, 100);
    }

    /** @param array<string,mixed> $server */
    private static function privacySignal(array $server): string
    {
        $signals = [];
        if (($server['HTTP_SEC_GPC'] ?? '') === '1') {
            $signals[] = 'GPC';
        }
        if (($server['HTTP_DNT'] ?? '') === '1') {
            $signals[] = 'DNT';
        }
        return $signals === [] ? 'None' : implode('+', $signals);
    }

    private static function escape(string $value): string
    {
        return str_replace(["\\", '|', "\r", "\n"], ["\\\\", '\\|', ' ', ' '], $value);
    }
}
