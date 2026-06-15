<?php

declare(strict_types=1);

namespace Sentry\Tests\Fixtures\OpenTelemetry;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class TestDiscoveryStrategy
{
    /**
     * @return array<int, array{class: string, condition: string}>
     */
    public static function getCandidates(string $type): array
    {
        if (is_a(ClientInterface::class, $type, true)) {
            return [['class' => StubOtelHttpClient::class, 'condition' => StubOtelHttpClient::class]];
        }

        if (is_a(RequestFactoryInterface::class, $type, true) || is_a(StreamFactoryInterface::class, $type, true)) {
            return [['class' => Psr17Factory::class, 'condition' => Psr17Factory::class]];
        }

        return [];
    }
}
