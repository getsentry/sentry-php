<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpBodySourceInterface;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\Psr7MessageBodySource;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
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
        $this->assertSame($expected, HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), $body, $type));
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
     * @dataProvider sourceBodiesProvider
     *
     * @param array<array-key, mixed>|string|null $body
     * @param mixed                               $expected
     */
    public function testSourceBodiesUseSharedDecodingAndFiltering($body, string $contentType, $expected): void
    {
        $source = $this->createMock(HttpBodySourceInterface::class);
        $source->method('getKnownLength')->willReturn(null);
        $source->expects($this->once())->method('read')->with(100000)->willReturn($body);
        $source->expects(\is_string($body) && $body !== '' ? $this->once() : $this->never())
            ->method('getContentType')->willReturn($contentType);

        $this->assertSame($expected, HttpBodyCollector::collectSource($this->options(), HttpMessageType::incomingRequest(), $source));
    }

    public function sourceBodiesProvider(): \Generator
    {
        yield 'raw body' => ['{"name":"Alice","password":"secret"}', 'application/json', ['name' => 'Alice', 'password' => '[Filtered]']];
        yield 'parsed body' => [['name' => 'Alice', 'password' => 'secret'], '', ['name' => 'Alice', 'password' => '[Filtered]']];
        yield 'unavailable' => [null, '', null];
    }

    /**
     * @dataProvider disabledSourceProvider
     *
     * @param array<string, mixed>|null $options
     */
    public function testDisabledSourcesAreNotAccessed(?array $options, HttpMessageType $messageType): void
    {
        $policy = DataCollectionPolicy::fromOptions($options === null ? null : new Options($options));
        $source = $this->createMock(HttpBodySourceInterface::class);
        $source->expects($this->never())->method('getKnownLength');
        $source->expects($this->never())->method('getContentType');
        $source->expects($this->never())->method('read');

        $this->assertNull(HttpBodyCollector::collectSource($policy, $messageType, $source));
    }

    public function disabledSourceProvider(): \Generator
    {
        yield 'no client options' => [null, HttpMessageType::incomingRequest()];
        yield 'legacy' => [[], HttpMessageType::incomingRequest()];
        yield 'legacy PII enabled' => [['send_default_pii' => true], HttpMessageType::incomingRequest()];
        yield 'all bodies disabled' => [['data_collection' => ['http_bodies' => []]], HttpMessageType::incomingRequest()];
        yield 'unselected direction' => [['data_collection' => ['http_bodies' => ['incomingRequest']]], HttpMessageType::outgoingResponse()];
        yield 'request size none' => [['data_collection' => [], 'max_request_body_size' => 'none'], HttpMessageType::incomingRequest()];
        yield 'request size never' => [['data_collection' => [], 'max_request_body_size' => 'never'], HttpMessageType::outgoingRequest()];
    }

    public function testKnownOversizedSourcesAreNotRead(): void
    {
        $source = $this->createMock(HttpBodySourceInterface::class);
        $source->expects($this->once())->method('getKnownLength')->willReturn(1001);
        $source->expects($this->never())->method('getContentType');
        $source->expects($this->never())->method('read');

        $this->assertNull(HttpBodyCollector::collectSource($this->options(['max_request_body_size' => 'small']), HttpMessageType::incomingRequest(), $source));
    }

    /**
     * @dataProvider bodyLimitProvider
     */
    public function testSourcesReceiveTheConfiguredReadLimit(HttpMessageType $messageType, string $size, int $limit): void
    {
        $source = $this->createMock(HttpBodySourceInterface::class);
        $source->method('getKnownLength')->willReturn(null);
        $source->expects($this->never())->method('getContentType');
        $source->expects($this->once())->method('read')->with($limit)->willReturn([]);

        $this->assertSame([], HttpBodyCollector::collectSource($this->options(['max_request_body_size' => $size]), $messageType, $source));
    }

    /**
     * @dataProvider rawSourceBodySizeProvider
     *
     * @param array<string, string>|null $expected
     */
    public function testRawSourceBodySizeIsCheckedBeforeDecoding(int $length, ?int $knownLength, ?array $expected): void
    {
        $body = str_pad('{"name":"Alice","password":"secret"}', $length, ' ');
        $source = $this->createMock(HttpBodySourceInterface::class);
        $source->method('getKnownLength')->willReturn($knownLength);
        $source->expects($this->once())->method('read')->with(1000)->willReturn($body);
        $source->expects($expected === null ? $this->never() : $this->once())
            ->method('getContentType')->willReturn('application/json');

        $this->assertSame($expected, HttpBodyCollector::collectSource($this->options(['max_request_body_size' => 'small']), HttpMessageType::incomingRequest(), $source));
    }

    public function rawSourceBodySizeProvider(): \Generator
    {
        yield 'at limit with unknown length' => [1000, null, ['name' => 'Alice', 'password' => '[Filtered]']];
        yield 'over limit with unknown length' => [1001, null, null];
        yield 'over limit with understated length' => [1001, 1000, null];
        yield 'over limit with zero length' => [1001, 0, null];
    }

    /**
     * @dataProvider bodyDirectionProvider
     */
    public function testConfiguredDefaultsCollectEveryDirection(HttpMessageType $direction): void
    {
        $this->assertSame([], HttpBodyCollector::collect($this->options(), $direction, []));
    }

    public function testSelectedBodyDirectionIsCollected(): void
    {
        $options = $this->options(['data_collection' => ['http_bodies' => ['outgoingRequest']]]);

        $this->assertSame([], HttpBodyCollector::collect($options, HttpMessageType::outgoingRequest(), []));
    }

    public function bodyDirectionProvider(): \Generator
    {
        foreach (self::bodyTypes() as $direction) {
            yield (string) $direction => [$direction];
        }
    }

    /**
     * @dataProvider bodySizeBoundaryProvider
     *
     * @param array<string, string>|null $expected
     */
    public function testRawBodySizeBoundaries(HttpMessageType $direction, string $size, int $length, ?array $expected): void
    {
        $options = $this->options(['max_request_body_size' => $size]);
        $body = ['x' => str_repeat('a', $length - 8)];
        $json = JSON::encode($body);

        $this->assertSame($length, \strlen($json));
        $this->assertSame($expected, HttpBodyCollector::collect($options, $direction, $json, 'application/json'));
    }

    public function bodySizeBoundaryProvider(): \Generator
    {
        yield 'request at limit' => [HttpMessageType::incomingRequest(), 'small', 1000, ['x' => str_repeat('a', 992)]];
        yield 'request above limit' => [HttpMessageType::incomingRequest(), 'small', 1001, null];
        yield 'response at limit' => [HttpMessageType::outgoingResponse(), 'small', 100000, ['x' => str_repeat('a', 99992)]];
        yield 'response above limit' => [HttpMessageType::outgoingResponse(), 'small', 100001, null];
    }

    /**
     * @dataProvider bodySizeBoundaryProvider
     *
     * @param array<string, string>|null $expected
     */
    public function testParsedBodySizeBoundaries(HttpMessageType $direction, string $size, int $length, ?array $expected): void
    {
        $body = ['x' => str_repeat('a', $length - 8)];

        $this->assertSame($expected, HttpBodyCollector::collect($this->options(['max_request_body_size' => $size]), $direction, $body, '', $length));
    }

    public function testKnownLengthDoesNotReplaceRawBodySizeCheck(): void
    {
        $policy = $this->options(['max_request_body_size' => 'small']);
        $body = JSON::encode(['password' => str_repeat('a', 1000)]);

        $this->assertNull(HttpBodyCollector::collect($policy, HttpMessageType::incomingRequest(), $body, 'application/json', 1));
        $this->assertNull(HttpBodyCollector::collect($policy, HttpMessageType::incomingRequest(), '{}', 'application/json', 1001));
    }

    public function bodyLimitProvider(): \Generator
    {
        yield 'small request' => [HttpMessageType::incomingRequest(), 'small', 1000];
        yield 'medium request' => [HttpMessageType::outgoingRequest(), 'medium', 10000];
        yield 'always request' => [HttpMessageType::incomingRequest(), 'always', 100000];
        yield 'small response' => [HttpMessageType::incomingResponse(), 'small', 100000];
        yield 'medium response' => [HttpMessageType::outgoingResponse(), 'medium', 100000];
    }

    public function testLimitsMeasureBytesBeforeFiltering(): void
    {
        $options = $this->options(['max_request_body_size' => 'small']);
        $this->assertNull(HttpBodyCollector::collect($options, HttpMessageType::incomingRequest(), JSON::encode(['password' => str_repeat('é', 500)]), 'application/json'));
        $this->assertNull(HttpBodyCollector::collect($options, HttpMessageType::incomingRequest(), str_repeat('é', 501), 'text/plain'));
    }

    public function testParsedBodiesWithoutKnownLengthAreMeasured(): void
    {
        $policy = $this->options(['max_request_body_size' => 'small']);

        $this->assertNull(HttpBodyCollector::collect($policy, HttpMessageType::incomingRequest(), ['name' => str_repeat('a', 1000)]));
        $this->assertSame(
            ['name' => 'Alice', 'password' => '[Filtered]'],
            HttpBodyCollector::collect($policy, HttpMessageType::incomingRequest(), ['name' => 'Alice', 'password' => 'secret'])
        );
    }

    public function testKnownLengthOfParsedBodiesIsTrusted(): void
    {
        $body = ['name' => str_repeat('a', 100001)];

        $this->assertSame($body, HttpBodyCollector::collect($this->options(['max_request_body_size' => 'small']), HttpMessageType::incomingRequest(), $body, '', 100));
    }

    public function testParsedServerBodiesWithoutContentLengthAreMeasured(): void
    {
        $policy = $this->options(['max_request_body_size' => 'small']);
        $request = new ServerRequest('POST', '/', ['Transfer-Encoding' => 'chunked']);

        $this->assertNull(HttpBodyCollector::collectServerRequest($policy, $request->withParsedBody(['name' => str_repeat('a', 1000)])));
        $this->assertSame(['name' => 'Alice'], HttpBodyCollector::collectServerRequest($policy, $request->withParsedBody(['name' => 'Alice'])));
    }

    public function testParsedBodiesWithInvalidUtf8AreMeasuredAndCollected(): void
    {
        $body = ['name' => "Alice \xB1\x31"];

        $this->assertSame($body, HttpBodyCollector::collect($this->options(['max_request_body_size' => 'small']), HttpMessageType::incomingRequest(), $body));
    }

    public function testMeasuringParsedBodiesDoesNotInvokeApplicationCode(): void
    {
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new \LogicException('Measuring the body must not serialize application objects.');
            }
        };

        $this->assertSame(
            ['name' => 'Alice', 'object' => '[Filtered]', 'infinite' => '[Filtered]'],
            HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), ['name' => 'Alice', 'object' => $object, 'infinite' => \INF])
        );
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
        $this->assertSame($expected, HttpBodyCollector::collect($this->options(['max_request_body_size' => 'small']), HttpMessageType::incomingRequest(), $raw, 'application/json'));
    }

    public function testFilteringDoesNotInvokeCallbacks(): void
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
            $this->assertSame(array_fill_keys(array_keys($body), '[Filtered]'), HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), $body));
        } finally {
            fclose($resource);
        }
    }

    public function testDecodedAndParsedNonFiniteNumbersAreFilteredConsistently(): void
    {
        $expected = ['positive' => '[Filtered]', 'negative' => '[Filtered]'];

        $this->assertSame($expected, HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), '{"positive":1e400,"negative":-1e400}', 'application/json'));
        $this->assertSame($expected, HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), ['positive' => \INF, 'negative' => -\INF]));
    }

    public function testDeeplyNestedJsonBodiesRemainSerializableInAnEnvelope(): void
    {
        // This body can be decoded, but without filtering it exceeds the JSON
        // depth limit once wrapped in an event envelope.
        $body = str_repeat('{"child":', 510) . '{"name":"Alice","password":"secret"}' . str_repeat('}', 510);
        $expected = ['child' => '[Filtered]'];
        for ($depth = 0; $depth < 5; ++$depth) {
            $expected = ['child' => $expected];
        }

        $result = HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), $body, 'application/json');
        $this->assertSame($expected, $result);
        $event = Event::createEvent();
        $event->setRequest(['data' => $result]);

        $envelope = (new PayloadSerializer(new Options()))->serialize($event);
        $payload = JSON::decode(explode("\n", $envelope)[2]);

        $this->assertSame($expected, $payload['request']['data']);
    }

    public function testRecursiveParsedBodiesRemainSerializableInAnEnvelope(): void
    {
        $body = ['name' => 'Alice', 'password' => 'secret'];
        $body['child'] = &$body;

        $result = HttpBodyCollector::collect($this->options(), HttpMessageType::incomingRequest(), $body);
        $this->assertSame('Alice', $result['name']);
        $this->assertSame('[Filtered]', $result['password']);
        $event = Event::createEvent();
        $event->setRequest(['data' => $result]);

        $envelope = (new PayloadSerializer(new Options()))->serialize($event);
        $payload = JSON::decode(explode("\n", $envelope)[2]);

        $this->assertSame($result, $payload['request']['data']);
        $this->assertSame('secret', $body['child']['password']);
    }

    public function testHeaderAndCookieTermsDoNotAffectBodies(): void
    {
        $options = $this->options(['data_collection' => ['http_headers' => ['terms' => ['name']], 'cookies' => ['terms' => ['name']]]]);

        $this->assertSame(['name' => 'Alice'], HttpBodyCollector::collect($options, HttpMessageType::incomingRequest(), ['name' => 'Alice']));
    }

    public function testServerStreamPositionIsRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"Alice","token":"secret"}');
        $request->getBody()->seek(7);

        $this->assertSame(['name' => 'Alice', 'token' => '[Filtered]'], HttpBodyCollector::collectServerRequest($this->options(), $request));
        $this->assertSame(7, $request->getBody()->tell());
    }

    public function testParsedBodyWithZeroContentLengthIsCollected(): void
    {
        $request = (new ServerRequest('POST', '/', ['Content-Length' => '0']))
            ->withParsedBody(['name' => 'Alice']);

        $this->assertSame(['name' => 'Alice'], HttpBodyCollector::collectServerRequest($this->options(), $request));
    }

    /**
     * @dataProvider parsedServerBodyProvider
     *
     * @param array<array-key, mixed>|object $body
     * @param array<array-key, mixed>|null   $expected
     */
    public function testParsedServerBodyIsAuthoritative($body, ?array $expected): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('');
        $request->expects($this->once())->method('getParsedBody')->willReturn($body);
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->assertSame($expected, HttpBodyCollector::collectServerRequest($this->options(), $request));
    }

    public function parsedServerBodyProvider(): \Generator
    {
        yield 'empty array' => [[], []];

        $json = '{"name":"Alice","profile":{"password":"secret","enabled":true},"items":[{"token":"secret","count":2}]}';
        $expected = [
            'name' => 'Alice',
            'profile' => ['password' => '[Filtered]', 'enabled' => true],
            'items' => [['token' => '[Filtered]', 'count' => 2]],
        ];
        yield 'parsed array' => [json_decode($json, true), $expected];
        yield 'parsed object omitted' => [json_decode($json), null];
    }

    public function testUploadedFilesAreNotAccessedOrAddedToParsedFields(): void
    {
        $file = $this->createMock(UploadedFileInterface::class);
        $file->expects($this->never())->method('getClientFilename');
        $file->expects($this->never())->method('getClientMediaType');
        $file->expects($this->never())->method('getSize');
        $file->expects($this->never())->method('getStream');

        $body = [3 => 'parsed value', 'document' => ['name' => 'parsed document']];
        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody($body)
            ->withUploadedFiles([3 => $file, 'document' => ['uploaded' => $file], 'attachment' => $file]);

        $this->assertSame($body, HttpBodyCollector::collectServerRequest($this->options(), $request));
        $this->assertSame($body, $request->getParsedBody());
    }

    public function testKnownOversizedServerRequestIsNotRead(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('1001');
        $request->expects($this->never())->method('getParsedBody');
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(['max_request_body_size' => 'small']), $request));
    }

    public function testNonSeekableServerStreamIsNotCollected(): void
    {
        $request = new ServerRequest('POST', '/', [], 'secret');

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(), $request->withBody(new NoSeekStream($request->getBody()))));
    }

    public function testOversizedServerStreamIsOmittedAndPositionIsRestored(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json', 'Content-Length' => '1'], str_repeat('a', 1001));
        $request->getBody()->seek(3);

        $this->assertNull(HttpBodyCollector::collectServerRequest($this->options(['max_request_body_size' => 'small']), $request));
        $this->assertSame(3, $request->getBody()->tell());
    }

    public function testServerRequestsUseLegacyCollection(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['max_request_body_size' => 'always']));
        $body = ['name' => 'Alice', 'token' => 'secret'];
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('100');
        $request->expects($this->once())->method('getParsedBody')->willReturn($body);
        $request->expects($this->once())->method('getUploadedFiles')->willReturn([]);
        $request->expects($this->never())->method('getBody');

        $this->assertSame($body, HttpBodyCollector::collectServerRequest($policy, $request));
    }

    /**
     * @dataProvider disabledServerRequestCollectionProvider
     *
     * @param array<string, mixed>|null $options
     */
    public function testDisabledServerRequestCollectionDoesNotAccessRequest(?array $options): void
    {
        $policy = DataCollectionPolicy::fromOptions($options === null ? null : new Options($options));
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->never())->method('getHeaderLine');
        $request->expects($this->never())->method('getParsedBody');
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->assertNull(HttpBodyCollector::collectServerRequest($policy, $request));
    }

    public function disabledServerRequestCollectionProvider(): \Generator
    {
        yield 'no client options' => [null];
        yield 'legacy none' => [['max_request_body_size' => 'none']];
        yield 'legacy never' => [['max_request_body_size' => 'never']];
        yield 'configured bodies disabled' => [['data_collection' => ['http_bodies' => []]]];
    }

    public function testPsr7BodyIsCollectedWithoutChangingTheStreamPosition(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"name":"Alice","token":"secret"}'
        );
        $response->getBody()->seek(4);

        $this->assertSame(['name' => 'Alice', 'token' => '[Filtered]'], HttpBodyCollector::collectPsr7Message(
            $this->options(),
            HttpMessageType::incomingResponse(),
            $response
        ));
        $this->assertSame(4, $response->getBody()->tell());
    }

    /**
     * @dataProvider disabledSourceProvider
     *
     * @param array<string, mixed>|null $options
     */
    public function testDisabledPsr7MessagesAreNotAccessed(?array $options, HttpMessageType $messageType): void
    {
        $policy = DataCollectionPolicy::fromOptions($options === null ? null : new Options($options));
        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->never())->method('getHeaderLine');
        $message->expects($this->never())->method('getBody');

        $this->assertNull(HttpBodyCollector::collectPsr7Message($policy, $messageType, $message));
    }

    public function testDeclaredOversizedPsr7MessagesAreNotRead(): void
    {
        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->once())->method('getHeaderLine')->with('Content-Length')->willReturn('100001');
        $message->expects($this->never())->method('getBody');

        $this->assertNull(HttpBodyCollector::collectSource($this->options(), HttpMessageType::incomingResponse(), new Psr7MessageBodySource($message)));
    }

    public function testOversizedPsr7StreamsAreNotRead(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects($this->once())->method('getSize')->willReturn(100001);
        $stream->expects($this->never())->method('read');
        $stream->expects($this->never())->method('rewind');
        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->once())->method('getHeaderLine')->with('Content-Length')->willReturn('1');
        $message->expects($this->once())->method('getBody')->willReturn($stream);

        $this->assertNull(HttpBodyCollector::collectSource($this->options(), HttpMessageType::incomingResponse(), new Psr7MessageBodySource($message)));
    }

    public function testPsr7StreamsWithUnknownSizeUseTheConfiguredReadLimit(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $stream->method('tell')->willReturn(4);
        $stream->method('eof')->willReturn(false);
        $stream->expects($this->once())->method('read')->with(1001)->willReturn(str_repeat('a', 1001));
        $stream->expects($this->once())->method('seek')->with(4);
        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->once())->method('getHeaderLine')->with('Content-Length')->willReturn('');
        $message->expects($this->once())->method('getBody')->willReturn($stream);

        $this->assertNull(HttpBodyCollector::collectSource($this->options(['max_request_body_size' => 'small']), HttpMessageType::outgoingRequest(), new Psr7MessageBodySource($message)));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function options(array $options = []): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options($options + ['data_collection' => [], 'max_request_body_size' => 'always']));
    }

    /**
     * @return HttpMessageType[]
     */
    private static function bodyTypes(): array
    {
        return [
            HttpMessageType::incomingRequest(),
            HttpMessageType::outgoingRequest(),
            HttpMessageType::incomingResponse(),
            HttpMessageType::outgoingResponse(),
        ];
    }
}
