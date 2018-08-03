<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Context;

use PHPUnit\Framework\TestCase;
use Raven\Context\RuntimeContext;
use Raven\Util\PHPVersion;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

class RuntimeContextTest extends TestCase
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

        $context = new RuntimeContext($initialData);

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

        $context = new RuntimeContext();
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

        $context = new RuntimeContext();
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

        $context = new RuntimeContext();
        $context->replaceData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    public function valuesDataProvider()
    {
        return [
            [
                [],
                [
                    'name' => 'php',
                    'version' => PHPVersion::getParsed(),
                ],
                null,
                null,
            ],
            [
                [
                    'name' => 'foo',
                ],
                [
                    'name' => 'foo',
                    'version' => PHPVersion::getParsed(),
                ],
                null,
                null,
            ],
            [
                [
                    'name' => 'foo',
                    'version' => 'bar',
                ],
                [
                    'name' => 'foo',
                    'version' => 'bar',
                ],
                null,
                null,
            ],
            [
                [
                    'foo' => 'bar',
                ],
                [],
                UndefinedOptionsException::class,
                'The option "foo" does not exist. Defined options are: "name", "version".',
            ],
            [
                [
                    'name' => 1,
                ],
                [],
                InvalidOptionsException::class,
                'The option "name" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                [
                    'version' => 1,
                ],
                [],
                InvalidOptionsException::class,
                'The option "version" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
        ];
    }

    /**
     * @dataProvider offsetSetDataProvider
     */
    public function testOffsetSet($key, $value, $expectedExceptionClass, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = new RuntimeContext();
        $context[$key] = $value;

        $this->assertArraySubset([$key => $value], $context->toArray());
    }

    public function offsetSetDataProvider()
    {
        return [
            [
                'name',
                'foo',
                null,
                null,
            ],
            [
                'name',
                1,
                InvalidOptionsException::class,
                'The option "name" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                'version',
                1,
                InvalidOptionsException::class,
                'The option "version" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                'foo',
                'bar',
                UndefinedOptionsException::class,
                'The option "foo" does not exist. Defined options are: "name", "version".',
            ],
        ];
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($getterMethod, $setterMethod, $value)
    {
        $context = new RuntimeContext();
        $context->$setterMethod($value);

        $this->assertEquals($value, $context->$getterMethod());
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            [
                'getName',
                'setName',
                'foo',
            ],
            [
                'getVersion',
                'setVersion',
                'bar',
            ],
        ];
    }
}
