<?php

declare(strict_types=1);

namespace PingFloodWatch;

final class Logger
{
    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('application', 'info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('application', 'error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function write(string $channel, string $level, string $message, array $context = []): void
    {
        $directory = (string) Config::get('storage.logs');
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }

        $safeContext = self::redact($context);
        $line = json_encode([
            'timestamp' => gmdate('c'),
            'level' => strtoupper($level),
            'channel' => $channel,
            'message' => $message,
            'context' => $safeContext,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        @file_put_contents($directory . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $channel) . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/token|secret|password|authorization|api[_-]?key|private[_-]?key|vapid|endpoint$|p256dh|^auth$/i', (string) $key)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }
}
