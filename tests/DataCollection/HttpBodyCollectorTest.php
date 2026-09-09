<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\Options;
use Sentry\Tracing\Span;

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

    public function testDirectionSelectionAndLegacyPii(): void
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $direction) {
            foreach ([false, true] as $pii) {
                $this->assertSame([], HttpBodyCollector::collect($this->options(['send_default_pii' => $pii]), $direction, []));
                $this->assertNull(HttpBodyCollector::collect(new Options(['send_default_pii' => $pii]), $direction, []));
                foreach (DataCollectionOptions::HTTP_BODY_TYPES as $selected) {
                    $options = $this->options(['data_collection' => ['http_bodies' => [$selected]]]);
                    $this->assertSame($direction === $selected ? [] : null, HttpBodyCollector::collect($options, $direction, []));
                }
            }
            $this->assertNull(HttpBodyCollector::collect($this->options(['data_collection' => ['http_bodies' => []]]), $direction, []));
            $this->assertSame(0, HttpBodyCollector::getMaxBodyLength(null, $direction));
        }
    }

    public function testRequestSizeDisableDoesNotDisableResponses(): void
    {
        foreach (['none', 'never'] as $size) {
            $options = $this->options(['max_request_body_size' => $size]);
            $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', []));
            $this->assertNull(HttpBodyCollector::collect($options, 'outgoingRequest', []));
            $this->assertSame([], HttpBodyCollector::collect($options, 'incomingResponse', []));
            $this->assertSame([], HttpBodyCollector::collect($options, 'outgoingResponse', []));
        }
    }

    public function testEverySizeBoundary(): void
    {
        foreach (DataCollectionOptions::HTTP_BODY_TYPES as $direction) {
            foreach (['small' => 1000, 'medium' => 10000, 'always' => 100000] as $size => $limit) {
                if (strpos($direction, 'Response') !== false) {
                    $limit = 100000;
                }
                $options = $this->options(['max_request_body_size' => $size]);
                $this->assertSame($limit, HttpBodyCollector::getMaxBodyLength($options, $direction));
                foreach ([-1, 0, 1] as $delta) {
                    $body = ['x' => str_repeat('a', $limit - 8 + $delta)];
                    $json = json_encode($body);
                    $this->assertSame($limit + $delta, \strlen($json));
                    $expected = $delta > 0 ? null : $body;
                    $this->assertSame($expected, HttpBodyCollector::collect($options, $direction, $body));
                    $this->assertSame($expected, HttpBodyCollector::collect($options, $direction, $json, 'application/json'));
                }
            }
        }
    }

    public function testLimitsMeasureBytesBeforeFiltering(): void
    {
        $options = $this->options(['max_request_body_size' => 'small']);
        $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', ['password' => str_repeat('é', 500)]));
        $this->assertNull(HttpBodyCollector::collect($options, 'incomingRequest', str_repeat('é', 501), 'text/plain'));
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

    public function testCustomTermsDoNotAffectBodiesAndExplicitAttributesWin(): void
    {
        $options = $this->options(['data_collection' => ['http_headers' => ['terms' => ['name']], 'cookies' => ['terms' => ['name']]]]);
        $this->assertSame(['name' => 'Alice'], HttpBodyCollector::collect($options, 'incomingRequest', ['name' => 'Alice']));
        foreach ([null, [], ['password' => 'explicit']] as $explicit) {
            $span = new Span();
            $span->setData(['http.request.body.data' => $explicit]);
            HttpDataCollector::setMissingSpanData($span, HttpDataCollector::collectBodyData($options, 'incomingRequest', ['password' => 'secret']));
            $this->assertSame($explicit, $span->getData()['http.request.body.data']);
        }
        $this->assertSame(['http.response.body.data' => []], HttpDataCollector::collectBodyData($options, 'outgoingResponse', []));
    }

    public function testServerStreamPositionIsRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"Alice","token":"secret"}');
        $request->getBody()->seek(7);
        $this->assertSame(['name' => 'Alice', 'token' => '[Filtered]'], HttpBodyCollector::collectServerRequest($this->options(), $request));
        $this->assertSame(7, $request->getBody()->tell());
        $this->assertSame([], HttpBodyCollector::collectServerRequest($this->options(), $request->withParsedBody([])));
        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(), $request->withBody(new NoSeekStream($request->getBody()))));
    }

    public function testServerStreamReadIsBoundedAndRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], str_repeat('a', 1001));
        $request->getBody()->seek(3);
        $options = $this->options(['max_request_body_size' => 'small']);
        $this->assertNull(HttpBodyCollector::collectServerRequest($options, $request));
        $this->assertSame(3, $request->getBody()->tell());
        $this->assertNull(HttpBodyCollector::collectServerRequest($options, $request->withHeader('Content-Length', '1001')));
        $this->assertSame(3, $request->getBody()->tell());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function options(array $options = []): Options
    {
        return new Options($options + ['data_collection' => [], 'max_request_body_size' => 'always']);
    }
}
