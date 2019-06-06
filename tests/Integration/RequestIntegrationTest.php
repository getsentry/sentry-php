<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Sentry\Event;
use Sentry\Integration\RequestIntegration;
use Sentry\Options;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\UploadedFile;
use Zend\Diactoros\Uri;

final class RequestIntegrationTest extends TestCase
{
    /**
     * @dataProvider applyToEventWithRequestHavingIpAddressDataProvider
     */
    public function testInvokeWithRequestHavingIpAddress(bool $shouldSendPii, array $expectedValue): void
    {
        $event = new Event();
        $event->getUserContext()->setData(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');

        $this->assertInstanceOf(ServerRequestInterface::class, $request);

        $integration = new RequestIntegration(new Options(['send_default_pii' => $shouldSendPii]));

        RequestIntegration::applyToEvent($integration, $event, $request);

        $this->assertEquals($expectedValue, $event->getUserContext()->toArray());
    }

    public function applyToEventWithRequestHavingIpAddressDataProvider(): array
    {
        return [
            [
                true,
                ['ip_address' => '127.0.0.1', 'foo' => 'bar'],
            ],
            [
                false,
                ['foo' => 'bar'],
            ],
        ];
    }

    /**
     * @dataProvider applyToEventDataProvider
     */
    public function testApplyToEvent(array $options, ServerRequestInterface $request, array $expectedResult): void
    {
        $event = new Event();
        $integration = new RequestIntegration(new Options($options));

        RequestIntegration::applyToEvent($integration, $event, $request);

        $this->assertEquals($expectedResult, $event->getRequest());
    }

    public function applyToEventDataProvider(): \Generator
    {
        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest())
                ->withCookieParams(['foo' => 'bar'])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('GET'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'cookies' => [
                    'foo' => 'bar',
                ],
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest())
                ->withCookieParams(['foo' => 'bar'])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('GET'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com:1234/foo'))
                ->withMethod('GET'),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com:1234/foo'))
                ->withMethod('GET'),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
        ];

        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com/foo?foo=bar&bar=baz'))
                ->withMethod('GET')
                ->withHeader('Host', 'www.example.com')
                ->withHeader('REMOTE_ADDR', '127.0.0.1')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'REMOTE_ADDR' => ['127.0.0.1'],
                    'Authorization' => ['foo'],
                    'Cookie' => ['bar'],
                    'Set-Cookie' => ['baz'],
                ],
                'env' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
            ],
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com/foo?foo=bar&bar=baz'))
                ->withMethod('GET')
                ->withHeader('Host', 'www.example.com')
                ->withHeader('REMOTE_ADDR', '127.0.0.1')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'none',
            ],
            (new ServerRequest())
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withBody($this->getStreamMock(1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest())
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withBody($this->getStreamMock(10 ** 3)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest())
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withBody($this->getStreamMock(10 ** 3 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest())
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withBody($this->getStreamMock(10 ** 4)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest())
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withBody($this->getStreamMock(10 ** 4 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest())
                ->withUploadedFiles([
                    'foo' => new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => [
                        'client_filename' => 'foo.ext',
                        'client_media_type' => 'application/text',
                        'size' => 123,
                    ],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest())
                ->withUploadedFiles([
                    'foo' => [
                        new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                        new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                    ],
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => [
                        [
                            'client_filename' => 'foo.ext',
                            'client_media_type' => 'application/text',
                            'size' => 123,
                        ],
                        [
                            'client_filename' => 'bar.ext',
                            'client_media_type' => 'application/octet-stream',
                            'size' => 321,
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest())
                ->withUploadedFiles([
                    'foo' => [
                        'bar' => [
                            new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                            new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                        ],
                    ],
                ])
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => [
                        'bar' => [
                            [
                                'client_filename' => 'foo.ext',
                                'client_media_type' => 'application/text',
                                'size' => 123,
                            ],
                            [
                                'client_filename' => 'bar.ext',
                                'client_media_type' => 'application/octet-stream',
                                'size' => 321,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->getStreamMock(13, '{"foo":"bar"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                ],
                'data' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest())
                ->withUri(new Uri('http://www.example.com/foo'))
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->getStreamMock(1, '{')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                ],
                'data' => '{',
            ],
        ];
    }

    private function getStreamMock(int $size, string $content = ''): StreamInterface
    {
        /** @var MockObject|StreamInterface $stream */
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->any())
            ->method('getSize')
            ->willReturn($size);

        $stream->expects($this->any())
            ->method('getContents')
            ->willReturn($content);

        return $stream;
    }
}
