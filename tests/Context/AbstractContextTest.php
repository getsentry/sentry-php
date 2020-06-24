<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\Context;

abstract class AbstractContextTest extends TestCase
{
    /**
     * @dataProvider valuesDataProvider
     */
    public function testConstructor(array $initialData, array $expectedData, ?string $expectedExceptionClass, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessageRegExp($expectedExceptionMessage);
        }

        $context = $this->createContext($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testMerge(array $initialData, array $expectedData, ?string $expectedExceptionClass, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessageRegExp($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->merge($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testSetData(array $initialData, array $expectedData, ?string $expectedExceptionClass, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessageRegExp($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->setData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testReplaceData(array $initialData, array $expectedData, ?string $expectedExceptionClass, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessageRegExp($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->replaceData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider offsetSetDataProvider
     */
    public function testOffsetSet(string $key, $value, ?string $expectedExceptionClass, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessageRegExp($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context[$key] = $value;

        $this->assertArrayHasKey($key, $context);
        $this->assertSame($value, $context[$key]);
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $value): void
    {
        $context = $this->createContext();
        $context->$setterMethod($value);

        $this->assertEquals($value, $context->$getterMethod());
    }

    abstract public function valuesDataProvider(): array;

    abstract public function offsetSetDataProvider(): array;

    abstract public function gettersAndSettersDataProvider(): array;

    abstract protected function createContext(array $initialData = []): Context;
}
