<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptionsNormalizer;

final class DataCollectionOptionsNormalizerTest extends TestCase
{
    public function testNormalizeHttpHeadersAppliesSingleConfigurationToBothDirections(): void
    {
        $httpHeaders = DataCollectionOptionsNormalizer::normalizeHttpHeaders([
            'mode' => 'allowList',
            'terms' => ['x-request-id'],
        ]);

        $this->assertSame('allowList', $httpHeaders['request']['mode']);
        $this->assertSame(['x-request-id'], $httpHeaders['request']['terms']);
        $this->assertSame('allowList', $httpHeaders['response']['mode']);
        $this->assertSame(['x-request-id'], $httpHeaders['response']['terms']);
    }

    public function testNormalizeHttpHeadersMergesDirectionDefaults(): void
    {
        $httpHeaders = DataCollectionOptionsNormalizer::normalizeHttpHeaders([
            'request' => [
                'mode' => 'off',
            ],
        ]);

        $this->assertSame('off', $httpHeaders['request']['mode']);
        $this->assertSame([], $httpHeaders['request']['terms']);
        $this->assertSame('denyList', $httpHeaders['response']['mode']);
        $this->assertSame([], $httpHeaders['response']['terms']);
    }

    public function testNormalizeGenAiMergesDefaults(): void
    {
        $genAi = DataCollectionOptionsNormalizer::normalizeGenAi([
            'inputs' => false,
        ]);

        $this->assertFalse($genAi['inputs']);
        $this->assertTrue($genAi['outputs']);
    }
}
