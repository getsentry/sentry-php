<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\State\Scope;
use Sentry\Tests\StubTransport;
use Sentry\Tracing\TransactionContext;

use function Sentry\configureScope;
use function Sentry\init;
use function Sentry\startTransaction;

final class FunctionTracingIntegrationSmokeTest extends TestCase
{
    public function testExtensionCreatesNestedSdkSpans(): void
    {
        if (!\function_exists('Sentry\\instrument')) {
            $this->markTestSkipped('The Sentry PHP tracer extension is not loaded.');
        }

        init([
            'dsn' => null,
            'default_integrations' => true,
            'traces_sample_rate' => 1.0,
            'transport' => StubTransport::getInstance(),
        ]);

        $transaction = startTransaction(new TransactionContext('tracer-smoke'));
        configureScope(static function (Scope $scope) use ($transaction): void {
            $scope->setSpan($transaction);
        });

        \Sentry\instrument(self::class, 'tracerSmokeOuter');
        \Sentry\instrument(self::class, 'tracerSmokeInner');

        self::tracerSmokeOuter();
        $transaction->finish();

        $this->assertCount(1, StubTransport::$events);

        $spans = StubTransport::$events[0]->getSpans();
        $this->assertCount(2, $spans);

        $this->assertSame(self::class . '::tracerSmokeOuter', $spans[0]->getDescription());
        $this->assertSame(self::class . '::tracerSmokeInner', $spans[1]->getDescription());
        $this->assertSame($transaction->getSpanId(), $spans[0]->getParentSpanId());
        $this->assertSame($spans[0]->getSpanId(), $spans[1]->getParentSpanId());
        $this->assertNotNull($spans[0]->getEndTimestamp());
        $this->assertNotNull($spans[1]->getEndTimestamp());
        $this->assertGreaterThanOrEqual($spans[0]->getStartTimestamp(), $spans[0]->getEndTimestamp());
        $this->assertGreaterThanOrEqual($spans[1]->getStartTimestamp(), $spans[1]->getEndTimestamp());
    }

    public static function tracerSmokeOuter(): void
    {
        self::tracerSmokeInner();
    }

    public static function tracerSmokeInner(): void
    {
    }
}
