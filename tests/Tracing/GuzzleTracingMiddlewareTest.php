<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

final class GuzzleTracingMiddlewareTest extends TestCase
{
    /**
     * @dataProvider traceDataProvider
     */
    public function testTrace(Request $request, $expectedPromiseResult, array $expectedBreadcrumbData): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn(new Options(['traces_sample_rate' => 1]));

        $hub = new Hub($client);

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($hub, $request, $expectedPromiseResult, $expectedBreadcrumbData): bool {
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

                $this->assertSame('http.client', $guzzleSpan->getOp());
                $this->assertSame("{$request->getMethod()} {$request->getUri()}", $guzzleSpan->getDescription());

                if ($expectedPromiseResult instanceof Response) {
                    $this->assertSame(SpanStatus::createFromHttpStatusCode($expectedPromiseResult->getStatusCode()), $guzzleSpan->getStatus());
                } else {
                    $this->assertSame(SpanStatus::internalError(), $guzzleSpan->getStatus());
                }

                $this->assertSame($expectedBreadcrumbData, $guzzleBreadcrumb->getMetadata());

                return true;
            }));

        $transaction = $hub->startTransaction(new TransactionContext());

        $hub->setSpan($transaction);

        $middleware = GuzzleTracingMiddleware::trace($hub);
        $function = $middleware(static function () use ($expectedPromiseResult): PromiseInterface {
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

    public function traceDataProvider(): iterable
    {
        yield [
            new Request('GET', 'https://www.example.com'),
            new Response(),
            [
                'url' => 'https://www.example.com',
                'method' => 'GET',
                'request_body_size' => 0,
                'status_code' => 200,
                'response_body_size' => 0,
            ],
        ];

        yield [
            new Request('POST', 'https://www.example.com', [], 'not-sentry'),
            new Response(403, [], 'sentry'),
            [
                'url' => 'https://www.example.com',
                'method' => 'POST',
                'request_body_size' => 10,
                'status_code' => 403,
                'response_body_size' => 6,
            ],
        ];

        yield [
            new Request('GET', 'https://www.example.com'),
            new \Exception(),
            [
                'url' => 'https://www.example.com',
                'method' => 'GET',
                'request_body_size' => 0,
            ],
        ];

        yield [
            new Request('POST', 'https://www.example.com', [], 'sentry'),
            new \Exception(),
            [
                'url' => 'https://www.example.com',
                'method' => 'POST',
                'request_body_size' => 6,
            ],
        ];
    }
}
