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
            ->with($this->callback(function (Event $eventArg): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());

                $spans = $eventArg->getSpans();

                $this->assertCount(1, $spans);
                $this->assertSame('http.guzzle', $spans[0]->getOp());
                $this->assertSame('GET http://www.example.com', $spans[0]->getDescription());

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
        $promise = $function(new Request('GET', 'http://www.example.com'), []);

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
    }
}
