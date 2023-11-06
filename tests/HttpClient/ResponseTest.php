<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\Response;

final class ResponseTest extends TestCase
{
    public function testResponseSuccess()
    {
        $response = new Response(
            200,
            [
                'Content-Type' => [
                    'application/json',
                ],
            ],
            ''
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->hasHeader('content-type'));
        $this->assertSame(['application/json'], $response->getHeader('content-type'));
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', $response->getError());
        $this->assertFalse($response->hasError());
    }

    public function testResponseFailure()
    {
        $response = new Response(
            500,
            [],
            'Something went wrong!'
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->isSuccess());
        $this->assertFalse($response->hasHeader('content-type'));
        $this->assertSame([], $response->getHeader('content-type'));
        $this->assertSame([], $response->getHeader('Content-Type'));
        $this->assertSame('', $response->getHeaderLine('content-type'));
        $this->assertSame('', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Something went wrong!', $response->getError());
        $this->assertTrue($response->hasError());
    }

    public function testResponseMultiValueHeader()
    {
        $response = new Response(
            200,
            [
                'X-Foo' => [
                    'one',
                    'two',
                    'three',
                ],
            ],
            ''
        );

        $this->assertSame(['one', 'two', 'three'], $response->getHeader('x-foo'));
        $this->assertSame('one,two,three', $response->getHeaderLine('x-foo'));
    }
}
