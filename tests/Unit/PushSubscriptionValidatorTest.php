<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\PushSubscriptionValidator;

final class PushSubscriptionValidatorTest extends TestCase
{
    public function testAcceptsStrictSubscriptionAndClassifiesClientWithoutReturningUserAgent(): void
    {
        $value = (new PushSubscriptionValidator())->subscribe($this->valid());
        self::assertSame('https://push.example.test/subscription/123', $value['endpoint']);
        self::assertSame('en', $value['language']);
        self::assertSame('aes128gcm', $value['content_encoding']);
        self::assertSame('ios', PushSubscriptionValidator::clientClass('Mozilla/5.0 (iPhone) Safari'));
        self::assertSame('android', PushSubscriptionValidator::clientClass('Mozilla/5.0 Android Chrome'));
        self::assertSame('desktop', PushSubscriptionValidator::clientClass('Mozilla/5.0 Firefox'));
    }

    /** @return iterable<string,array{callable(array<string,mixed>):array<string,mixed>,string}> */
    public static function invalidSubscriptions(): iterable
    {
        yield 'http endpoint' => [static function (array $v): array { $v['endpoint'] = 'http://push.example.test/a'; return $v; }, 'INVALID_SUBSCRIPTION'];
        yield 'endpoint too long' => [static function (array $v): array { $v['endpoint'] = 'https://push.example.test/' . str_repeat('a', 2050); return $v; }, 'INVALID_SUBSCRIPTION'];
        yield 'short public key' => [static function (array $v): array { $v['keys']['p256dh'] = self::encode(str_repeat('a', 64)); return $v; }, 'INVALID_SUBSCRIPTION'];
        yield 'short auth key' => [static function (array $v): array { $v['keys']['auth'] = self::encode(str_repeat('a', 15)); return $v; }, 'INVALID_SUBSCRIPTION'];
        yield 'unknown top-level key' => [static fn (array $v): array => $v + ['tracking' => true], 'INVALID_SUBSCRIPTION'];
        yield 'unknown nested key' => [static function (array $v): array { $v['keys']['extra'] = 'x'; return $v; }, 'INVALID_SUBSCRIPTION'];
        yield 'invalid language' => [static function (array $v): array { $v['language'] = 'sv'; return $v; }, 'INVALID_LANGUAGE'];
    }

    #[DataProvider('invalidSubscriptions')]
    public function testRejectsMalformedOrExpandedStructures(callable $change, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new PushSubscriptionValidator())->subscribe($change($this->valid()));
    }

    public function testUnsubscribeAcceptsOnlyAnHttpsEndpoint(): void
    {
        $validator = new PushSubscriptionValidator();
        self::assertSame('https://push.example.test/subscription/123', $validator->unsubscribe([
            'endpoint' => 'https://push.example.test/subscription/123',
        ]));
        $this->expectException(\InvalidArgumentException::class);
        $validator->unsubscribe(['endpoint' => 'https://push.example.test/a', 'language' => 'en']);
    }

    /** @return array<string,mixed> */
    private function valid(): array
    {
        return [
            'endpoint' => 'https://push.example.test/subscription/123',
            'expirationTime' => null,
            'keys' => ['p256dh' => self::encode(str_repeat('p', 65)), 'auth' => self::encode(str_repeat('a', 16))],
            'contentEncoding' => 'aes128gcm',
            'language' => 'en',
        ];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
