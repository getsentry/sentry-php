<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\RequestFetcherInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use function Sentry\withScope;

final class RequestIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $options, ServerRequestInterface $request, array $expectedRequestContextData, ?UserDataBag $initialUser, ?UserDataBag $expectedUser): void
    {
        $event = new Event();
        $event->setUser($initialUser);

        $integration = new RequestIntegration($this->createRequestFetcher($request));
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($integration);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options($options));

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($event, $expectedRequestContextData, $initialUser, $expectedUser): void {
            $event = $scope->applyToEvent($event, []);

            $this->assertNotNull($event);
            $this->assertSame($expectedRequestContextData, $event->getRequest());

            $user = $event->getUser();

            if (null !== $expectedUser) {
                $this->assertNotNull($user);
                $this->assertEquals($expectedUser, $user);
            } else {
                $this->assertNull($user);
            }
        });
    }

    public function invokeDataProvider(): iterable
    {
        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo')))
                ->withCookieParams(['foo' => 'bar']),
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
            UserDataBag::createFromUserIdentifier('unique_id'),
            UserDataBag::createFromUserIdentifier('unique_id'),
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo')))
                ->withCookieParams(['foo' => 'bar']),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com:1234/foo'))),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com:1234/foo'))),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo?foo=bar&bar=baz'), [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'env' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Authorization' => ['foo'],
                    'Cookie' => ['bar'],
                    'Set-Cookie' => ['baz'],
                ],
            ],
            null,
            UserDataBag::createFromUserIpAddress('127.0.0.1'),
        ];

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo?foo=bar&bar=baz'), ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'none',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 3 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 4 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                ]),
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => [
                        new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                        new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                    ],
                ]),
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => [
                        'bar' => [
                            new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                            new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                        ],
                    ],
                ]),
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
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
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->getStreamMock(null)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                ],
            ],
            null,
            null,
        ];
    }

    private function getStreamMock(?int $size, string $content = ''): StreamInterface
    {
        /** @var MockObject&StreamInterface $stream */
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->any())
            ->method('getSize')
            ->willReturn($size);

        $stream->expects(null === $size ? $this->never() : $this->any())
            ->method('getContents')
            ->willReturn($content);

        return $stream;
    }

    private function createRequestFetcher(ServerRequestInterface $request): RequestFetcherInterface
    {
        return new class($request) implements RequestFetcherInterface {
            /**
             * @var ServerRequestInterface
             */
            private $request;

            public function __construct(ServerRequestInterface $request)
            {
                $this->request = $request;
            }

            public function fetchRequest(): ServerRequestInterface
            {
                return $this->request;
            }
        };
    }
}
