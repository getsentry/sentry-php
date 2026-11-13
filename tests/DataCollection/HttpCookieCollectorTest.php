<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpCookieCollector;
use Sentry\Options;

final class HttpCookieCollectorTest extends TestCase
{
    public function testRequestCookiesAreFiltered(): void
    {
        $this->assertSame([
            'theme' => 'dark',
            'session_id' => '[Filtered]',
            'empty' => '',
            'preferences' => ['name' => 'Alice', 'password' => '[Filtered]'],
        ], HttpCookieCollector::collect($this->options(), [
            'theme' => 'dark',
            'session_id' => 'secret',
            'empty' => '',
            'preferences' => ['name' => 'Alice', 'password' => 'secret'],
        ]));
    }

    public function testDisabledCollectionDoesNotCollectCookies(): void
    {
        $options = $this->options(['cookies' => ['mode' => 'off']]);
        $cookies = [['theme', 'parsed']];

        $this->assertNull(HttpCookieCollector::collect($options, ['theme' => 'parsed']));
        $this->assertNull(HttpCookieCollector::collectPairs($options, $cookies));
    }

    public function testResponseCookiePairsPreserveRepeatedAndNullValues(): void
    {
        $cookies = [
            ['theme', null],
            ['theme', 'light'],
            ['theme', 'dark'],
            ['locale', null],
            ['session_id', 'secret'],
            ['session_id', 'another-secret'],
        ];

        $this->assertSame([
            ['theme', null],
            ['theme', 'light'],
            ['theme', 'dark'],
            ['locale', null],
            ['session_id', '[Filtered]'],
            ['session_id', '[Filtered]'],
        ], HttpCookieCollector::collectPairs($this->options(), $cookies));
    }

    public function testEmptyCookieCollectionsArePreserved(): void
    {
        $options = $this->options();

        $this->assertSame([], HttpCookieCollector::collect($options, []));
        $this->assertSame([], HttpCookieCollector::collectPairs($options, []));
    }

    public function testLargeParsedCookieCollectionsArePreserved(): void
    {
        $cookies = ['preferences' => array_fill(0, 100001, 'value')];

        $this->assertSame($cookies, HttpCookieCollector::collect($this->options(), $cookies));
    }

    public function testResponseCookieAllowListPreservesRepeatedAndNullValues(): void
    {
        $options = $this->options(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'locale', 'api_token']]]);
        $cookies = [
            ['theme', null], ['theme', 'light'],
            ['locale', null],
            ['api_token', 'secret'], ['api_token', null],
            ['tracking_id', 'first'], ['tracking_id', 'second'],
        ];

        $this->assertSame([
            ['theme', null], ['theme', 'light'],
            ['locale', null],
            ['api_token', '[Filtered]'], ['api_token', '[Filtered]'],
            ['tracking_id', '[Filtered]'], ['tracking_id', '[Filtered]'],
        ], HttpCookieCollector::collectPairs($options, $cookies));
    }

    public function testDictionariesAndPairsApplyTheSameDenyListRules(): void
    {
        $options = $this->options(['cookies' => ['mode' => 'denyList']]);
        $resource = fopen('php://memory', 'r+');
        $object = new class {
            public function __toString(): string
            {
                throw new \LogicException('Cookie filtering must not invoke application code.');
            }
        };
        $cookies = [
            'theme' => 'dark',
            'preferences' => ['name' => 'Alice', 'password' => 'secret', 0 => 'numeric'],
            'session_id' => 'secret',
            'object' => $object,
            'resource' => $resource,
            'float' => \INF,
            'empty' => null,
        ];
        $pairs = [
            ['theme', 'dark'],
            ['preferences', ['name' => 'Alice', 'password' => 'secret', 0 => 'numeric']],
            ['session_id', 'secret'],
            ['object', $object],
            ['resource', $resource],
            ['float', \INF],
            ['empty', null],
        ];
        $expected = [
            'theme' => 'dark',
            'preferences' => ['name' => 'Alice', 'password' => '[Filtered]', 0 => 'numeric'],
            'session_id' => '[Filtered]',
            'object' => '[Filtered]',
            'resource' => '[Filtered]',
            'float' => '[Filtered]',
            'empty' => null,
        ];

        try {
            $this->assertSame($expected, HttpCookieCollector::collect($options, $cookies));
            $this->assertSame($expected, array_column(HttpCookieCollector::collectPairs($options, $pairs), 1, 0));
        } finally {
            fclose($resource);
        }
    }

    public function testDictionariesAndPairsApplyTheSameAllowListRules(): void
    {
        $options = $this->options(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'preferences', 'name', '0', 'object', 'resource', 'float', 'empty']]]);
        $resource = fopen('php://memory', 'r+');
        $object = new class {
            public function __toString(): string
            {
                throw new \LogicException('Cookie filtering must not invoke application code.');
            }
        };
        $cookies = [
            'theme' => 'dark',
            'preferences' => ['name' => 'Alice', 'password' => 'secret', 0 => 'numeric'],
            'session_id' => 'secret',
            'object' => $object,
            'resource' => $resource,
            'float' => \INF,
            'empty' => null,
        ];
        $pairs = [
            ['theme', 'dark'],
            ['preferences', ['name' => 'Alice', 'password' => 'secret', 0 => 'numeric']],
            ['session_id', 'secret'],
            ['object', $object],
            ['resource', $resource],
            ['float', \INF],
            ['empty', null],
        ];
        $expected = [
            'theme' => 'dark',
            'preferences' => ['name' => 'Alice', 'password' => '[Filtered]', 0 => 'numeric'],
            'session_id' => '[Filtered]',
            'object' => '[Filtered]',
            'resource' => '[Filtered]',
            'float' => '[Filtered]',
            'empty' => null,
        ];

        try {
            $this->assertSame($expected, HttpCookieCollector::collect($options, $cookies));
            $this->assertSame($expected, array_column(HttpCookieCollector::collectPairs($options, $pairs), 1, 0));
        } finally {
            fclose($resource);
        }
    }

    public function testUnavailableCookiesAreNotCollected(): void
    {
        $this->assertNull(HttpCookieCollector::collect($this->options(), null));
    }

    private function options(array $dataCollection = []): Options
    {
        return new Options(['data_collection' => $dataCollection]);
    }
}
