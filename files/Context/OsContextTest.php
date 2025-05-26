<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\OsContext;

final class OsContextTest extends TestCase
{
    /**
     * @dataProvider valuesDataProvider
     */
    public function testConstructor(string $expectedName, ?string $expectedVersion, ?string $expectedBuild, ?string $expectedKernelVersion, ?string $expectedMachineType): void
    {
        $context = new OsContext($expectedName, $expectedVersion, $expectedBuild, $expectedKernelVersion, $expectedMachineType);

        $this->assertSame($expectedName, $context->getName());
        $this->assertSame($expectedVersion, $context->getVersion());
        $this->assertSame($expectedBuild, $context->getBuild());
        $this->assertSame($expectedKernelVersion, $context->getKernelVersion());
        $this->assertSame($expectedMachineType, $context->getMachineType());
    }

    public function testConstructorThrowsOnInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $name argument cannot be an empty string.');

        new OsContext('');
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testGettersAndSetters(string $expectedName, ?string $expectedVersion, ?string $expectedBuild, ?string $expectedKernelVersion, ?string $expectedMachineType): void
    {
        $context = new OsContext('Windows');
        $context->setName($expectedName);
        $context->setVersion($expectedVersion);
        $context->setBuild($expectedBuild);
        $context->setKernelVersion($expectedKernelVersion);
        $context->setMachineType($expectedMachineType);

        $this->assertSame($expectedName, $context->getName());
        $this->assertSame($expectedVersion, $context->getVersion());
        $this->assertSame($expectedBuild, $context->getBuild());
        $this->assertSame($expectedKernelVersion, $context->getKernelVersion());
        $this->assertSame($expectedMachineType, $context->getMachineType());
    }

    public static function valuesDataProvider(): iterable
    {
        yield [
            'Linux',
            '4.19.104-microsoft-standard',
            '#1 SMP Wed Feb 19 06:37:35 UTC 2020',
            'Linux c03a247f5e13 4.19.104-microsoft-standard #1 SMP Wed Feb 19 06:37:35 UTC 2020 x86_64',
            'x86_64',
        ];

        yield [
            'Linux',
            null,
            null,
            null,
            null,
        ];
    }
}
