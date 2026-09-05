<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SanitizedFixtureTest extends TestCase
{
    public function testDocumentedProviderSamplesAreValidAndSanitized(): void
    {
        foreach (glob(APP_ROOT . '/docs/provider_samples/*.json') ?: [] as $file) {
            $contents = file_get_contents($file);
            self::assertIsArray(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
            self::assertDoesNotMatchRegularExpression('/api[_-]?key|authorization|bearer|password/i', $contents);
        }
    }
}
