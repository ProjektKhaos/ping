<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use PDO;

final class PushSubscriptionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function upsert(
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding,
        string $language,
        ?string $clientClass,
        ?string $now = null
    ): int {
        $now ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'INSERT INTO push_subscriptions
                (endpoint_hash, endpoint, p256dh, auth, content_encoding, language, client_class, created_at, updated_at)
             VALUES (:hash, :endpoint, :p256dh, :auth, :encoding, :language, :client_class, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), endpoint = VALUES(endpoint), p256dh = VALUES(p256dh),
                auth = VALUES(auth), content_encoding = VALUES(content_encoding), language = VALUES(language),
                client_class = VALUES(client_class), updated_at = VALUES(updated_at), disabled_at = NULL,
                failure_count = 0'
        );
        $statement->execute([
            'hash' => self::endpointHash($endpoint),
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'encoding' => $contentEncoding,
            'language' => $language,
            'client_class' => $clientClass,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function disableByEndpoint(string $endpoint, ?string $now = null): bool
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'UPDATE push_subscriptions SET disabled_at = :disabled_at, updated_at = :updated_at
             WHERE endpoint_hash = :hash AND disabled_at IS NULL'
        );
        $statement->execute(['disabled_at' => $now, 'updated_at' => $now, 'hash' => self::endpointHash($endpoint)]);
        return $statement->rowCount() > 0;
    }

    public function disableById(int $id, ?string $now = null): void
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'UPDATE push_subscriptions SET disabled_at = :disabled_at, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'disabled_at' => $now, 'updated_at' => $now]);
    }

    public function markSuccess(int $id, ?string $now = null): void
    {
        $statement = $this->db->prepare(
            'UPDATE push_subscriptions SET last_success_at = :last_success_at, failure_count = 0, updated_at = :updated_at WHERE id = :id'
        );
        $now ??= gmdate('Y-m-d H:i:s');
        $statement->execute(['id' => $id, 'last_success_at' => $now, 'updated_at' => $now]);
    }

    public function markFailure(int $id, ?string $now = null): void
    {
        $statement = $this->db->prepare(
            'UPDATE push_subscriptions SET failure_count = failure_count + 1, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'updated_at' => $now ?? gmdate('Y-m-d H:i:s')]);
    }

    /** @return array<string,mixed>|null */
    public function findActive(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM push_subscriptions WHERE id = :id AND disabled_at IS NULL');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function recentSafe(int $limit = 20): array
    {
        $statement = $this->db->prepare(
            'SELECT id, endpoint_hash, language, client_class, created_at, updated_at, last_success_at,
                    failure_count, disabled_at FROM push_subscriptions ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function endpointHash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
