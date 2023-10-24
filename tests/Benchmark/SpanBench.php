<?php

declare(strict_types=1);

namespace Sentry\Tests\Benchmark;

use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionContext;

use function Sentry\continueTrace;

final class SpanBench
{
    /**
     * @var TransactionContext
     */
    private $context;

    /**
     * @var TransactionContext
     */
    private $contextWithTimestamp;

    public function __construct()
    {
        $this->context = continueTrace('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0', '');
        $this->contextWithTimestamp = continueTrace('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0', '');
        $this->contextWithTimestamp->setStartTimestamp(microtime(true));
    }

    /**
     * @Revs(100000)
     *
     * @Iterations(10)
     */
    public function benchConstructor(): void
    {
        $span = new Span();
    }

    /**
     * @Revs(100000)
     *
     * @Iterations(10)
     */
    public function benchConstructorWithInjectedContext(): void
    {
        $span = new Span($this->context);
    }

    /**
     * @Revs(100000)
     *
     * @Iterations(10)
     */
    public function benchConstructorWithInjectedContextAndStartTimestamp(): void
    {
        $span = new Span($this->contextWithTimestamp);
    }
}
