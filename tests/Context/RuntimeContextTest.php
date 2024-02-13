<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\RuntimeContext;

final class RuntimeContextTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = new RuntimeContext('php', '7.4', 'fpm');

        $this->assertSame('php', $context->getName());
        $this->assertSame('fpm', $context->getSAPI());
        $this->assertSame('7.4', $context->getVersion());
    }

    public function testConstructorThrowsOnInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $name argument cannot be an empty string.');

        new RuntimeContext('');
    }

    public function testGettersAndSetters(): void
    {
        $context = new RuntimeContext('php');
        $context->setName('go');
        $context->setSAPI('fpm');
        $context->setVersion('1.15');

        $this->assertSame('go', $context->getName());
        $this->assertSame('fpm', $context->getSAPI());
        $this->assertSame('1.15', $context->getVersion());
    }
}
