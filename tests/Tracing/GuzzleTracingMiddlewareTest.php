<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

final class GuzzleTracingMiddlewareTest extends TestCase
{
    public function testTraceCreatesBreadcrumbIfSpanIsNotSet(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(2))
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 0,
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(TransactionContext::make());

        $this->assertFalse($transaction->getSampled());

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($expectedPromiseResult): PromiseInterface {
            return new FulfilledPromise($expectedPromiseResult);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request('GET', 'https://www.example.com'), []);

        try {
            $promiseResult = $promise->wait();
        } catch (\Throwable $exception) {
            $promiseResult = $exception;
        }

        $this->assertSame($expectedPromiseResult, $promiseResult);

        $this->assertNull($transaction->getSpanRecorder());

        $hub->configureScope(function (Scope $scope): void {
            $event = Event::createEvent();

            $scope->applyToEvent($event);

            $this->assertCount(1, $event->getBreadcrumbs());
        });
    }

    public function testTraceCreatesBreadcrumbIfSpanIsRecorded(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(2))
               ->method('getOptions')
               ->willReturn(new Options([
                   'traces_sample_rate' => 1,
               ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(TransactionContext::make());

        $this->assertTrue($transaction->getSampled());

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($expectedPromiseResult): PromiseInterface {
            return new FulfilledPromise($expectedPromiseResult);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request('GET', 'https://www.example.com'), []);

        try {
            $promiseResult = $promise->wait();
        } catch (\Throwable $exception) {
            $promiseResult = $exception;
        }

        $this->assertSame($expectedPromiseResult, $promiseResult);

        $this->assertNotNull($transaction->getSpanRecorder());
        $this->assertCount(1, $transaction->getSpanRecorder()->getSpans());

        $hub->configureScope(function (Scope $scope): void {
            $event = Event::createEvent();

            $scope->applyToEvent($event);

            $this->assertCount(1, $event->getBreadcrumbs());
        });
    }

    /**
     * @dataProvider traceHeadersDataProvider
     */
    public function testTraceHeaders(Request $request, Options $options, bool $headersShouldBePresent): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn($options);

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($expectedPromiseResult, $headersShouldBePresent): PromiseInterface {
            if ($headersShouldBePresent) {
                $this->assertNotEmpty($request->getHeader('sentry-trace'));
                $this->assertNotEmpty($request->getHeader('baggage'));
            } else {
                $this->assertEmpty($request->getHeader('sentry-trace'));
                $this->assertEmpty($request->getHeader('baggage'));
            }

            return new FulfilledPromise($expectedPromiseResult);
        });

        /** @var PromiseInterface $promise */
        $function($request, []);
    }

    /**
     * @dataProvider traceHeadersDataProvider
     */
    public function testTraceHeadersWithTransaction(Request $request, Options $options, bool $headersShouldBePresent): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(2))
            ->method('getOptions')
            ->willReturn($options);

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());

        $hub->setSpan($transaction);

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($expectedPromiseResult, $headersShouldBePresent): PromiseInterface {
            if ($headersShouldBePresent) {
                $this->assertNotEmpty($request->getHeader('sentry-trace'));
                $this->assertNotEmpty($request->getHeader('baggage'));
            } else {
                $this->assertEmpty($request->getHeader('sentry-trace'));
                $this->assertEmpty($request->getHeader('baggage'));
            }

            return new FulfilledPromise($expectedPromiseResult);
        });

        /** @var PromiseInterface $promise */
        $function($request, []);

        $transaction->finish();
    }

    public function testTraceHeadersAreNotAddedWhenExternalPropagationContextIsActive(): void
    {
        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'trace_propagation_targets' => null,
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);
        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($expectedPromiseResult): PromiseInterface {
            $this->assertEmpty($request->getHeader('sentry-trace'));
            $this->assertEmpty($request->getHeader('baggage'));

            return new FulfilledPromise($expectedPromiseResult);
        });

        $function(new Request('GET', 'https://www.example.com'), []);

        Scope::clearExternalPropagationContext();
    }

    public static function traceHeadersDataProvider(): iterable
    {
        // Test cases here are duplicated with sampling enabled and disabled because trace headers hould be added regardless of the sample decision

        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 0,
            ]),
            true,
        ];
        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 1,
            ]),
            true,
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 0,
                'trace_propagation_targets' => null,
            ]),
            true,
        ];
        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => null,
            ]),
            true,
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 0,
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]),
            true,
        ];
        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]),
            true,
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 0,
                'trace_propagation_targets' => [],
            ]),
            false,
        ];
        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [],
            ]),
            false,
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 0,
                'trace_propagation_targets' => [
                    'example.com',
                ],
            ]),
            false,
        ];
        yield [
            new Request('GET', 'https://www.example.com'),
            new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [
                    'example.com',
                ],
            ]),
            false,
        ];
    }

    /**
     * @dataProvider traceDataProvider
     */
    public function testTrace(Request $request, $expectedPromiseResult, array $expectedBreadcrumbData, array $expectedSpanData): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(4))
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($hub, $request, $expectedPromiseResult, $expectedBreadcrumbData, $expectedSpanData): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());

                $hub->configureScope(static function (Scope $scope) use ($eventArg): void {
                    $scope->applyToEvent($eventArg);
                });

                $spans = $eventArg->getSpans();
                $breadcrumbs = $eventArg->getBreadcrumbs();

                $this->assertCount(1, $spans);
                $this->assertCount(1, $breadcrumbs);

                $guzzleSpan = $spans[0];
                $guzzleBreadcrumb = $breadcrumbs[0];

                $partialUri = Uri::fromParts([
                    'scheme' => $request->getUri()->getScheme(),
                    'host' => $request->getUri()->getHost(),
                    'port' => $request->getUri()->getPort(),
                    'path' => $request->getUri()->getPath(),
                ]);

                $this->assertSame('http.client', $guzzleSpan->getOp());
                $this->assertSame("{$request->getMethod()} {$partialUri}", $guzzleSpan->getDescription());

                if ($expectedPromiseResult instanceof Response) {
                    $this->assertSame(SpanStatus::createFromHttpStatusCode($expectedPromiseResult->getStatusCode()), $guzzleSpan->getStatus());
                } else {
                    $this->assertSame(SpanStatus::internalError(), $guzzleSpan->getStatus());
                }

                $this->assertSame($expectedSpanData, $guzzleSpan->getData());
                $this->assertSame($expectedBreadcrumbData, $guzzleBreadcrumb->getMetadata());

                return true;
            }));

        $transaction = $hub->startTransaction(new TransactionContext());

        $hub->setSpan($transaction);

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($expectedPromiseResult): PromiseInterface {
            $this->assertNotEmpty($request->getHeader('sentry-trace'));
            $this->assertNotEmpty($request->getHeader('baggage'));

            if ($expectedPromiseResult instanceof \Throwable) {
                return new RejectedPromise($expectedPromiseResult);
            }

            return new FulfilledPromise($expectedPromiseResult);
        });

        /** @var PromiseInterface $promise */
        $promise = $function($request, []);

        try {
            $promiseResult = $promise->wait();
        } catch (\Throwable $exception) {
            $promiseResult = $exception;
        }

        $this->assertSame($expectedPromiseResult, $promiseResult);

        $transaction->finish();
    }

    /**
     * @dataProvider traceQueryStringDataProvider
     *
     * @param array<string, mixed> $options
     */
    public function testTraceFiltersQueryString(array $options, ?string $expectedQueryString): void
    {
        $rawQueryString = 'search=hello%20world&password=s%2Becret&custom=value';
        $sdkOptions = new Options(array_merge([
            'traces_sample_rate' => 1,
        ], $options));
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn($sdkOptions);

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($rawQueryString): PromiseInterface {
            $this->assertSame($rawQueryString, $request->getUri()->getQuery());

            return new FulfilledPromise(new Response());
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request('GET', 'https://www.example.com?' . $rawQueryString), []);
        $promise->wait();

        $spanData = $this->getHttpSpan($transaction)->getData();
        $breadcrumbData = $this->getBreadcrumbData($hub);

        if ($expectedQueryString === null) {
            $this->assertArrayNotHasKey('http.query', $spanData);
            $this->assertArrayNotHasKey('http.query', $breadcrumbData);
        } else {
            $this->assertSame($expectedQueryString, $spanData['http.query']);
            $this->assertSame($expectedQueryString, $breadcrumbData['http.query']);
        }
    }

    public function testTraceCollectsConfiguredOutgoingHttpData(): void
    {
        $sdkOptions = new Options([
            'traces_sample_rate' => 1,
            'data_collection' => [],
        ]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn($sdkOptions);

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $response = new Response(200, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Response-Id' => 'response-123',
            'Set-Cookie' => [
                'session_id=response-secret; Path=/; HttpOnly',
                'theme=light; Path=/',
            ],
        ], 'token=response-secret&status=ok');
        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($response): PromiseInterface {
            $this->assertSame(0, $request->getBody()->tell());

            return new FulfilledPromise($response);
        });
        $request = new Request(
            'POST',
            'https://www.example.com/path?search=hello%20world&password=request-secret#fragment',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer request-secret',
                'Cookie' => 'session_id=request-secret; theme=dark',
            ],
            '[{"password":"request-secret","name":"Alice"},"unkeyed-request-secret"]'
        );

        /** @var PromiseInterface $promise */
        $promise = $function($request, []);
        $promise->wait();

        $this->assertSame(0, $request->getBody()->tell());
        $this->assertSame(0, $response->getBody()->tell());

        $expectedSharedData = [
            'url.full' => 'https://www.example.com/path?search=hello%20world&password=%5BFiltered%5D#fragment',
            'http.query' => 'search=hello%20world&password=[Filtered]',
        ];
        $expectedSpanData = [
            'http.request.header.content-type' => ['application/json'],
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.cookie' => ['[Filtered]'],
            'http.request.body.data' => [
                [
                    'password' => '[Filtered]',
                    'name' => 'Alice',
                ],
                '[Filtered]',
            ],
            'http.response.header.content-type' => ['application/x-www-form-urlencoded'],
            'http.response.header.x-response-id' => ['response-123'],
            'http.response.header.set-cookie' => ['[Filtered]', '[Filtered]'],
            'http.response.body.data' => [
                'token' => '[Filtered]',
                'status' => 'ok',
            ],
        ];
        $spanData = $this->getHttpSpan($transaction)->getData();
        $breadcrumbData = $this->getBreadcrumbData($hub);

        foreach ($expectedSharedData as $key => $value) {
            $this->assertSame($value, $spanData[$key]);
            $this->assertSame($value, $breadcrumbData[$key]);
        }
        foreach ($expectedSpanData as $key => $value) {
            $this->assertSame($value, $spanData[$key]);
            $this->assertArrayNotHasKey($key, $breadcrumbData);
        }
        $this->assertSame($expectedSharedData['url.full'], $breadcrumbData['url']);
        $this->assertStringNotContainsString('request-secret', json_encode($spanData));
        $this->assertStringNotContainsString('response-secret', json_encode($spanData));
    }

    public function testTraceDoesNotConsumeNonSeekableBodies(): void
    {
        $sdkOptions = new Options([
            'traces_sample_rate' => 1,
            'data_collection' => [],
        ]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn($sdkOptions);

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $requestBody = new NoSeekStream(Utils::streamFor('{"request":"body"}'));
        $responseBody = new NoSeekStream(Utils::streamFor('{"response":"body"}'));
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(function (Request $request) use ($response): PromiseInterface {
            $this->assertSame('{"request":"body"}', $request->getBody()->getContents());

            return new FulfilledPromise($response);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request(
            'POST',
            'https://www.example.com',
            ['Content-Type' => 'application/json'],
            $requestBody
        ), []);
        $promiseResult = $promise->wait();

        $this->assertSame($response, $promiseResult);
        $this->assertSame('{"response":"body"}', $promiseResult->getBody()->getContents());

        $spanData = $this->getHttpSpan($transaction)->getData();
        $this->assertArrayNotHasKey('http.request.body.data', $spanData);
        $this->assertArrayNotHasKey('http.response.body.data', $spanData);
    }

    public function testTraceSkipsBodiesLargerThanTheirLimits(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'data_collection' => [],
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $oversizedRequestBody = str_repeat('a', 10001);
        $oversizedResponseBody = str_repeat('a', 100001);
        $response = new Response(200, ['Content-Type' => 'application/json'], $oversizedResponseBody);
        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($response): PromiseInterface {
            return new FulfilledPromise($response);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request(
            'POST',
            'https://www.example.com',
            ['Content-Type' => 'application/json'],
            $oversizedRequestBody
        ), []);
        $promise->wait();

        $spanData = $this->getHttpSpan($transaction)->getData();
        $this->assertArrayNotHasKey('http.request.body.data', $spanData);
        $this->assertArrayNotHasKey('http.response.body.data', $spanData);
    }

    /**
     * @dataProvider httpBodySafetyLimitDataProvider
     */
    public function testTraceAppliesHttpBodySafetyLimit(int $bodySize, bool $shouldCollect): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'max_request_body_size' => 'always',
                'data_collection' => [],
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $rawBody = str_repeat('a', $bodySize);
        $requestBody = FnStream::decorate(Utils::streamFor($rawBody), [
            'getSize' => static function (): ?int {
                return null;
            },
        ]);
        $responseBody = FnStream::decorate(Utils::streamFor($rawBody), [
            'getSize' => static function (): ?int {
                return null;
            },
        ]);
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($response): PromiseInterface {
            return new FulfilledPromise($response);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request(
            'POST',
            'https://www.example.com',
            ['Content-Type' => 'application/json'],
            $requestBody
        ), []);
        $promise->wait();

        $this->assertSame(0, $requestBody->tell());
        $this->assertSame(0, $responseBody->tell());

        $spanData = $this->getHttpSpan($transaction)->getData();
        if ($shouldCollect) {
            $this->assertSame('[Filtered]', $spanData['http.request.body.data']);
            $this->assertSame('[Filtered]', $spanData['http.response.body.data']);
        } else {
            $this->assertArrayNotHasKey('http.request.body.data', $spanData);
            $this->assertArrayNotHasKey('http.response.body.data', $spanData);
        }
    }

    public static function httpBodySafetyLimitDataProvider(): iterable
    {
        yield 'at 100 KB safety limit' => [100000, true];
        yield 'over 100 KB safety limit' => [100001, false];
    }

    public function testTraceRespectsDisabledOutgoingHttpDataCollection(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'data_collection' => [
                    'cookies' => ['mode' => 'off'],
                    'http_headers' => [
                        'request' => ['mode' => 'off'],
                        'response' => ['mode' => 'off'],
                    ],
                    'http_bodies' => [],
                    'url_query_params' => ['mode' => 'off'],
                ],
            ]));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);

        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'session_id=response-secret',
        ], '{"token":"response-secret"}');
        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($response): PromiseInterface {
            return new FulfilledPromise($response);
        });

        /** @var PromiseInterface $promise */
        $promise = $function(new Request(
            'POST',
            'https://www.example.com?password=request-secret',
            [
                'Content-Type' => 'application/json',
                'Cookie' => 'session_id=request-secret',
            ],
            '{"password":"request-secret"}'
        ), []);
        $promise->wait();

        $spanData = $this->getHttpSpan($transaction)->getData();
        $breadcrumbData = $this->getBreadcrumbData($hub);

        foreach ([
            'http.query',
            'http.request.header.content-type',
            'http.request.header.cookie',
            'http.request.body.data',
            'http.response.header.content-type',
            'http.response.header.set-cookie',
            'http.response.body.data',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $spanData);
            $this->assertArrayNotHasKey($key, $breadcrumbData);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getBreadcrumbData(Hub $hub): array
    {
        $event = Event::createEvent();
        $hub->configureScope(static function (Scope $scope) use ($event): void {
            $scope->applyToEvent($event);
        });
        $this->assertCount(1, $event->getBreadcrumbs());

        return $event->getBreadcrumbs()[0]->getMetadata();
    }

    private function getHttpSpan(Transaction $transaction): Span
    {
        $this->assertNotNull($transaction->getSpanRecorder());
        $httpSpans = array_values(array_filter(
            $transaction->getSpanRecorder()->getSpans(),
            static function (Span $span): bool {
                return $span->getOp() === 'http.client';
            }
        ));
        $this->assertCount(1, $httpSpans);

        return $httpSpans[0];
    }

    public static function traceQueryStringDataProvider(): iterable
    {
        yield 'legacy behavior is unchanged' => [
            [],
            'search=hello%20world&password=s%2Becret&custom=value',
        ];

        yield 'default data collection filters mandatory sensitive values' => [
            ['data_collection' => []],
            'search=hello%20world&password=[Filtered]&custom=value',
        ];

        yield 'collection can be disabled' => [
            [
                'data_collection' => [
                    'url_query_params' => [
                        'mode' => 'off',
                    ],
                ],
            ],
            null,
        ];

        yield 'allow list filters values not matching configured terms' => [
            [
                'data_collection' => [
                    'url_query_params' => [
                        'mode' => 'allowList',
                        'terms' => ['custom'],
                    ],
                ],
            ],
            'search=[Filtered]&password=[Filtered]&custom=value',
        ];

        yield 'deny list combines mandatory and custom terms' => [
            [
                'data_collection' => [
                    'url_query_params' => [
                        'mode' => 'denyList',
                        'terms' => ['custom'],
                    ],
                ],
            ],
            'search=hello%20world&password=[Filtered]&custom=[Filtered]',
        ];
    }

    public static function traceDataProvider(): iterable
    {
        yield [
            new Request('GET', 'https://www.example.com'),
            new Response(),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
        ];

        yield [
            new Request('GET', 'https://user:password@www.example.com?query=string#fragment=1'),
            new Response(),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.query' => 'query=string',
                'http.fragment' => 'fragment=1',
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.query' => 'query=string',
                'http.fragment' => 'fragment=1',
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
        ];

        yield [
            new Request('POST', 'https://www.example.com', [], 'not-sentry'),
            new Response(403, [], 'sentry'),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'POST',
                'http.request.body.size' => 10,
                'http.response.body.size' => 6,
                'http.response.status_code' => 403,
            ],
            [
                'http.request.method' => 'POST',
                'http.request.body.size' => 10,
                'http.response.body.size' => 6,
                'http.response.status_code' => 403,
            ],
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new \Exception(),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
            ],
        ];
    }
}
