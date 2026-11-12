<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\RequestFetcherInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\IsolationScope;
use Sentry\UserDataBag;

use function Sentry\withIsolationScope;

final class RequestIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $options, ServerRequestInterface $request, array $expectedRequestContextData, ?UserDataBag $initialUser, ?UserDataBag $expectedUser, array $integrationOptions = []): void
    {
        $event = Event::createEvent();
        $event->setUser($initialUser);

        $this->setupIntegration($request, $options, $integrationOptions);

        withIsolationScope(function (IsolationScope $scope) use ($event, $expectedRequestContextData, $expectedUser): void {
            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertSame($expectedRequestContextData, $event->getRequest());

            $user = $event->getUser();

            if ($expectedUser !== null) {
                $this->assertNotNull($user);
                $this->assertEquals($expectedUser, $user);
            } else {
                $this->assertNull($user);
            }
        });
    }

    public static function invokeDataProvider(): iterable
    {
        yield 'cookies and headers are collected and the user is kept' => [
            [],
            (new ServerRequest('GET', 'http://www.example.com/foo'))
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

        yield 'URL with a port' => [
            [],
            new ServerRequest('GET', 'http://www.example.com:1234/foo'),
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

        yield 'user IP address is collected' => [
            [],
            (new ServerRequest('GET', 'http://www.example.com/foo?foo=bar&bar=baz', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com'),
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
                ],
            ],
            null,
            UserDataBag::createFromUserIpAddress('127.0.0.1'),
        ];

        yield 'empty user IP address is not collected' => [
            [],
            (new ServerRequest('GET', 'http://www.example.com', [], null, '1.1', ['REMOTE_ADDR' => '']))
                ->withHeader('Host', 'www.example.com'),
            [
                'url' => 'http://www.example.com',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
            null,
            null,
        ];

        yield 'request body size never' => [
            [
                'max_request_body_size' => 'never',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', '3')
                ->withBody(Utils::streamFor('foo')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['3'],
                ],
            ],
            null,
            null,
        ];

        yield 'body that cannot be parsed is filtered' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', '3')
                ->withBody(Utils::streamFor('foo')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['3'],
                ],
                'data' => '[Filtered]',
            ],
            null,
            null,
        ];

        yield 'parsed body at the small size limit' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 3))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['1000'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
            null,
            null,
        ];

        yield 'parsed body over the small size limit' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 3 + 1))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['1001'],
                ],
            ],
            null,
            null,
        ];

        yield 'parsed body at the medium size limit' => [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 4))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['10000'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
            null,
            null,
        ];

        yield 'parsed body over the medium size limit' => [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 4 + 1))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['10001'],
                ],
            ],
            null,
            null,
        ];

        yield 'uploaded files are not collected' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', '444')
                ->withUploadedFiles([
                    'foo' => [
                        new UploadedFile('foo content', 123, \UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                        new UploadedFile('bar content', 321, \UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                    ],
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['444'],
                ],
            ],
            null,
            null,
        ];

        yield 'JSON body is decoded' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Length', '23')
                ->withBody(Utils::streamFor('{"1":"foo","bar":"baz"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                    'Content-Length' => ['23'],
                ],
                'data' => [
                    '1' => 'foo',
                    'bar' => 'baz',
                ],
            ],
            null,
            null,
        ];

        yield 'JSON body without a content length is collected' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Utils::streamFor('{"foo":"bar"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'cookies' => [],
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

        yield 'numeric header names' => [
            [],
            (new ServerRequest('GET', 'http://www.example.com/foo'))
                ->withHeader('123', 'test'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    '123' => ['test'],
                ],
            ],
            null,
            null,
        ];

        yield 'explicit header restrictions are applied' => [
            [],
            (new ServerRequest('GET', 'https://example.com/'))
                ->withHeader('X-Tenant-ID', 'tenant')
                ->withHeader('X-Forwarded-For', '203.0.113.7'),
            [
                'url' => 'https://example.com/',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['example.com'],
                    'X-Tenant-ID' => ['[Filtered]'],
                    'X-Forwarded-For' => ['203.0.113.7'],
                ],
            ],
            null,
            null,
            ['pii_sanitize_headers' => ['x-TeNaNt-Id']],
        ];

        yield 'data collection can disable all incoming request data' => [
            [
                'data_collection' => [
                    'user_info' => false,
                    'cookies' => ['mode' => 'off'],
                    'http_headers' => ['request' => ['mode' => 'off']],
                    'http_bodies' => [],
                    'url_query_params' => ['mode' => 'off'],
                ],
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo?token=secret', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withCookieParams(['session_id' => 'secret'])
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('Content-Length', '3')
                ->withBody(Utils::streamFor('foo')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
            ],
            UserDataBag::createFromUserIdentifier('explicit-user'),
            UserDataBag::createFromUserIdentifier('explicit-user'),
        ];

        yield 'data collection applies per-category filtering' => [
            [
                'data_collection' => [
                    'user_info' => false,
                    'cookies' => ['mode' => 'allowList', 'terms' => ['theme']],
                    'http_headers' => ['request' => ['mode' => 'allowList', 'terms' => ['x-request-id']]],
                    'http_bodies' => [],
                    'url_query_params' => ['mode' => 'denyList', 'terms' => ['page']],
                ],
            ],
            (new ServerRequest('GET', 'http://www.example.com/foo?token=secret&page=5'))
                ->withCookieParams([
                    'session_id' => 'secret',
                    'theme' => 'dark',
                ])
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('X-Request-Id', 'request-id'),
            [
                'url' => 'http://www.example.com/foo?token=[Filtered]&page=[Filtered]',
                'method' => 'GET',
                'query_string' => 'token=[Filtered]&page=[Filtered]',
                'cookies' => [
                    'session_id' => '[Filtered]',
                    'theme' => 'dark',
                ],
                'headers' => [
                    'Host' => ['[Filtered]'],
                    'Authorization' => ['[Filtered]'],
                    'X-Request-Id' => ['request-id'],
                ],
            ],
            null,
            null,
        ];

        yield 'data collection defaults filter sensitive request data' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://user:password@www.example.com/foo?api%5Ftoken=secret&q=a%20b%26c', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withCookieParams([
                    'session_id' => 'secret',
                    'theme' => 'dark',
                ])
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('Cookie', 'session_id=raw; theme=raw')
                ->withHeader('Set-Cookie', 'theme=light')
                ->withHeader('X-Forwarded-For', '203.0.113.7')
                ->withHeader('Content-Length', '100')
                ->withParsedBody([
                    'password' => 'secret',
                    'user' => [
                        'api_token' => 'secret',
                        'name' => 'alice',
                    ],
                ]),
            [
                'url' => 'http://[Filtered]:[Filtered]@www.example.com/foo?api%5Ftoken=[Filtered]&q=a%20b%26c',
                'method' => 'POST',
                'query_string' => 'api%5Ftoken=[Filtered]&q=a%20b%26c',
                'env' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                'cookies' => [
                    'session_id' => '[Filtered]',
                    'theme' => 'dark',
                ],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Authorization' => ['[Filtered]'],
                    'X-Forwarded-For' => ['203.0.113.7'],
                    'Content-Length' => ['100'],
                ],
                'data' => ['password' => '[Filtered]', 'user' => ['api_token' => '[Filtered]', 'name' => 'alice']],
            ],
            null,
            UserDataBag::createFromUserIpAddress('127.0.0.1'),
        ];
    }

    public function testExplicitRequestFieldsArePreserved(): void
    {
        $request = (new ServerRequest('POST', 'https://automatic.example/?token=automatic'))
            ->withHeader('Content-Length', '20')
            ->withHeader('Authorization', 'automatic')
            ->withCookieParams(['session_id' => 'automatic'])
            ->withParsedBody(['password' => 'automatic']);

        $initialRequest = [
            'url' => 'https://manual.example/?token=explicit',
            'query_string' => null,
            'headers' => ['Authorization' => ['explicit']],
            'cookies' => [],
            'data' => ['password' => 'explicit'],
            'env' => [],
            'custom' => 'explicit',
        ];
        $event = Event::createEvent();
        $event->setRequest($initialRequest);

        $this->setupIntegration($request, ['max_request_body_size' => 'always']);

        withIsolationScope(function (IsolationScope $scope) use ($event, $initialRequest): void {
            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertSame($initialRequest + ['method' => 'POST'], $event->getRequest());
        });
    }

    public function testExplicitNullBodySkipsAutomaticBodyCollection(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn(new Uri('https://example.com/'));
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('20');
        $request->expects($this->never())->method('getParsedBody');
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->setupIntegration($request, ['max_request_body_size' => 'always']);

        $event = Event::createEvent();
        $event->setRequest(['data' => null]);

        withIsolationScope(function (IsolationScope $scope) use ($event): void {
            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertArrayHasKey('data', $event->getRequest());
            $this->assertNull($event->getRequest()['data']);
        });
    }

    /**
     * @dataProvider nonErrorEventDataProvider
     */
    public function testBodyIsNotCollectedForNonErrorEvents(Event $event, array $options): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn(new Uri('https://example.com/'));
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaders')->willReturn(['Content-Length' => ['16']]);
        $request->method('getHeaderLine')->willReturn('16');
        $request->method('getCookieParams')->willReturn(['theme' => 'dark']);
        $request->method('getServerParams')->willReturn([]);
        $request->expects($this->never())->method('getParsedBody');
        $request->expects($this->never())->method('getUploadedFiles');
        $request->expects($this->never())->method('getBody');

        $this->setupIntegration($request, $options);

        withIsolationScope(function (IsolationScope $scope) use ($event): void {
            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertSame([
                'url' => 'https://example.com/',
                'method' => 'POST',
                'cookies' => ['theme' => 'dark'],
                'headers' => ['Content-Length' => ['16']],
            ], $event->getRequest());
        });
    }

    public static function nonErrorEventDataProvider(): iterable
    {
        yield 'transactions' => [
            Event::createTransaction(),
            ['max_request_body_size' => 'always'],
        ];
    }

    private function setupIntegration(ServerRequestInterface $request, array $options, array $integrationOptions = []): void
    {
        $integration = new RequestIntegration($this->createRequestFetcher($request), $integrationOptions);
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($integration);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options($options));

        SentrySdk::init($client);
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
