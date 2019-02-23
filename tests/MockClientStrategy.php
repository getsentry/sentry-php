<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\Strategy\DiscoveryStrategy;
use Http\Mock\Client as HttpMockClient;

/**
 * This class is ported from Http\Discovery\Strategy\MockClientStrategy.
 *
 * This version provides the Mock client when an asycn client is required too.
 * PR upstream: https://github.com/php-http/discovery/pull/137
 */
final class MockClientStrategy implements DiscoveryStrategy
{
    /**
     * {@inheritdoc}
     */
    public static function getCandidates($type)
    {
        switch ($type) {
            case HttpClient::class:
            case HttpAsyncClient::class:
                return [['class' => HttpMockClient::class, 'condition' => HttpMockClient::class]];
            default:
                return [];
       }
    }
}
