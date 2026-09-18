<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpCookieCollector;

final class HttpCookieCollectorTest extends TestCase
{
    public function testRequestCookiesAreFiltered(): void
    {
        $this->assertSame([
            'theme' => 'dark',
            'session_id' => '[Filtered]',
            'empty' => '',
            'preferences' => ['name' => 'Alice', 'password' => '[Filtered]'],
        ], HttpCookieCollector::collect(new DataCollectionOptions(), [
            'theme' => 'dark',
            'session_id' => 'secret',
            'empty' => '',
            'preferences' => ['name' => 'Alice', 'password' => 'secret'],
        ]));
    }

    public function testDisabledCollectionDoesNotConsumeResponseCookiePairs(): void
    {
        $options = new DataCollectionOptions(['cookies' => ['mode' => 'off']]);
        $this->assertNull(HttpCookieCollector::collect($options, ['theme' => 'parsed']));
        $this->assertNull(HttpCookieCollector::collectPairs($options, (static function (): \Generator {
            throw new \LogicException('Disabled collection must not consume cookie pairs.');
            yield ['theme', 'parsed'];
        })()));
    }

    public function testResponseCookiePairsAreConsumedOnceAndPreserveRepeatedAndNullValues(): void
    {
        $iterations = 0;
        $cookies = (static function () use (&$iterations): \Generator {
            ++$iterations;
            yield ['theme', null];
            yield ['theme', 'light'];
            yield ['theme', 'dark'];
            yield ['locale', null];
            yield ['session_id', 'secret'];
            yield ['session_id', 'another-secret'];
        })();

        $this->assertSame([
            ['theme', null],
            ['theme', 'light'],
            ['theme', 'dark'],
            ['locale', null],
            ['session_id', '[Filtered]'],
            ['session_id', '[Filtered]'],
        ], HttpCookieCollector::collectPairs(new DataCollectionOptions(), $cookies));
        $this->assertSame(1, $iterations);
    }

    public function testEmptyCookieCollectionsArePreserved(): void
    {
        $options = new DataCollectionOptions();
        $cookies = (static function (): \Generator {
            yield from [];
        })();

        $this->assertSame([], HttpCookieCollector::collect($options, []));
        $this->assertSame([], HttpCookieCollector::collectPairs($options, $cookies));
    }

    public function testLargeParsedCookieCollectionsArePreserved(): void
    {
        $cookies = ['preferences' => array_fill(0, 100001, 'value')];

        $this->assertSame($cookies, HttpCookieCollector::collect(new DataCollectionOptions(), $cookies));
    }

    public function testResponseCookieAllowListPreservesRepeatedAndNullValues(): void
    {
        $options = new DataCollectionOptions(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'locale', 'api_token']]]);
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

    public function testDictionariesAndPairsApplyTheSameFilteringRules(): void
    {
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
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = [$name, $value];
        }

        try {
            foreach ([['mode' => 'denyList'], ['mode' => 'allowList', 'terms' => ['theme', 'preferences', 'name', '0', 'object', 'resource', 'float', 'empty']]] as $behavior) {
                $options = new DataCollectionOptions(['cookies' => $behavior]);
                $expected = [
                    'theme' => 'dark',
                    'preferences' => ['name' => 'Alice', 'password' => '[Filtered]', 0 => 'numeric'],
                    'session_id' => '[Filtered]',
                    'object' => '[Filtered]',
                    'resource' => '[Filtered]',
                    'float' => '[Filtered]',
                    'empty' => null,
                ];

                $this->assertSame($expected, HttpCookieCollector::collect($options, $cookies));
                $this->assertSame($expected, array_column(HttpCookieCollector::collectPairs($options, $pairs), 1, 0));
            }
        } finally {
            fclose($resource);
        }
    }
}
