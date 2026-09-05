<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

final class PushSubscriptionValidator
{
    /** @param array<string,mixed> $input @return array{endpoint:string,p256dh:string,auth:string,content_encoding:string,language:string} */
    public function subscribe(array $input): array
    {
        $this->assertOnlyKeys($input, ['endpoint', 'expirationTime', 'keys', 'contentEncoding', 'language']);
        $endpoint = $this->endpoint($input['endpoint'] ?? null);
        if (!isset($input['keys']) || !is_array($input['keys'])) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        $this->assertOnlyKeys($input['keys'], ['p256dh', 'auth']);
        $p256dh = $this->encodedKey($input['keys']['p256dh'] ?? null, 65);
        $auth = $this->encodedKey($input['keys']['auth'] ?? null, 16);
        $encoding = (string) ($input['contentEncoding'] ?? 'aes128gcm');
        if (!in_array($encoding, ['aes128gcm', 'aesgcm'], true)) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        $language = $input['language'] ?? null;
        if (!is_string($language) || !in_array($language, ['en', 'th'], true)) {
            throw new \InvalidArgumentException('INVALID_LANGUAGE');
        }
        if (array_key_exists('expirationTime', $input)
            && $input['expirationTime'] !== null && !is_int($input['expirationTime']) && !is_float($input['expirationTime'])) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        return compact('endpoint', 'p256dh', 'auth') + ['content_encoding' => $encoding, 'language' => $language];
    }

    /** @param array<string,mixed> $input */
    public function unsubscribe(array $input): string
    {
        $this->assertOnlyKeys($input, ['endpoint']);
        return $this->endpoint($input['endpoint'] ?? null);
    }

    public static function clientClass(string $userAgent): string
    {
        $agent = strtolower($userAgent);
        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ipod')) {
            return 'ios';
        }
        if (str_contains($agent, 'android')) {
            return 'android';
        }
        return $agent === '' ? 'unknown' : 'desktop';
    }

    private function endpoint(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        return $value;
    }

    private function encodedKey(mixed $value, int $expectedBytes): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 255 || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if (!is_string($decoded) || strlen($decoded) !== $expectedBytes) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
        return $value;
    }

    /** @param array<string,mixed> $values @param list<string> $allowed */
    private function assertOnlyKeys(array $values, array $allowed): void
    {
        if (array_diff(array_keys($values), $allowed) !== []) {
            throw new \InvalidArgumentException('INVALID_SUBSCRIPTION');
        }
    }
}
