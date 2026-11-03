<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\LegacyRequestBodyCollector;
use Sentry\Options;
use Sentry\Util\JSON;

final class LegacyRequestBodyCollectorTest extends TestCase
{
    /**
     * @dataProvider rawBodiesProvider
     *
     * @param mixed $expected
     */
    public function testDecodingAndEmptyBodyRules(string $body, string $contentType, $expected): void
    {
        $request = new ServerRequest('POST', '/', [
            'Content-Length' => (string) \strlen($body),
            'Content-Type' => $contentType,
        ], $body);

        $this->assertSame($expected, LegacyRequestBodyCollector::collect($this->policy(), $request));
    }

    public function rawBodiesProvider(): \Generator
    {
        yield 'JSON is not filtered' => ['{"password":"secret"}', 'application/json', ['password' => 'secret']];
        yield 'malformed JSON falls back to raw' => ['{bad', 'application/json', '{bad'];
        yield 'JSON with parameters remains raw' => ['{"name":"Alice"}', 'application/json; charset=utf-8', '{"name":"Alice"}'];
        yield 'JSON suffix remains raw' => ['{"name":"Alice"}', 'application/problem+json', '{"name":"Alice"}'];
        yield 'JSON media type is case sensitive' => ['{"name":"Alice"}', 'Application/JSON', '{"name":"Alice"}'];
        yield 'form remains raw' => ['password=secret', 'application/x-www-form-urlencoded', 'password=secret'];
        yield 'scalar JSON string' => ['"secret"', 'application/json', 'secret'];
        yield 'scalar JSON number' => ['123', 'application/json', 123];
        yield 'JSON null omitted' => ['null', 'application/json', null];
        yield 'JSON false omitted' => ['false', 'application/json', null];
        yield 'JSON zero omitted' => ['0', 'application/json', null];
        yield 'JSON empty string omitted' => ['""', 'application/json', null];
        yield 'JSON empty array omitted' => ['[]', 'application/json', null];
        yield 'JSON empty object omitted' => ['{}', 'application/json', null];
        yield 'raw zero omitted' => ['0', 'text/plain', null];
        yield 'raw whitespace retained' => [' ', 'text/plain', ' '];
    }

    /**
     * @dataProvider parsedBodyFallbackProvider
     *
     * @param array<array-key, mixed>|object $parsedBody
     */
    public function testParsedBodiesCanFallBackToTheStream($parsedBody): void
    {
        $request = (new ServerRequest('POST', '/', [
            'Content-Length' => '21',
            'Content-Type' => 'application/json',
        ], '{"password":"secret"}'))->withParsedBody($parsedBody);

        $this->assertSame(['password' => 'secret'], LegacyRequestBodyCollector::collect($this->policy(), $request));
    }

    public function parsedBodyFallbackProvider(): \Generator
    {
        yield 'empty parsed array' => [[]];
        yield 'parsed object' => [(object) ['name' => 'Alice']];
    }

    /**
     * @dataProvider rejectedContentLengthProvider
     */
    public function testRequestsOutsideLengthBoundsAreNotRead(string $size, string $contentLength): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn($contentLength);
        $request->expects($this->never())->method('getParsedBody');
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->assertNull(LegacyRequestBodyCollector::collect($this->policy($size), $request));
    }

    public function rejectedContentLengthProvider(): \Generator
    {
        yield 'missing length' => ['always', ''];
        yield 'zero length' => ['always', '0'];
        yield 'above small limit' => ['small', '1001'];
        yield 'above medium limit' => ['medium', '10001'];
    }

    /**
     * @dataProvider underreportedContentLengthProvider
     *
     * @param array<string, string>|string $expected
     */
    public function testBodyReadUsesConfiguredLimit(string $size, string $body, string $contentType, $expected): void
    {
        $request = new ServerRequest('POST', '/', [
            'Content-Length' => '1',
            'Content-Type' => $contentType,
        ], $body);
        $request->getBody()->seek(3);

        $this->assertSame($expected, LegacyRequestBodyCollector::collect($this->policy($size), $request));
        $this->assertSame(3, $request->getBody()->tell());
    }

    public function underreportedContentLengthProvider(): \Generator
    {
        $body = ['x' => str_repeat('a', 992)];
        $json = JSON::encode($body);
        $oversizedJson = JSON::encode(['x' => str_repeat('a', 993)]);

        yield 'at limit' => ['small', $json, 'application/json', $body];
        yield 'truncated JSON falls back to raw' => ['small', $oversizedJson, 'application/json', substr($oversizedJson, 0, 1000)];
        yield 'decodes after truncation' => ['small', $json . 'invalid suffix', 'application/json', $body];
        yield 'small raw body is truncated' => ['small', str_repeat('a', 1002), 'text/plain', str_repeat('a', 1000)];
        yield 'medium raw body is truncated' => ['medium', str_repeat('a', 10002), 'text/plain', str_repeat('a', 10000)];
    }

    public function testAlwaysCollectsBodiesBeyondTheConfiguredLimit(): void
    {
        $body = ['x' => str_repeat('a', 99993)];
        $request = new ServerRequest('POST', '/', [
            'Content-Length' => '100001',
            'Content-Type' => 'application/json',
        ], JSON::encode($body));
        $request->getBody()->seek(3);

        $this->assertSame($body, LegacyRequestBodyCollector::collect($this->policy(), $request));
        $this->assertSame(3, $request->getBody()->tell());
    }

    /**
     * @dataProvider uploadedFileBodyProvider
     *
     * @param array<array-key, mixed>|object|null $body
     * @param array<array-key, mixed>             $expectedFields
     */
    public function testUploadedFileMetadataIsCollectedWithoutReadingStreams($body, array $expectedFields): void
    {
        $file = $this->createMock(UploadedFileInterface::class);
        $file->method('getClientFilename')->willReturn('photo.png');
        $file->method('getClientMediaType')->willReturn('image/png');
        $file->method('getSize')->willReturn(123);
        $file->expects($this->never())->method('getStream');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('444');
        $request->method('getParsedBody')->willReturn($body);
        $request->method('getUploadedFiles')->willReturn(['uploads' => [5 => $file], 'api_token' => $file]);
        $request->expects($this->never())->method('getBody');

        $this->assertSame([
            'uploads' => [5 => [
                'client_filename' => 'photo.png',
                'client_media_type' => 'image/png',
                'size' => 123,
            ]],
            'api_token' => [
                'client_filename' => 'photo.png',
                'client_media_type' => 'image/png',
                'size' => 123,
            ],
        ] + $expectedFields, LegacyRequestBodyCollector::collect($this->policy(), $request));
    }

    public function uploadedFileBodyProvider(): \Generator
    {
        yield 'files only' => [null, []];
        yield 'parsed array' => [['name' => 'Alice', 'password' => 'secret'], ['name' => 'Alice', 'password' => 'secret']];
        yield 'parsed object fields omitted' => [(object) ['name' => 'Alice', 'password' => 'secret'], []];
    }

    public function testParsedFieldsTakePrecedenceOverUploadedFileMetadata(): void
    {
        $file = new UploadedFile('file contents', 13, \UPLOAD_ERR_OK, 'document.txt', 'text/plain');
        $body = [3 => 'parsed value', 'document' => ['name' => 'parsed document']];
        $request = (new ServerRequest('POST', '/', ['Content-Length' => '444']))
            ->withParsedBody($body)
            ->withUploadedFiles([3 => $file, 'document' => ['uploaded' => $file]]);

        $this->assertSame($body, LegacyRequestBodyCollector::collect($this->policy(), $request));
    }

    private function policy(string $size = 'always'): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['max_request_body_size' => $size]));
    }
}
