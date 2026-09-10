<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\Options;
use Sentry\Tracing\Span;
use Sentry\Util\JSON;

final class HttpBodyCollectorTest extends TestCase
{
    /**
     * @dataProvider bodiesProvider
     *
     * @param mixed $body
     * @param mixed $expected
     */
    public function testBodies($body, string $type, $expected): void
    {
        $this->assertSame($expected, HttpBodyCollector::collect($this->options(), 'incomingRequest', $body, $type));
    }

    public function bodiesProvider(): \Generator
    {
        yield 'JSON' => ['{"name":"Alice","profile":{"PASSWORD":"secret"}}', 'application/json', ['name' => 'Alice', 'profile' => ['PASSWORD' => '[Filtered]']]];
        yield 'JSON suffix' => ['{"token":"secret"}', 'Application/problem+json; charset=utf-8', ['token' => '[Filtered]']];
        yield 'form' => ['profile[name]=Alice&profile[password]=secret', 'application/x-www-form-urlencoded; charset=utf-8', ['profile[name]' => 'Alice', 'profile[password]' => '[Filtered]']];
        yield 'form preserves names' => ['user.name=Alice&user+name=Bob&token=secret', 'application/x-www-form-urlencoded', ['user.name' => 'Alice', 'user name' => 'Bob', 'token' => '[Filtered]']];
        yield 'parsed form' => [['name' => 'Alice', 'password' => 'secret'], '', ['name' => 'Alice', 'password' => '[Filtered]']];
        yield 'numeric keys' => [[['token' => 'secret'], 'ok'], '', [['token' => '[Filtered]'], 'ok']];
        yield 'raw empty' => ['', 'application/json', null];
        yield 'boolean input' => [true, '', null];
        yield 'numeric input' => [123, '', null];
        yield 'encoded empty string' => ['""', 'application/json', '[Filtered]'];
        yield 'absent' => [null, '', null];
        yield 'empty parsed' => [[], '', []];
        yield 'empty object' => ['{}', 'application/json', []];
        yield 'empty array' => ['[]', 'application/json', []];
        yield 'malformed' => ['{bad', 'application/json', '[Filtered]'];
        yield 'scalar string' => ['"secret"', 'application/json', '[Filtered]'];
        yield 'scalar number' => ['123', 'application/json', '[Filtered]'];
        yield 'JSON null' => ['null', 'application/json', '[Filtered]'];
        yield 'unsupported' => ['secret', 'text/plain', '[Filtered]'];
        yield 'nonapplication suffix' => ['{}', 'text/example+json', '[Filtered]'];
        yield 'whitespace' => [' ', 'application/json', '[Filtered]'];
    }

    /**
     * @dataProvider bodyDirectionProvider
     */
    public function testConfiguredDefaultsCollectEveryDirection(string $direction): void
    {
        $this->assertSame([], HttpBodyCollector::collect($this->options(), $direction, []));
    }

    /**
     * @dataProvider legacyBodyDirectionProvider
     */
    public function testLegacyModeDoesNotCollectBodies(string $direction, bool $sendDefaultPii): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => $sendDefaultPii]));

        $this->assertNull(HttpBodyCollector::collect($policy, $direction, []));
    }

    /**
     * @dataProvider selectedBodyDirectionProvider
     */
    public function testConfiguredBodyDirectionsCanBeSelected(string $selected, string $direction, $expected): void
    {
        $options = $this->options(['data_collection' => ['http_bodies' => [$selected]]]);

        $this->assertSame($expected, HttpBodyCollector::collect($options, $direction, []));
    }

    /**
     * @dataProvider bodyDirectionProvider
     */
    public function testEmptyBodySelectionDisablesEveryDirection(string $direction): void
    {
        $options = $this->options(['data_collection' => ['http_bodies' => []]]);

        $this->assertNull(HttpBodyCollector::collect($options, $direction, []));
    }

    /**
     * @dataProvider bodyDirectionProvider
     */
    public function testMissingOptionsHaveNoBodyLimit(string $direction): void
    {
        $this->assertSame(0, HttpBodyCollector::getMaxBodyLength(DataCollectionPolicy::fromOptions(null), $direction));
    }

    public function bodyDirectionProvider(): \Generator
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $direction) {
            yield $direction => [$direction];
        }
    }

    public function legacyBodyDirectionProvider(): \Generator
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $direction) {
            yield $direction . ' with PII disabled' => [$direction, false];
            yield $direction . ' with PII enabled' => [$direction, true];
        }
    }

    public function selectedBodyDirectionProvider(): \Generator
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $selected) {
            yield $selected . ' selected' => [$selected, $selected, []];
            foreach (array_diff(DataCollectionOptions::HTTP_BODY_TYPES, [$selected]) as $direction) {
                yield $selected . ' excludes ' . $direction => [$selected, $direction, null];
            }
        }
    }

    /**
     * @dataProvider disabledRequestSizeProvider
     */
    public function testRequestSizeDisableDoesNotDisableResponses(string $size): void
    {
        $options = $this->options(['max_request_body_size' => $size]);

        $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', []));
        $this->assertNull(HttpBodyCollector::collect($options, 'outgoingRequest', []));
        $this->assertSame([], HttpBodyCollector::collect($options, 'incomingResponse', []));
        $this->assertSame([], HttpBodyCollector::collect($options, 'outgoingResponse', []));
    }

    public function disabledRequestSizeProvider(): \Generator
    {
        yield 'none' => ['none'];
        yield 'never' => ['never'];
    }

    /**
     * @dataProvider rawBodySizeBoundaryProvider
     *
     * @param array<string, string>|null $expected
     */
    public function testRawBodySizeBoundaries(string $direction, string $size, int $length, ?array $expected): void
    {
        $options = $this->options(['max_request_body_size' => $size]);
        $body = ['x' => str_repeat('a', $length - 8)];
        $json = JSON::encode($body);

        $this->assertSame($length, \strlen($json));
        $this->assertSame($expected, HttpBodyCollector::collect($options, $direction, $json, 'application/json'));
    }

    public function rawBodySizeBoundaryProvider(): \Generator
    {
        foreach (['incomingRequest', 'outgoingRequest'] as $direction) {
            foreach (['small' => 1000, 'medium' => 10000, 'always' => 100000] as $size => $limit) {
                yield $direction . ' ' . $size . ' below limit' => [$direction, $size, $limit - 1, ['x' => str_repeat('a', $limit - 9)]];
                yield $direction . ' ' . $size . ' at limit' => [$direction, $size, $limit, ['x' => str_repeat('a', $limit - 8)]];
                yield $direction . ' ' . $size . ' above limit' => [$direction, $size, $limit + 1, null];
            }
        }
        foreach (['incomingResponse', 'outgoingResponse'] as $direction) {
            yield $direction . ' below limit' => [$direction, 'small', 99999, ['x' => str_repeat('a', 99991)]];
            yield $direction . ' at limit' => [$direction, 'small', 100000, ['x' => str_repeat('a', 99992)]];
            yield $direction . ' above limit' => [$direction, 'small', 100001, null];
        }
    }

    /**
     * @dataProvider bodyLimitProvider
     */
    public function testBodyLimits(string $direction, string $size, int $expectedLimit): void
    {
        $this->assertSame($expectedLimit, HttpBodyCollector::getMaxBodyLength($this->options(['max_request_body_size' => $size]), $direction));
    }

    public function bodyLimitProvider(): \Generator
    {
        yield 'small request' => ['incomingRequest', 'small', 1000];
        yield 'medium request' => ['outgoingRequest', 'medium', 10000];
        yield 'always request' => ['incomingRequest', 'always', 100000];
        yield 'small response' => ['incomingResponse', 'small', 100000];
        yield 'medium response' => ['outgoingResponse', 'medium', 100000];
    }

    public function testLimitsMeasureBytesBeforeFiltering(): void
    {
        $options = $this->options(['max_request_body_size' => 'small']);
        $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', JSON::encode(['password' => str_repeat('é', 500)]), 'application/json'));
        $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', str_repeat('é', 501), 'text/plain'));
    }

    /**
     * @dataProvider bodyDirectionProvider
     */
    public function testParsedArraysAreNotSerializedForSizeChecks(string $direction): void
    {
        $body = ['name' => str_repeat('a', 100001), 'password' => 'secret'];
        $expected = ['name' => $body['name'], 'password' => '[Filtered]'];

        $this->assertSame($expected, HttpBodyCollector::collect($this->options(['max_request_body_size' => 'small']), $direction, $body));
    }

    public function testFilteringDoesNotRecheckTheResultSize(): void
    {
        $body = [];
        for ($i = 0; $i < 60; ++$i) {
            $body['token' . $i] = '';
        }
        $raw = JSON::encode($body);
        $expected = array_fill_keys(array_keys($body), '[Filtered]');
        $this->assertLessThan(1000, \strlen($raw));
        $this->assertGreaterThan(1000, \strlen(JSON::encode($expected)));
        $this->assertSame($expected, HttpBodyCollector::collect($this->options(['max_request_body_size' => 'small']), 'incomingRequest', $raw, 'application/json'));
    }

    public function testNormalizationDoesNotInvokeCallbacks(): void
    {
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new \LogicException('Must not serialize');
            }

            public function __toString(): string
            {
                throw new \LogicException('Must not cast');
            }
        };
        $resource = fopen('php://temp', 'w+');
        try {
            $body = ['object' => $object, 'resource' => $resource, 'callback' => static function (): void {
                throw new \LogicException('Must not call');
            }];
            $this->assertSame(array_fill_keys(array_keys($body), '[Filtered]'), HttpBodyCollector::collect($this->options(), 'incomingRequest', $body));
        } finally {
            fclose($resource);
        }
    }

    public function testRecursiveArraysAreBounded(): void
    {
        $body = [];
        $body['child'] = &$body;
        $result = HttpBodyCollector::collect($this->options(), 'incomingRequest', $body);
        for ($i = 0; $i < 511; ++$i) {
            $this->assertIsArray($result);
            $result = $result['child'];
        }
        $this->assertSame('[Filtered]', $result);
    }

    public function testHeaderAndCookieTermsDoNotAffectBodies(): void
    {
        $options = $this->options(['data_collection' => ['http_headers' => ['terms' => ['name']], 'cookies' => ['terms' => ['name']]]]);

        $this->assertSame(['name' => 'Alice'], HttpBodyCollector::collect($options, 'incomingRequest', ['name' => 'Alice']));
    }

    /**
     * @dataProvider explicitBodyDataProvider
     *
     * @param mixed $explicit
     */
    public function testExplicitBodyDataWins($explicit): void
    {
        $span = (new Span())->setData(['http.request.body.data' => $explicit]);
        $data = HttpDataCollector::collectBodyData($this->options(), 'incomingRequest', ['password' => 'secret']);

        $span->setData(array_diff_key($data, $span->getData()));

        $this->assertSame($explicit, $span->getData()['http.request.body.data']);
    }

    public function explicitBodyDataProvider(): \Generator
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'populated' => [['password' => 'explicit']];
    }

    public function testEmptyBodyDataIsRetained(): void
    {
        $this->assertSame(
            ['http.response.body.data' => []],
            HttpDataCollector::collectBodyData($this->options(), 'outgoingResponse', [])
        );
    }

    public function testServerStreamPositionIsRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"Alice","token":"secret"}');
        $request->getBody()->seek(7);

        $this->assertSame(['name' => 'Alice', 'token' => '[Filtered]'], HttpBodyCollector::collectServerRequest($this->options(), $request));
        $this->assertSame(7, $request->getBody()->tell());
    }

    public function testParsedServerBodyIsAuthoritative(): void
    {
        $request = (new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"raw"}'))->withParsedBody([]);

        $this->assertSame([], HttpBodyCollector::collectServerRequest($this->options(), $request));
    }

    public function testNonSeekableServerStreamIsNotCollected(): void
    {
        $request = new ServerRequest('POST', '/', [], 'secret');

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(), $request->withBody(new NoSeekStream($request->getBody()))));
    }

    public function testOversizedServerStreamReadIsBoundedAndRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], str_repeat('a', 1001));
        $request->getBody()->seek(3);

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(['max_request_body_size' => 'small']), $request));
        $this->assertSame(3, $request->getBody()->tell());
    }

    public function testDeclaredOversizedServerStreamPreservesPosition(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Length' => '1001'], str_repeat('a', 1001));
        $request->getBody()->seek(3);

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(['max_request_body_size' => 'small']), $request));
        $this->assertSame(3, $request->getBody()->tell());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function options(array $options = []): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options($options + ['data_collection' => [], 'max_request_body_size' => 'always']));
    }
}
