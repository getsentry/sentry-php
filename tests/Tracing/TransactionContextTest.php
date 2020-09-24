<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\TransactionContext;

final class TransactionContextTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $transactionContext = new TransactionContext();

        $this->assertSame('<unlabeled transaction>', $transactionContext->getName());
        $this->assertNull($transactionContext->getParentSampled());

        $transactionContext->setName('foo');
        $transactionContext->setParentSampled(true);

        $this->assertSame('foo', $transactionContext->getName());
        $this->assertTrue($transactionContext->getParentSampled());
    }
}
