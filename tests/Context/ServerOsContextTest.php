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
use Raven\Context\ServerOsContext;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

class ServerOsContextTest extends TestCase
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

        $context = new ServerOsContext($initialData);

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

        $context = new ServerOsContext();
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

        $context = new ServerOsContext();
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

        $context = new ServerOsContext();
        $context->replaceData($initialData);

        $this->assertEquals($expectedData, $context->toArray());
    }

    public function valuesDataProvider()
    {
        return [
            [
                [],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
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
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'version' => 'bar',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => 'bar',
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'build' => 'baz',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => 'baz',
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'kernel_version' => 'foobarbaz',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => 'foobarbaz',
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
                'The option "foo" does not exist. Defined options are: "build", "kernel_version", "name", "version".',
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
            [
                [
                    'build' => 1,
                ],
                [],
                InvalidOptionsException::class,
                'The option "build" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                [
                    'kernel_version' => 1,
                ],
                [],
                InvalidOptionsException::class,
                'The option "kernel_version" with value 1 is expected to be of type "string", but is of type "integer".',
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

        $context = new ServerOsContext();
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
                'foo',
                null,
                null,
            ],
            [
                'version',
                1,
                InvalidOptionsException::class,
                'The option "version" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                'build',
                'foo',
                null,
                null,
            ],
            [
                'build',
                1,
                InvalidOptionsException::class,
                'The option "build" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                'kernel_version',
                'foobarbaz',
                null,
                null,
            ],
            [
                'kernel_version',
                1,
                InvalidOptionsException::class,
                'The option "kernel_version" with value 1 is expected to be of type "string", but is of type "integer".',
            ],
            [
                'foo',
                'bar',
                UndefinedOptionsException::class,
                'The option "foo" does not exist. Defined options are: "build", "kernel_version", "name", "version".',
            ],
        ];
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($getterMethod, $setterMethod, $value)
    {
        $context = new ServerOsContext();
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
            [
                'getBuild',
                'setBuild',
                'baz',
            ],
            [
                'getKernelVersion',
                'setKernelVersion',
                'foobarbaz',
            ],
        ];
    }
}
