<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\TaskQueue;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

final class GuzzleTracingMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Utils::queue(new TaskQueue(false));
    }

    public function testTraceCreatesBreadcrumbIfSpanIsNotSet(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeast(2))
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 0,
            ]));

        SentrySdk::init($client);
        $transaction = SentrySdk::startTransaction(TransactionContext::make());

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

        SentrySdk::configureScope(function (Scope $scope): void {
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

        SentrySdk::init($client);
        $transaction = SentrySdk::startTransaction(TransactionContext::make());

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

        SentrySdk::configureScope(function (Scope $scope): void {
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

        $expectedPromiseResult = new Response();

        SentrySdk::init($client);
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
        $client->expects($this->atLeast(2))
            ->method('getOptions')
            ->willReturn($options);

        SentrySdk::init($client);
        $transaction = SentrySdk::startTransaction(new TransactionContext());
        SentrySdk::getCurrentScope()->setSpan($transaction);

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
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1,
                'trace_propagation_targets' => [
                    'www.example.com',
                ],
            ]));

        SentrySdk::init($client);

        $capturedEvent = null;
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $this->callback(static function (Event $eventArg) use (&$capturedEvent): bool {
                    $capturedEvent = $eventArg;

                    return $eventArg->getType() === EventType::transaction();
                }),
                null,
                $this->callback(function (Scope $scope) use (&$capturedEvent, $request, $expectedPromiseResult, $expectedBreadcrumbData, $expectedSpanData): bool {
                    if ($capturedEvent === null) {
                        return false;
                    }

                    $eventArg = $scope->applyToEvent($capturedEvent);

                    $this->assertNotNull($eventArg);

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
                })
            );

        $transaction = SentrySdk::startTransaction(new TransactionContext());
        SentrySdk::getCurrentScope()->setSpan($transaction);

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
