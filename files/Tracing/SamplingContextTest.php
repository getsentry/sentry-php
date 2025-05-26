<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\TransactionContext;

final class SamplingContextTest extends TestCase
{
    public function testGetDefault(): void
    {
        $transactionContext = new TransactionContext(TransactionContext::DEFAULT_NAME, true);
        $samplingContext = SamplingContext::getDefault($transactionContext);

        $this->assertSame($transactionContext, $samplingContext->getTransactionContext());
        $this->assertSame($transactionContext->getParentSampled(), $samplingContext->getParentSampled());
    }
}
