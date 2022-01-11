<?php

namespace Sentry\Tests\Benchmark;

use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use Sentry\Tracing\Span;

class SpanBench
{
    /**
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchConstructor(): void
    {
        $span = new Span();
    }
}
