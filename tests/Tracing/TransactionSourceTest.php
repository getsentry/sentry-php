<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\TransactionSource;

final class TransactionSourceTest extends TestCase
{
    public function testCustom(): void
    {
        $transactionSource = TransactionSource::custom();

        $this->assertSame('custom', (string) $transactionSource);
    }

    public function testUrl(): void
    {
        $transactionSource = TransactionSource::url();

        $this->assertSame('url', (string) $transactionSource);
    }

    public function testRoute(): void
    {
        $transactionSource = TransactionSource::route();

        $this->assertSame('route', (string) $transactionSource);
    }

    public function testView(): void
    {
        $transactionSource = TransactionSource::view();

        $this->assertSame('view', (string) $transactionSource);
    }

    public function testComponent(): void
    {
        $transactionSource = TransactionSource::component();

        $this->assertSame('component', (string) $transactionSource);
    }

    public function testTask(): void
    {
        $transactionSource = TransactionSource::task();

        $this->assertSame('task', (string) $transactionSource);
    }
}
