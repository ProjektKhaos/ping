<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

final class NotificationPayloadFactory
{
    /** @param array<string,mixed> $outbox @return array<string,mixed> */
    public function create(array $outbox, string $language): array
    {
        $language = in_array($language, ['en', 'th'], true) ? $language : 'en';
        $severity = (string) $outbox['severity'];
        $eventType = (string) $outbox['event_type'];
        $titleKey = $eventType === 'cleared' ? 'push.title.cleared' : 'push.title.' . $severity;
        $bodyKey = $eventType === 'cleared' ? 'push.body.cleared' : 'push.body.' . $severity;
        $alertId = (int) $outbox['alert_id'];
        return [
            'title' => t($titleKey, [], $language),
            'body' => t($bodyKey, [], $language),
            'severity' => $severity,
            'alert_id' => $alertId,
            'event_type' => $eventType,
            'url' => url('alerts.php?lang=' . rawurlencode($language)),
            'tag' => 'pfw-alert-' . base_convert((string) $alertId, 10, 36),
            'timestamp' => (string) $outbox['created_at'] . 'Z',
        ];
    }
}
