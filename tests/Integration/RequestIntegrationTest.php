<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
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
        $event = Event::createEvent();
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
                'send_default_pii' => false,
                'integrations' => [
                    new RequestIntegration(null, ['pii_sanitize_headers' => ['aUthOrIzAtIoN']]),
                ],
            ],
            (new ServerRequest('GET', 'http://www.example.com', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Authorization', 'foo'),
            [
                'url' => 'http://www.example.com',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Authorization' => ['[Filtered]'],
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

        yield [
            [
                'max_request_body_size' => 'never',
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

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', 'http://www.example.com/foo'))
                ->withHeader('Content-Length', (string) (10 ** 3))
                ->withBody(Utils::streamFor('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus at placerat est. Donec maximus odio augue, vitae bibendum nisi euismod nec. Nunc vel velit ligula. Ut non ultricies magna, non condimentum turpis. Donec pellentesque id nunc at facilisis. Sed fermentum ultricies nunc, id posuere ex ullamcorper quis. Sed varius tincidunt nulla, id varius nulla interdum sit amet. Pellentesque molestie sapien at mi tristique consequat. Nullam id eleifend arcu. Vivamus sed placerat neque. Ut sapien magna, elementum in euismod pretium, rhoncus vitae augue. Nam ullamcorper dui et tortor semper, eu feugiat elit faucibus. Curabitur vel auctor odio. Phasellus vestibulum ullamcorper dictum. Suspendisse fringilla, ipsum bibendum venenatis vulputate, nunc orci facilisis leo, commodo finibus mi arcu in turpis. Mauris ut ultrices est. Nam quis purus ut nulla interdum ornare. Proin in tellus egestas, commodo magna porta, consequat justo. Vivamus in convallis odio. Pellentesque porttitor, urna non gravida.')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['1000'],
                ],
                'data' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus at placerat est. Donec maximus odio augue, vitae bibendum nisi euismod nec. Nunc vel velit ligula. Ut non ultricies magna, non condimentum turpis. Donec pellentesque id nunc at facilisis. Sed fermentum ultricies nunc, id posuere ex ullamcorper quis. Sed varius tincidunt nulla, id varius nulla interdum sit amet. Pellentesque molestie sapien at mi tristique consequat. Nullam id eleifend arcu. Vivamus sed placerat neque. Ut sapien magna, elementum in euismod pretium, rhoncus vitae augue. Nam ullamcorper dui et tortor semper, eu feugiat elit faucibus. Curabitur vel auctor odio. Phasellus vestibulum ullamcorper dictum. Suspendisse fringilla, ipsum bibendum venenatis vulputate, nunc orci facilisis leo, commodo finibus mi arcu in turpis. Mauris ut ultrices est. Nam quis purus ut nulla interdum ornare. Proin in tellus egestas, commodo magna porta, consequat justo. Vivamus in convallis odio. Pellentesque porttitor, urna non gravid',
            ],
            null,
            null,
        ];

        yield [
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

        yield [
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
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['1001'],
                ],
            ],
            null,
            null,
        ];

        yield [
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

        yield [
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
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['10001'],
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
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => ['444'],
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
                ->withHeader('Content-Length', '13')
                ->withBody(Utils::streamFor('{"foo":"bar"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                    'Content-Length' => ['13'],
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
                'url' => 'http://www.example.com/foo?token=%5BFiltered%5D&page=%5BFiltered%5D',
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
            (new ServerRequest('POST', 'http://www.example.com/foo?api%5Ftoken=secret&q=a%20b%26c', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withCookieParams([
                    'session_id' => 'secret',
                    'theme' => 'dark',
                ])
                ->withHeader('Authorization', 'Bearer secret')
                ->withHeader('Cookie', 'session_id=secret; theme=dark')
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
                'url' => 'http://www.example.com/foo?api%5Ftoken=%5BFiltered%5D&q=a%20b%26c',
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
                    'Cookie' => ['[Filtered]'],
                    'X-Forwarded-For' => ['203.0.113.7'],
                    'Content-Length' => ['100'],
                ],
                'data' => [
                    'password' => '[Filtered]',
                    'user' => [
                        'api_token' => '[Filtered]',
                        'name' => 'alice',
                    ],
                ],
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
