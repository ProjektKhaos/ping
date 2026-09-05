<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;

final class RateLimiter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{allowed:bool,retry_after:int,count:int} */
    public function consume(string $route, string $client, int $limit, int $windowSeconds, ?DateTimeImmutable $now = null): array
    {
        $secret = (string) Config::get('security.rate_limit_key', '');
        if ($secret === '') {
            throw new \RuntimeException('Push API rate limiting is not configured.');
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowString = $now->format('Y-m-d H:i:s');
        $expires = $now->modify('+' . max(1, $windowSeconds) . ' seconds')->format('Y-m-d H:i:s');
        $clientHash = hash_hmac('sha256', $client, $secret);
        $statement = $this->db->prepare(
            'INSERT INTO api_rate_limits (route, client_hash, window_started_at, request_count, expires_at)
             VALUES (:route, :client_hash, :now_insert, 1, :expires_insert)
             ON DUPLICATE KEY UPDATE
                request_count = IF(expires_at <= :now_check, 1, request_count + 1),
                window_started_at = IF(expires_at <= :now_window, :now_reset, window_started_at),
                expires_at = IF(expires_at <= :now_expiry, :expires_reset, expires_at)'
        );
        $statement->execute([
            'route' => $route, 'client_hash' => $clientHash,
            'now_insert' => $nowString, 'expires_insert' => $expires,
            'now_check' => $nowString, 'now_window' => $nowString, 'now_reset' => $nowString,
            'now_expiry' => $nowString, 'expires_reset' => $expires,
        ]);
        $select = $this->db->prepare(
            'SELECT request_count, expires_at FROM api_rate_limits WHERE route = :route AND client_hash = :client_hash'
        );
        $select->execute(['route' => $route, 'client_hash' => $clientHash]);
        $row = $select->fetch() ?: ['request_count' => $limit + 1, 'expires_at' => $expires];
        $expiresAt = new DateTimeImmutable((string) $row['expires_at'] . ' UTC');
        $retryAfter = max(1, $expiresAt->getTimestamp() - $now->getTimestamp());
        return ['allowed' => (int) $row['request_count'] <= $limit, 'retry_after' => $retryAfter, 'count' => (int) $row['request_count']];
    }

    public function purgeExpired(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $statement = $this->db->prepare('DELETE FROM api_rate_limits WHERE expires_at < :cutoff LIMIT 500');
        $statement->execute(['cutoff' => $now->modify('-1 day')->format('Y-m-d H:i:s')]);
        return $statement->rowCount();
    }
}
