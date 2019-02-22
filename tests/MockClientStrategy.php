<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\Strategy\DiscoveryStrategy;
use Http\Mock\Client as Mock;

/**
 * This class is ported from Http\Discovery\Strategy\MockClientStrategy.
 *
 * This versions provides the Mock client when an asycn client is required too.
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
                return [['class' => Mock::class, 'condition' => Mock::class]];
            default:
                return [];
       }
    }
}
