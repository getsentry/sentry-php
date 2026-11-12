<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\GlobalScope;
use Sentry\State\IsolationScope;
use Sentry\State\Scope;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSampler;

final class GuzzleTracingMiddlewareTest extends TestCase
{
    public function testTraceCreatesBreadcrumbIfSpanIsNotSet(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 0,
            ]));

        SentrySdk::init($client);

        $transaction = \Sentry\startTransaction(TransactionContext::make());

        $this->assertFalse($transaction->getSampled());

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace();
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

        $event = Event::createEvent();

        (new GlobalScope())->merge(SentrySdk::getIsolationScope())->applyToEvent($event);

        $this->assertCount(1, $event->getBreadcrumbs());
    }

    public function testTraceCreatesBreadcrumbIfSpanIsRecorded(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
            ]));

        SentrySdk::init($client);

        $transaction = \Sentry\startTransaction(TransactionContext::make());

        $this->assertTrue($transaction->getSampled());

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace();
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

        $event = Event::createEvent();

        (new GlobalScope())->merge(SentrySdk::getIsolationScope())->applyToEvent($event);

        $this->assertCount(1, $event->getBreadcrumbs());
    }

    public function testTraceUsesProvidedIsolationScope(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]));

        SentrySdk::init($client);

        $currentScope = SentrySdk::getIsolationScope();

        $providedScope = new IsolationScope();
        $providedScope->setClient($client);

        $request = new Request('GET', 'https://www.example.com');
        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace($providedScope);
        $function = $middleware(function (Request $request) use ($expectedPromiseResult): PromiseInterface {
            $this->assertNotEmpty($request->getHeader('sentry-trace'));
            $this->assertNotEmpty($request->getHeader('baggage'));

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

        $currentEvent = Event::createEvent();
        (new GlobalScope())->merge($currentScope)->applyToEvent($currentEvent);

        $providedEvent = Event::createEvent();
        (new GlobalScope())->merge($providedScope)->applyToEvent($providedEvent);

        $this->assertCount(0, $currentEvent->getBreadcrumbs());
        $this->assertCount(1, $providedEvent->getBreadcrumbs());
    }

    /**
     * @dataProvider traceHeadersDataProvider
     */
    public function testTraceHeaders(Request $request, Options $options, bool $headersShouldBePresent): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn($options);

        SentrySdk::init($client);

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace();
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
        $client->method('getOptions')
            ->willReturn($options);

        SentrySdk::init($client);

        $transaction = \Sentry\startTransaction(new TransactionContext());

        SentrySdk::getIsolationScope()->setSpan($transaction);

        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace();
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
        $client->method('getOptions')
            ->willReturn(new Options([
                'trace_propagation_targets' => null,
            ]));

        SentrySdk::init($client);
        $expectedPromiseResult = new Response();

        $middleware = GuzzleTracingMiddleware::trace();
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
        $client->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]));

        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($scope, $request, $expectedPromiseResult, $expectedBreadcrumbData, $expectedSpanData): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());

                (new GlobalScope())->merge($scope)->applyToEvent($eventArg);

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

        $transaction = \Sentry\startTransaction(new TransactionContext());

        $scope->setSpan($transaction);

        $middleware = GuzzleTracingMiddleware::trace();
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

    public function testTraceCollectsConfiguredMetadata(): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            [],
            new Request('GET', 'https://user:password@www.example.com/path?search=hello%20world&password=secret#fragment', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'session_id=secret; theme=dark',
            ]),
            new Response(200, [
                'Content-Type' => 'application/json',
                'Set-Cookie' => ['session_id=secret; HttpOnly', 'theme=light; Path=/'],
            ])
        );

        $this->assertSame('https://[Filtered]:[Filtered]@www.example.com/path?search=hello%20world&password=[Filtered]#fragment', $spanData['url.full']);
        $this->assertSame('search=hello%20world&password=[Filtered]', $spanData['http.query']);
        $this->assertSame('[Filtered]', $spanData['http.request.header.authorization']);
        $this->assertSame('application/json', $spanData['http.response.header.content-type']);
        $this->assertSame(['session_id=[Filtered]', 'theme=dark'], $spanData['http.request.header.cookie']);
        $this->assertSame(['session_id=[Filtered]', 'theme=light'], $spanData['http.response.header.set-cookie']);
        $this->assertSame([
            'url' => $spanData['url.full'],
            'http.request.method' => 'GET',
            'http.request.body.size' => 0,
            'http.query' => $spanData['http.query'],
            'http.fragment' => 'fragment',
            'url.full' => $spanData['url.full'],
            'http.response.body.size' => 0,
            'http.response.status_code' => 200,
        ], $breadcrumbData);
    }

    public function testTraceCollectsCookiesWhenHeadersAreDisabled(): void
    {
        [$spanData] = $this->traceExchange(
            ['data_collection' => ['http_headers' => ['mode' => 'off']]],
            new Request('GET', 'https://www.example.com', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'theme=dark',
            ]),
            new Response(200, [
                'Content-Type' => 'application/json',
                'Set-Cookie' => 'theme=light; Path=/',
            ])
        );

        $this->assertSame(['theme=dark'], $spanData['http.request.header.cookie']);
        $this->assertSame(['theme=light'], $spanData['http.response.header.set-cookie']);
        $this->assertArrayNotHasKey('http.request.header.authorization', $spanData);
        $this->assertArrayNotHasKey('http.response.header.content-type', $spanData);
    }

    public function testTraceUsesSeparateRequestAndResponseHeaderRules(): void
    {
        [$spanData] = $this->traceExchange(
            ['data_collection' => [
                'http_headers' => [
                    'request' => ['mode' => 'allowList', 'terms' => ['x-request-id']],
                    'response' => ['mode' => 'allowList', 'terms' => ['x-response-id']],
                ],
                'cookies' => ['mode' => 'off'],
            ]],
            new Request('GET', 'https://www.example.com', [
                'X-Request-ID' => ['request-id', 'second-request-id'],
                'X-Response-ID' => 'request-value',
                'Cookie' => 'theme=dark',
            ]),
            new Response(200, [
                'X-Request-ID' => 'response-value',
                'X-Response-ID' => ['response-id', 'second-response-id'],
                'Set-Cookie' => 'theme=light',
            ])
        );

        $this->assertSame('request-id, second-request-id', $spanData['http.request.header.x-request-id']);
        $this->assertSame('[Filtered]', $spanData['http.request.header.x-response-id']);
        $this->assertSame('[Filtered]', $spanData['http.response.header.x-request-id']);
        $this->assertSame('response-id, second-response-id', $spanData['http.response.header.x-response-id']);
        $this->assertArrayNotHasKey('http.request.header.cookie', $spanData);
        $this->assertArrayNotHasKey('http.response.header.set-cookie', $spanData);
    }

    public function testTraceUsesConfiguredQueryFiltering(): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            ['data_collection' => ['url_query_params' => ['mode' => 'allowList', 'terms' => ['search']]]],
            new Request('GET', 'https://www.example.com?search=hello%20world&custom=value'),
            new Response()
        );

        $this->assertSame('search=hello%20world&custom=[Filtered]', $spanData['http.query']);
        $this->assertSame('https://www.example.com?' . $spanData['http.query'], $spanData['url.full']);
        $this->assertSame($spanData['http.query'], $breadcrumbData['http.query']);
        $this->assertSame($spanData['url.full'], $breadcrumbData['url']);
    }

    public function testTraceRespectsDisabledMetadataCollection(): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            ['data_collection' => [
                'cookies' => ['mode' => 'off'],
                'http_headers' => ['mode' => 'off'],
                'http_bodies' => [],
                'url_query_params' => ['mode' => 'off'],
            ]],
            new Request('GET', 'https://www.example.com?password=secret', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'theme=dark',
            ]),
            new Response(200, ['Content-Type' => 'application/json', 'Set-Cookie' => 'theme=light'])
        );

        $expected = [
            'http.request.method' => 'GET',
            'http.request.body.size' => 0,
            'url.full' => 'https://www.example.com',
            'http.response.body.size' => 0,
            'http.response.status_code' => 200,
        ];
        $this->assertSame($expected, $spanData);
        $this->assertSame(array_merge(['url' => 'https://www.example.com'], $expected), $breadcrumbData);
    }

    public function testTraceSupportsNumericHeaderNames(): void
    {
        [$spanData] = $this->traceExchange(
            [],
            new Request('GET', 'https://www.example.com/', ['123' => 'request']),
            new Response(200, ['456' => 'response'])
        );

        $this->assertSame('request', $spanData['http.request.header.123']);
        $this->assertSame('response', $spanData['http.response.header.456']);
    }

    public function testTraceConcurrentRequests(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1]));
        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();
        $transaction = \Sentry\startTransaction(new TransactionContext());
        $scope->setSpan($transaction);

        $sentRequests = [];
        $pendingPromises = [];
        $function = (GuzzleTracingMiddleware::trace($scope))(static function (Request $request) use ($scope, &$sentRequests, &$pendingPromises): PromiseInterface {
            $sentRequests[] = [$request, $scope->getSpan()];

            return $pendingPromises[] = new Promise();
        });

        $promise1 = $function(new Request('GET', 'https://www.example.com/1'), []);
        $promise2 = $function(new Request('GET', 'https://www.example.com/2'), []);

        $pendingPromises[0]->resolve(new Response(200));
        $pendingPromises[1]->resolve(new Response(200));
        $promise1->wait();
        $promise2->wait();

        $recorder = $transaction->getSpanRecorder();
        $this->assertNotNull($recorder);
        $spans = $recorder->getSpans();
        $this->assertCount(3, $spans);

        foreach ([1, 2] as $i) {
            [$request, $currentSpanDuringRequest] = $sentRequests[$i - 1];
            $span = $spans[$i];

            $this->assertSame('GET https://www.example.com/' . $i, $span->getDescription());
            $this->assertEquals($transaction->getSpanId(), $span->getParentSpanId());
            $this->assertSame($span, $currentSpanDuringRequest);
            $this->assertSame($span->toTraceparent(), $request->getHeaderLine('sentry-trace'));
            $this->assertNotNull($span->getEndTimestamp());
        }

        $this->assertSame($transaction, $scope->getSpan());
    }

    public function testTraceHeadersUseTheSpanOfTheGivenIsolationScope(): void
    {
        $currentClient = $this->createMock(ClientInterface::class);
        $currentClient->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1]));
        SentrySdk::init($currentClient);
        SentrySdk::getIsolationScope()->setSpan(\Sentry\startTransaction(new TransactionContext()));

        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1]));
        $scope = new IsolationScope();
        $scope->setClient($client);
        $transaction = TransactionSampler::startTransaction($client->getOptions(), new TransactionContext());
        $scope->setSpan($transaction);

        $sentRequests = [];
        $function = (GuzzleTracingMiddleware::trace($scope))(static function (Request $request) use ($scope, &$sentRequests): PromiseInterface {
            $sentRequests[] = [$request, $scope->getSpan()];

            return new FulfilledPromise(new Response());
        });

        $function(new Request('GET', 'https://www.example.com'), []);

        [$request, $span] = $sentRequests[0];

        $this->assertNotSame($transaction, $span);
        $this->assertSame($span->toTraceparent(), $request->getHeaderLine('sentry-trace'));
        $this->assertSame($span->toBaggage(), $request->getHeaderLine('baggage'));
    }

    public function testTraceRestoresTheCurrentSpanWhenTheHandlerThrows(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1]));
        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();
        $transaction = \Sentry\startTransaction(new TransactionContext());
        $scope->setSpan($transaction);
        $exception = new \RuntimeException('Handler error');
        $function = (GuzzleTracingMiddleware::trace($scope))(static function () use ($exception): PromiseInterface {
            throw $exception;
        });

        try {
            $function(new Request('GET', 'https://www.example.com'), []);
            $this->fail('The handler exception must be rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame($transaction, $scope->getSpan());
    }

    public function testTracePreservesExplicitSpanData(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1]));
        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();
        $transaction = \Sentry\startTransaction(new TransactionContext());
        $scope->setSpan($transaction);
        $function = (GuzzleTracingMiddleware::trace($scope))(function () use ($scope): PromiseInterface {
            $span = $scope->getSpan();
            $this->assertNotNull($span);
            $span->setData([
                'http.query' => 'explicit',
                'http.response.header.authorization' => ['Bearer explicit'],
            ]);

            return new FulfilledPromise(new Response(200, [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer automatic',
            ]));
        });

        $function(new Request('GET', 'https://www.example.com/?token=secret'), [])->wait();

        $data = $this->getHttpSpan($transaction)->getData();
        $this->assertSame('explicit', $data['http.query']);
        $this->assertSame(['Bearer explicit'], $data['http.response.header.authorization']);
        $this->assertSame('application/json', $data['http.response.header.content-type']);
    }

    /**
     * @dataProvider enabledBodyCollectionProvider
     *
     * @param array<string, mixed> $options
     */
    public function testTraceCollectsConfiguredBodies(array $options): void
    {
        $request = new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], '{"name":"Alice","password":"secret"}');
        $response = new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], 'status=ok&token=secret');
        $request->getBody()->seek(4);
        $response->getBody()->seek(5);

        [$spanData, $breadcrumbData] = $this->traceExchange($options, $request, $response);

        $this->assertSame('{"name":"Alice","password":"[Filtered]"}', $spanData['http.request.body.data']);
        $this->assertSame('{"status":"ok","token":"[Filtered]"}', $spanData['http.response.body.data']);
        $this->assertArrayNotHasKey('http.request.body.data', $breadcrumbData);
        $this->assertArrayNotHasKey('http.response.body.data', $breadcrumbData);
        $this->assertSame(4, $request->getBody()->tell());
        $this->assertSame(5, $response->getBody()->tell());
    }

    public static function enabledBodyCollectionProvider(): \Generator
    {
        yield 'defaults' => [[]];
        yield 'bodies independent of headers and cookies' => [['data_collection' => ['http_headers' => ['mode' => 'off'], 'cookies' => ['mode' => 'off']]]];
    }

    /**
     * @dataProvider disabledBodyCollectionProvider
     *
     * @param array<string, mixed> $options
     */
    public function testTraceDoesNotCollectDisabledBodies(array $options): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            $options,
            new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], '{"name":"Alice","password":"secret"}'),
            new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], 'status=ok&token=secret')
        );

        $this->assertArrayNotHasKey('http.request.body.data', $spanData);
        $this->assertArrayNotHasKey('http.response.body.data', $spanData);
        $this->assertArrayNotHasKey('http.request.body.data', $breadcrumbData);
        $this->assertArrayNotHasKey('http.response.body.data', $breadcrumbData);
    }

    public static function disabledBodyCollectionProvider(): \Generator
    {
        yield 'bodies disabled' => [['data_collection' => ['http_bodies' => []]]];
    }

    public function testTraceCollectsOnlyOutgoingRequestBody(): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            ['data_collection' => ['http_bodies' => ['outgoingRequest']]],
            new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], '{"name":"Alice","password":"secret"}'),
            new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], 'status=ok&token=secret')
        );

        $this->assertSame('{"name":"Alice","password":"[Filtered]"}', $spanData['http.request.body.data']);
        $this->assertArrayNotHasKey('http.response.body.data', $spanData);
        $this->assertArrayNotHasKey('http.request.body.data', $breadcrumbData);
        $this->assertArrayNotHasKey('http.response.body.data', $breadcrumbData);
    }

    /**
     * @dataProvider responseOnlyBodyCollectionProvider
     *
     * @param array<string, mixed> $options
     */
    public function testTraceCollectsOnlyIncomingResponseBody(array $options): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            $options,
            new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], '{"name":"Alice","password":"secret"}'),
            new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], 'status=ok&token=secret')
        );

        $this->assertArrayNotHasKey('http.request.body.data', $spanData);
        $this->assertSame('{"status":"ok","token":"[Filtered]"}', $spanData['http.response.body.data']);
        $this->assertArrayNotHasKey('http.request.body.data', $breadcrumbData);
        $this->assertArrayNotHasKey('http.response.body.data', $breadcrumbData);
    }

    public static function responseOnlyBodyCollectionProvider(): \Generator
    {
        yield 'incoming response only' => [['data_collection' => ['http_bodies' => ['incomingResponse']]]];
        yield 'request size never does not disable response bodies' => [['max_request_body_size' => 'never']];
    }

    public function testTraceCollectsBodiesFromRejectedRequests(): void
    {
        $request = new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], '{"name":"Alice","password":"secret"}');
        $response = new Response(503, ['Content-Type' => 'application/json'], '{"error":"unavailable","token":"secret"}');

        [$spanData, $breadcrumbData] = $this->traceExchange(
            [],
            $request,
            RequestException::create($request, $response)
        );

        $this->assertSame('{"name":"Alice","password":"[Filtered]"}', $spanData['http.request.body.data']);
        $this->assertSame('{"error":"unavailable","token":"[Filtered]"}', $spanData['http.response.body.data']);
        $this->assertArrayNotHasKey('http.request.body.data', $breadcrumbData);
        $this->assertArrayNotHasKey('http.response.body.data', $breadcrumbData);
    }

    /**
     * @dataProvider unreadBodyProvider
     *
     * @param array<string, mixed> $options
     */
    public function testTraceDoesNotReadUncollectedBodies(array $options, bool $attachSpan): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn(2);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects($this->never())->method('read');
        $stream->expects($this->never())->method('getContents');
        $stream->expects($this->never())->method('rewind');
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options($options + ['traces_sample_rate' => 1]));
        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();
        $transaction = \Sentry\startTransaction(new TransactionContext());
        if ($attachSpan) {
            $scope->setSpan($transaction);
        }
        $response = new Response(200, ['Content-Type' => 'application/json'], $stream);
        $function = (GuzzleTracingMiddleware::trace($scope))(static function () use ($response): PromiseInterface {
            return new FulfilledPromise($response);
        });

        $this->assertSame($response, $function(new Request('POST', 'https://www.example.com', ['Content-Type' => 'application/json'], $stream), [])->wait());
    }

    public static function unreadBodyProvider(): \Generator
    {
        yield 'no parent span' => [[], false];
        yield 'unsampled parent' => [['traces_sample_rate' => 0], true];
    }

    /**
     * @param array<string, mixed>      $options
     * @param RequestException|Response $responseOrException
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function traceExchange(array $options, Request $request, $responseOrException): array
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options($options + ['traces_sample_rate' => 1]));
        SentrySdk::init($client);
        $scope = SentrySdk::getIsolationScope();
        $transaction = \Sentry\startTransaction(new TransactionContext());
        $scope->setSpan($transaction);
        $requestPosition = $request->getBody()->tell();
        $function = (GuzzleTracingMiddleware::trace($scope))(function (Request $forwardedRequest) use ($request, $responseOrException, $requestPosition): PromiseInterface {
            $this->assertSame($requestPosition, $forwardedRequest->getBody()->tell());
            $this->assertSame((string) $request->getUri(), (string) $forwardedRequest->getUri());
            $this->assertSame($request->getHeader('Cookie'), $forwardedRequest->getHeader('Cookie'));

            return $responseOrException instanceof RequestException ? new RejectedPromise($responseOrException) : new FulfilledPromise($responseOrException);
        });

        try {
            $response = $function($request, [])->wait();
            if ($responseOrException instanceof RequestException) {
                $this->fail('The original request exception must be rethrown.');
            }

            $this->assertSame($responseOrException, $response);
        } catch (RequestException $exception) {
            $this->assertSame($responseOrException, $exception);
        }

        $event = Event::createEvent();
        SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event);
        $this->assertCount(1, $event->getBreadcrumbs());

        return [$this->getHttpSpan($transaction)->getData(), $event->getBreadcrumbs()[0]->getMetadata()];
    }

    private function getHttpSpan(Transaction $transaction): Span
    {
        $recorder = $transaction->getSpanRecorder();
        $this->assertNotNull($recorder);
        $spans = $recorder->getSpans();
        $this->assertCount(2, $spans);

        return $spans[1];
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
                'url.full' => 'https://www.example.com',
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'url.full' => 'https://www.example.com',
                'http.request.header.host' => 'www.example.com',
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
        ];

        yield [
            new Request('GET', 'https://user:password@www.example.com?query=string#fragment=1', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'theme=dark',
            ]),
            new Response(200, ['Content-Type' => 'application/json', 'Set-Cookie' => 'theme=light']),
            [
                'url' => 'https://[Filtered]:[Filtered]@www.example.com?query=string#fragment=1',
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.query' => 'query=string',
                'http.fragment' => 'fragment=1',
                'url.full' => 'https://[Filtered]:[Filtered]@www.example.com?query=string#fragment=1',
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'http.query' => 'query=string',
                'http.fragment' => 'fragment=1',
                'url.full' => 'https://[Filtered]:[Filtered]@www.example.com?query=string#fragment=1',
                'http.request.header.host' => 'www.example.com',
                'http.request.header.authorization' => '[Filtered]',
                'http.request.header.cookie' => ['theme=dark'],
                'http.response.body.size' => 0,
                'http.response.status_code' => 200,
                'http.response.header.content-type' => 'application/json',
                'http.response.header.set-cookie' => ['theme=light'],
            ],
        ];

        yield [
            new Request('POST', 'https://www.example.com', [], 'not-sentry'),
            new Response(403, [], 'sentry'),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'POST',
                'http.request.body.size' => 10,
                'url.full' => 'https://www.example.com',
                'http.response.body.size' => 6,
                'http.response.status_code' => 403,
            ],
            [
                'http.request.method' => 'POST',
                'http.request.body.size' => 10,
                'url.full' => 'https://www.example.com',
                'http.request.header.host' => 'www.example.com',
                'http.request.body.data' => '[Filtered]',
                'http.response.body.size' => 6,
                'http.response.status_code' => 403,
                'http.response.body.data' => '[Filtered]',
            ],
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new \Exception(),
            [
                'url' => 'https://www.example.com',
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'url.full' => 'https://www.example.com',
            ],
            [
                'http.request.method' => 'GET',
                'http.request.body.size' => 0,
                'url.full' => 'https://www.example.com',
                'http.request.header.host' => 'www.example.com',
            ],
        ];
    }
}
