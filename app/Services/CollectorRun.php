<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PDO;

final class CollectorRun
{
    private int $id;

    public function __construct(private readonly PDO $db, string $collector)
    {
        $statement = $this->db->prepare(
            "INSERT INTO collector_runs (collector, status, started_at) VALUES (:collector, 'running', UTC_TIMESTAMP())"
        );
        $statement->execute(['collector' => $collector]);
        $this->id = (int) $this->db->lastInsertId();
    }

    public function finish(int $inserted, int $updated, string $message = 'Completed'): void
    {
        $statement = $this->db->prepare(
            "UPDATE collector_runs SET status = 'success', finished_at = UTC_TIMESTAMP(), records_inserted = :inserted,
                records_updated = :updated, message = :message WHERE id = :id"
        );
        $statement->execute(['id' => $this->id, 'inserted' => $inserted, 'updated' => $updated, 'message' => substr($message, 0, 500)]);
    }

    public function fail(string $code, string $message): void
    {
        $statement = $this->db->prepare(
            "UPDATE collector_runs SET status = 'failed', finished_at = UTC_TIMESTAMP(), error_code = :code,
                message = :message WHERE id = :id"
        );
        $statement->execute(['id' => $this->id, 'code' => substr($code, 0, 64), 'message' => substr($message, 0, 500)]);
    }
}
