<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use PDO;

final class ProviderHealthRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function success(string $provider, string $type, ?string $at = null): void
    {
        $at ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'INSERT INTO provider_health
                (provider, provider_type, last_success_at, consecutive_failures, last_error_code, last_error_message)
             VALUES (:provider, :type, :at, 0, NULL, NULL)
             ON DUPLICATE KEY UPDATE provider_type = VALUES(provider_type), last_success_at = VALUES(last_success_at),
                consecutive_failures = 0, last_error_code = NULL, last_error_message = NULL'
        );
        $statement->execute(['provider' => $provider, 'type' => $type, 'at' => $at]);
    }

    public function failure(string $provider, string $type, string $code, string $message, ?string $at = null): void
    {
        $at ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'INSERT INTO provider_health
                (provider, provider_type, last_failure_at, consecutive_failures, last_error_code, last_error_message)
             VALUES (:provider, :type, :at, 1, :code, :message)
             ON DUPLICATE KEY UPDATE provider_type = VALUES(provider_type), last_failure_at = VALUES(last_failure_at),
                consecutive_failures = consecutive_failures + 1, last_error_code = VALUES(last_error_code),
                last_error_message = VALUES(last_error_message)'
        );
        $statement->execute([
            'provider' => $provider,
            'type' => $type,
            'at' => $at,
            'code' => substr($code, 0, 64),
            'message' => substr($message, 0, 500),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function get(string $provider): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM provider_health WHERE provider = :provider');
        $statement->execute(['provider' => $provider]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM provider_health ORDER BY provider_type, provider')->fetchAll();
    }
}
