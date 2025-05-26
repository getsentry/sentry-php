<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\HttpClient;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Util\Http;

class HttpClientTest extends TestCase
{
    use TestServer;

    public function testClientMakesRequestWithCorrectHeadersMethodAndPath(): void
    {
        $testServer = $this->startTestServer();

        $options = new Options([
            'dsn' => "http://publicKey@{$testServer}/200",
        ]);

        $request = new Request();
        $request->setStringBody('test');

        $client = new HttpClient($sdkIdentifier = 'sentry.php', $sdkVersion = 'testing');
        $response = $client->sendRequest($request, $options);

        $serverOutput = $this->stopTestServer();

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->getStatusCode());

        // This assertion is here to test that the response headers are correctly parsed
        $this->assertEquals(200, (int) $response->getHeaderLine('x-sentry-test-server-status-code'));

        $this->assertTrue($serverOutput['compressed']);
        $this->assertEquals($response->getStatusCode(), $serverOutput['status']);
        $this->assertEquals($request->getStringBody(), $serverOutput['body']);
        $this->assertEquals('/api/200/envelope/', $serverOutput['server']['REQUEST_URI']);
        $this->assertEquals("{$sdkIdentifier}/{$sdkVersion}", $serverOutput['headers']['User-Agent']);

        $expectedHeaders = Http::getRequestHeaders($options->getDsn(), $sdkIdentifier, $sdkVersion);
        foreach ($expectedHeaders as $expectedHeader) {
            [$headerName, $headerValue] = explode(': ', $expectedHeader);
            $this->assertEquals($headerValue, $serverOutput['headers'][$headerName]);
        }
    }

    public function testClientMakesUncompressedRequestWhenCompressionDisabled(): void
    {
        $testServer = $this->startTestServer();

        $options = new Options([
            'dsn' => "http://publicKey@{$testServer}/200",
            'http_compression' => false,
        ]);

        $request = new Request();
        $request->setStringBody('test');

        $client = new HttpClient('sentry.php', 'testing');
        $response = $client->sendRequest($request, $options);

        $serverOutput = $this->stopTestServer();

        $this->assertTrue($response->isSuccess());
        $this->assertFalse($serverOutput['compressed']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->getStatusCode(), $serverOutput['status']);
        $this->assertEquals($request->getStringBody(), $serverOutput['body']);
        $this->assertEquals($response->getError(), '');
        $this->assertEquals(\strlen($request->getStringBody()), $serverOutput['headers']['Content-Length']);
    }

    public function testClientReturnsBodyAsErrorOnNonSuccessStatusCode(): void
    {
        $testServer = $this->startTestServer();

        $options = new Options([
            'dsn' => "http://publicKey@{$testServer}/400",
        ]);

        $request = new Request();
        $request->setStringBody('test');

        $client = new HttpClient('sentry.php', 'testing');
        $response = $client->sendRequest($request, $options);

        $this->stopTestServer();

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(400, $response->getStatusCode());

        $this->assertEquals($request->getStringBody(), $response->getError());
    }

    public function testThrowsExceptionIfDsnOptionIsNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DSN option must be set to use the HttpClient.');

        $options = new Options(['dsn' => null]);

        $client = new HttpClient('sentry.php', 'testing');
        $client->sendRequest(new Request(), $options);
    }

    public function testThrowsExceptionIfRequestDataIsEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The request data is empty.');

        $options = new Options(['dsn' => 'https://publicKey@example.com/1']);

        $client = new HttpClient('sentry.php', 'testing');
        $client->sendRequest(new Request(), $options);
    }
}
