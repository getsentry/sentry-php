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
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

final class GuzzleTracingMiddlewareTest extends TestCase
{
    /**
     * @dataProvider traceDataProvider
     */
    public function testTrace($expectedPromiseResult): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['traces_sample_rate' => 1]));

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($expectedPromiseResult): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());

                $spans = $eventArg->getSpans();

                $this->assertCount(1, $spans);

                $guzzleSpan = $spans[0];

                $this->assertSame('http.client', $guzzleSpan->getOp());
                $this->assertSame('GET https://www.example.com', $guzzleSpan->getDescription());

                $expectedSpanData = [
                    'url' => 'https://www.example.com',
                    'method' => 'GET',
                    'request_body_size' => 0,
                ];

                if ($expectedPromiseResult instanceof Response) {
                    $this->assertSame(SpanStatus::createFromHttpStatusCode($expectedPromiseResult->getStatusCode()), $guzzleSpan->getStatus());

                    $expectedSpanData['status_code'] = $expectedPromiseResult->getStatusCode();
                    $expectedSpanData['response_body_size'] = $expectedPromiseResult->getBody()->getSize();
                } else {
                    $this->assertSame(SpanStatus::internalError(), $guzzleSpan->getStatus());
                }

                $this->assertSame($expectedSpanData, $guzzleSpan->getData());

                return true;
            }));

        $hub = new Hub($client);
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
        $promise = $function(new Request('GET', 'https://www.example.com'), []);

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
            new Response(),
        ];

        yield [
            new \Exception(),
        ];

        yield [
            new Response(403, [], 'sentry'),
        ];
    }
}
