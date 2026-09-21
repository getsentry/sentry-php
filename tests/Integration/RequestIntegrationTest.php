<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\ClientInterface;
use Sentry\DataCollection\RequestDataCollector;
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
    public function testInvoke(array $options, ServerRequestInterface $request, array $expectedRequestContextData, ?UserDataBag $initialUser, ?UserDataBag $expectedUser, array $integrationOptions = []): void
    {
        $event = Event::createEvent();
        $event->setUser($initialUser);

        $this->setupIntegration($request, $options, $integrationOptions);

        withScope(function (Scope $scope) use ($event, $expectedRequestContextData, $expectedUser): void {
            $event = $scope->applyToEvent($event);

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
        yield 'explicit header restrictions remain active with data collection' => [
            ['data_collection' => [], 'send_default_pii' => true],
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

        yield 'explicit empty header restrictions disable legacy sanitization' => [
            [],
            (new ServerRequest('GET', 'https://example.com/'))
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('X-Forwarded-For', '203.0.113.7'),
            [
                'url' => 'https://example.com/',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['example.com'],
                    'Authorization' => ['Bearer secret'],
                    'X-Forwarded-For' => ['203.0.113.7'],
                ],
            ],
            null,
            null,
            ['pii_sanitize_headers' => []],
        ];

        yield 'explicit default header restrictions apply with data collection' => [
            ['data_collection' => []],
            (new ServerRequest('GET', 'https://example.com/'))
                ->withHeader('X-Forwarded-For', '203.0.113.7')
                ->withHeader('X-Real-IP', '203.0.113.7'),
            [
                'url' => 'https://example.com/',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['example.com'],
                    'X-Forwarded-For' => ['[Filtered]'],
                    'X-Real-IP' => ['[Filtered]'],
                ],
            ],
            null,
            null,
            ['pii_sanitize_headers' => RequestDataCollector::DEFAULT_PII_SANITIZE_HEADERS],
        ];

        foreach (['absent' => null, 'conflicting' => 'theme=raw', 'malformed' => 'malformed'] as $name => $cookieHeader) {
            $request = (new ServerRequest('GET', 'https://example.com/'))
                ->withCookieParams(['theme' => 'parsed', 'session_id' => 'secret']);
            if ($cookieHeader !== null) {
                $request = $request->withHeader('Cookie', $cookieHeader);
            }

            yield 'parsed cookies with ' . $name . ' header' => [
                ['data_collection' => ['http_headers' => ['mode' => 'off']]],
                $request,
                [
                    'url' => 'https://example.com/',
                    'method' => 'GET',
                    'cookies' => ['theme' => 'parsed', 'session_id' => '[Filtered]'],
                ],
                null,
                null,
            ];
        }

        yield [
            [
                'send_default_pii' => true,
            ],
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

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', 'http://www.example.com/foo'))
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

        yield [
            [
                'send_default_pii' => false,
            ],
            new ServerRequest('GET', 'http://www.example.com:1234/foo'),
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
            (new ServerRequest('GET', 'http://www.example.com/foo?foo=bar&bar=baz', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Proxy-Authorization', 'Basic dXNlcjpwYXNz')
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
                    'Proxy-Authorization' => ['Basic dXNlcjpwYXNz'],
                    'Cookie' => ['bar'],
                    'Set-Cookie' => ['baz'],
                ],
            ],
            null,
            UserDataBag::createFromUserIpAddress('127.0.0.1'),
        ];

        yield [
            [
                'send_default_pii' => true,
            ],
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

        yield [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', 'http://www.example.com/foo?foo=bar&bar=baz', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Proxy-Authorization', 'Basic dXNlcjpwYXNz')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Authorization' => ['[Filtered]'],
                    'Proxy-Authorization' => ['[Filtered]'],
                    'Cookie' => ['[Filtered]'],
                    'Set-Cookie' => ['[Filtered]'],
                ],
            ],
            null,
            null,
        ];

        yield [
            [
                'max_request_body_size' => 'none',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', '3')
                ->withBody(Utils::streamFor('foo')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['3'],
                ],
            ],
            null,
            null,
        ];

        yield 'legacy request bodies use the configured limit' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 3))
                ->withBody(Utils::streamFor(str_repeat('a', 1001))),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['1000'],
                ],
                'data' => str_repeat('a', 1000),
            ],
            null,
            null,
        ];

        yield [
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

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Utils::streamFor('{"foo":"bar"}')),
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
                'data_collection' => [],
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', 'http://user:password@www.example.com/foo?api%5Ftoken=secret&q=a%20b%26c', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withCookieParams([
                    'session_id' => 'secret',
                    'theme' => 'dark',
                ])
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('Cookie', 'session_id=secret; theme=dark')
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
                'url' => 'http://www.example.com/foo?api%5Ftoken=[Filtered]&q=a%20b%26c',
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

        yield [
            [],
            (new ServerRequest('GET', 'http://www.example.com/foo'))
                ->withHeader('123', 'test'),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com'],
                    '123' => ['test'],
                ],
            ],
            null,
            null,
        ];
    }

    /**
     * @dataProvider explicitRequestDataProvider
     */
    public function testExplicitRequestFieldsArePreserved(array $options, array $initialRequest, array $expectedRequest): void
    {
        $request = (new ServerRequest('POST', 'https://automatic.example/?token=automatic'))
            ->withHeader('Content-Length', '20')
            ->withHeader('Authorization', 'automatic')
            ->withCookieParams(['session_id' => 'automatic'])
            ->withParsedBody(['password' => 'automatic']);

        $event = Event::createEvent();
        $event->setRequest($initialRequest);

        $this->setupIntegration($request, $options);

        withScope(function (Scope $scope) use ($event, $expectedRequest): void {
            $event = $scope->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertSame($expectedRequest, $event->getRequest());
            $this->assertNull($event->getUser());
        });
    }

    public static function explicitRequestDataProvider(): iterable
    {
        $explicitRequest = [
            'url' => 'https://manual.example/?token=explicit',
            'query_string' => 'token=explicit',
            'headers' => ['Authorization' => ['explicit']],
            'cookies' => ['session_id' => 'explicit'],
            'data' => ['password' => 'explicit'],
            'env' => ['CUSTOM' => 'explicit'],
            'custom' => 'explicit',
        ];
        $expectedExplicitRequest = [
            'url' => 'https://manual.example/?token=explicit',
            'query_string' => 'token=explicit',
            'headers' => ['Authorization' => ['explicit']],
            'cookies' => ['session_id' => 'explicit'],
            'data' => ['password' => 'explicit'],
            'env' => ['CUSTOM' => 'explicit'],
            'custom' => 'explicit',
            'method' => 'POST',
        ];
        $emptyRequest = [
            'url' => '',
            'query_string' => null,
            'headers' => [],
            'cookies' => null,
            'data' => [],
            'env' => [],
        ];
        $expectedEmptyRequest = [
            'url' => '',
            'query_string' => null,
            'headers' => [],
            'cookies' => null,
            'data' => [],
            'env' => [],
            'method' => 'POST',
        ];

        yield 'legacy with explicit values' => [
            ['max_request_body_size' => 'always'],
            $explicitRequest,
            $expectedExplicitRequest,
        ];

        yield 'legacy with empty values' => [
            ['max_request_body_size' => 'always'],
            $emptyRequest,
            $expectedEmptyRequest,
        ];

        yield 'default data collection with explicit values' => [
            [
                'data_collection' => [],
                'max_request_body_size' => 'always',
            ],
            $explicitRequest,
            $expectedExplicitRequest,
        ];

        yield 'default data collection with empty values' => [
            [
                'data_collection' => [],
                'max_request_body_size' => 'always',
            ],
            $emptyRequest,
            $expectedEmptyRequest,
        ];

        yield 'disabled data collection with explicit values' => [
            [
                'data_collection' => [
                    'user_info' => false,
                    'http_headers' => ['mode' => 'off'],
                    'cookies' => ['mode' => 'off'],
                    'url_query_params' => ['mode' => 'off'],
                    'http_bodies' => [],
                ],
                'max_request_body_size' => 'always',
            ],
            $explicitRequest,
            $expectedExplicitRequest,
        ];

        yield 'disabled data collection with empty values' => [
            [
                'data_collection' => [
                    'user_info' => false,
                    'http_headers' => ['mode' => 'off'],
                    'cookies' => ['mode' => 'off'],
                    'url_query_params' => ['mode' => 'off'],
                    'http_bodies' => [],
                ],
                'max_request_body_size' => 'always',
            ],
            $emptyRequest,
            $expectedEmptyRequest,
        ];
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

        $this->setupIntegration($request, ['data_collection' => [], 'max_request_body_size' => 'always']);

        $event = Event::createEvent();
        $event->setRequest(['data' => null]);

        withScope(function (Scope $scope) use ($event): void {
            $event = $scope->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertArrayHasKey('data', $event->getRequest());
            $this->assertNull($event->getRequest()['data']);
        });
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

        SentrySdk::getCurrentHub()->bindClient($client);
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
