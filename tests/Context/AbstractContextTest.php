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
    public function testConstructor($initialData, $expectedData, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = $this->createContext($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testMerge($initialData, $expectedData, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->merge($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testSetData($initialData, $expectedData, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->setData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testReplaceData($initialData, $expectedData, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context->replaceData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    abstract public function valuesDataProvider(): array;

    /**
     * @dataProvider offsetSetDataProvider
     */
    public function testOffsetSet($key, $value, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = $this->createContext();
        $context[$key] = $value;

        $this->assertArraySubset([$key => $value], $context->toArray());
    }

    abstract public function offsetSetDataProvider(): array;

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($getterMethod, $setterMethod, $value)
    {
        $context = $this->createContext();
        $context->$setterMethod($value);

        $this->assertEquals($value, $context->$getterMethod());
    }

    abstract public function gettersAndSettersDataProvider(): array;

    abstract protected function createContext(array $initialData = []): Context;
}
