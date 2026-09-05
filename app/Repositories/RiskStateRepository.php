<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use PDO;

final class RiskStateRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string,mixed> $risk */
    public function save(string $type, array $risk): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO risk_state (risk_type, severity, reason_codes_json, message_key, context_json, calculated_at)
             VALUES (:type, :severity, :reasons, :message_key, :context, :calculated_at)
             ON DUPLICATE KEY UPDATE severity = VALUES(severity), reason_codes_json = VALUES(reason_codes_json),
                message_key = VALUES(message_key), context_json = VALUES(context_json), calculated_at = VALUES(calculated_at)'
        );
        $statement->execute([
            'type' => $type,
            'severity' => $risk['severity'],
            'reasons' => json_encode($risk['reason_codes'], JSON_THROW_ON_ERROR),
            'message_key' => $risk['message_key'],
            'context' => json_encode($risk['context'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'calculated_at' => $risk['calculated_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function get(string $type): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM risk_state WHERE risk_type = :type');
        $statement->execute(['type' => $type]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['reason_codes'] = json_decode((string) $row['reason_codes_json'], true) ?: [];
        $row['context'] = json_decode((string) $row['context_json'], true) ?: [];
        unset($row['reason_codes_json'], $row['context_json']);
        return $row;
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $result = [];
        foreach (['river', 'weather', 'combined'] as $type) {
            $row = $this->get($type);
            if ($row !== null) {
                $result[$type] = $row;
            }
        }
        return $result;
    }
}
