<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpCookieCollector;
use Sentry\DataCollection\HttpMessageType;
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
        ], HttpCookieCollector::collect($this->policy(), HttpMessageType::incomingRequest(), [
            'theme' => 'dark',
            'session_id' => 'secret',
            'empty' => '',
            'preferences' => ['name' => 'Alice', 'password' => 'secret'],
        ]));
    }

    public function testDisabledCollectionDoesNotCollectCookies(): void
    {
        $policy = $this->policy(['cookies' => ['mode' => 'off']]);
        $cookies = [['theme', 'parsed']];

        $this->assertNull(HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), ['theme' => 'parsed']));
        $this->assertNull(HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingResponse(), $cookies));
        $this->assertNull(HttpCookieCollector::collectGroupedPairs($policy, HttpMessageType::incomingResponse(), $cookies));
        $this->assertNull(HttpCookieCollector::collectGroupedPairs($policy, HttpMessageType::incomingResponse(), null));
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
        ], HttpCookieCollector::collectPairs($this->policy(), HttpMessageType::incomingResponse(), $cookies));
    }

    public function testEmptyCookieCollectionsArePreserved(): void
    {
        $policy = $this->policy();

        $this->assertSame([], HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), []));
        $this->assertSame([], HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingResponse(), []));
        $this->assertSame([], HttpCookieCollector::collectGroupedPairs($policy, HttpMessageType::incomingResponse(), []));
    }

    public function testLargeParsedCookieCollectionsArePreserved(): void
    {
        $cookies = ['preferences' => array_fill(0, 100001, 'value')];

        $this->assertSame($cookies, HttpCookieCollector::collect($this->policy(), HttpMessageType::incomingRequest(), $cookies));
    }

    public function testResponseCookieAllowListPreservesRepeatedAndNullValues(): void
    {
        $policy = $this->policy(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'locale', 'api_token']]]);
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
        ], HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingResponse(), $cookies));
    }

    public function testCookiePairsAreFilteredBeforeGrouping(): void
    {
        $policy = $this->policy(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'locale', 'language', 'api_token']]]);

        $this->assertSame([
            'theme' => [null, 'dark', 'light'],
            'locale' => null,
            'language' => 'en',
            'api_token' => ['[Filtered]', '[Filtered]'],
            'tracking' => ['[Filtered]', '[Filtered]'],
        ], HttpCookieCollector::collectGroupedPairs($policy, HttpMessageType::incomingResponse(), [
            ['theme', null], ['theme', 'dark'], ['theme', 'light'],
            ['locale', null], ['language', 'en'],
            ['api_token', 'secret'], ['api_token', null],
            ['tracking', 'one'], ['tracking', 'two'],
        ]));
    }

    public function testMalformedCookiePairsAreFiltered(): void
    {
        $this->assertSame('[Filtered]', HttpCookieCollector::collectGroupedPairs($this->policy(), HttpMessageType::incomingResponse(), null));
    }

    public function testDictionariesAndPairsApplyTheSameDenyListRules(): void
    {
        $policy = $this->policy(['cookies' => ['mode' => 'denyList']]);
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
            $this->assertSame($expected, HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), $cookies));
            $this->assertSame($expected, array_column(HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingResponse(), $pairs), 1, 0));
        } finally {
            fclose($resource);
        }
    }

    public function testDictionariesAndPairsApplyTheSameAllowListRules(): void
    {
        $policy = $this->policy(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'preferences', 'name', '0', 'object', 'resource', 'float', 'empty']]]);
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
            $this->assertSame($expected, HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), $cookies));
            $this->assertSame($expected, array_column(HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingResponse(), $pairs), 1, 0));
        } finally {
            fclose($resource);
        }
    }

    public function testLegacyModeWithoutPiiDoesNotCollectCookies(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options());

        $this->assertNull(HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), ['theme' => 'dark']));
        $this->assertNull(HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingRequest(), [['theme', 'dark']]));
        $this->assertNull(HttpCookieCollector::collectGroupedPairs($policy, HttpMessageType::incomingRequest(), null));
    }

    public function testLegacyModeWithPiiCollectsIncomingRequestCookiesUnfiltered(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => true]));
        $cookies = [['session_id', 'secret'], ['theme', 'dark']];

        $this->assertSame(
            ['session_id' => 'secret', 'theme' => 'dark'],
            HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), ['session_id' => 'secret', 'theme' => 'dark'])
        );
        $this->assertSame(
            [['session_id', 'secret'], ['theme', 'dark']],
            HttpCookieCollector::collectPairs($policy, HttpMessageType::incomingRequest(), $cookies)
        );
    }

    /**
     * @dataProvider legacyUncollectedTypeProvider
     */
    public function testLegacyModeWithPiiDoesNotCollectOtherCookies(HttpMessageType $type): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => true]));

        $this->assertNull(HttpCookieCollector::collect($policy, $type, ['theme' => 'dark']));
        $this->assertNull(HttpCookieCollector::collectPairs($policy, $type, [['theme', 'dark']]));
        $this->assertNull(HttpCookieCollector::collectGroupedPairs($policy, $type, [['theme', 'dark']]));
    }

    public static function legacyUncollectedTypeProvider(): \Generator
    {
        yield 'outgoing request' => [HttpMessageType::outgoingRequest()];
        yield 'incoming response' => [HttpMessageType::incomingResponse()];
        yield 'outgoing response' => [HttpMessageType::outgoingResponse()];
    }

    private function policy(array $dataCollection = []): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
