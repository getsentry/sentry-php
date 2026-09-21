<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
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
        $client->method('getOptions')
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
        $client->method('getOptions')
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
        $client->method('getOptions')
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
        $client->method('getOptions')
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
        $client->method('getOptions')
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
        $client->method('getOptions')
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

    public function testTraceCollectsConfiguredMetadata(): void
    {
        [$spanData, $breadcrumbData] = $this->traceExchange(
            ['data_collection' => []],
            new Request('GET', 'https://user:password@www.example.com/path?search=hello%20world&password=secret#fragment', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'session_id=secret; theme=dark',
            ]),
            new Response(200, [
                'Content-Type' => 'application/json',
                'Set-Cookie' => ['session_id=secret; HttpOnly', 'theme=light; Path=/'],
            ])
        );

        $this->assertSame('https://www.example.com/path?search=hello%20world&password=[Filtered]', $spanData['url.full']);
        $this->assertSame('search=hello%20world&password=[Filtered]', $spanData['http.query']);
        $this->assertSame(['[Filtered]'], $spanData['http.request.header.authorization']);
        $this->assertSame(['application/json'], $spanData['http.response.header.content-type']);
        $this->assertSame('[Filtered]', $spanData['http.request.header.cookie.session_id']);
        $this->assertSame('dark', $spanData['http.request.header.cookie.theme']);
        $this->assertSame('[Filtered]', $spanData['http.response.header.set_cookie.session_id']);
        $this->assertSame('light', $spanData['http.response.header.set_cookie.theme']);
        $this->assertArrayNotHasKey('http.request.header.cookie', $spanData);
        $this->assertArrayNotHasKey('http.response.header.set-cookie', $spanData);
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

    public function testTracePreservesExplicitSpanData(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options(['traces_sample_rate' => 1, 'data_collection' => []]));
        $hub = new Hub($client);
        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);
        $function = (GuzzleTracingMiddleware::trace($hub))(function () use ($hub): PromiseInterface {
            $span = $hub->getSpan();
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
        $this->assertSame(['application/json'], $data['http.response.header.content-type']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function traceExchange(array $options, Request $request, Response $response): array
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn(new Options($options + ['traces_sample_rate' => 1]));
        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);
        $transaction = $hub->startTransaction(new TransactionContext());
        $hub->setSpan($transaction);
        $function = (GuzzleTracingMiddleware::trace($hub))(function (Request $forwardedRequest) use ($request, $response): PromiseInterface {
            $this->assertSame((string) $request->getUri(), (string) $forwardedRequest->getUri());
            $this->assertSame($request->getHeader('Cookie'), $forwardedRequest->getHeader('Cookie'));

            return new FulfilledPromise($response);
        });

        $this->assertSame($response, $function($request, [])->wait());

        $event = Event::createEvent();
        $hub->configureScope(static function (Scope $scope) use ($event): void {
            $scope->applyToEvent($event);
        });
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
            new Request('GET', 'https://user:password@www.example.com?query=string#fragment=1', [
                'Authorization' => 'Bearer secret',
                'Cookie' => 'theme=dark',
            ]),
            new Response(200, ['Content-Type' => 'application/json', 'Set-Cookie' => 'theme=light']),
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
