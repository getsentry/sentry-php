<?php

namespace Sentry\Tests\Benchmark;

use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionContext;

class SpanBench
{
    /** @var TransactionContext */
    private $context;

    public function __construct()
    {
        $this->context = TransactionContext::fromSentryTrace('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0');
    }

    /**
     * @Revs(100000)
     * @Iterations(10)
     */
    public function benchConstructor(): void
    {
        $span = new Span();
    }

    /**
     * @Revs(100000)
     * @Iterations(10)
     */
    public function benchConstructorWithInjectedContext(): void
    {
        $span = new Span($this->context);
    }
}
