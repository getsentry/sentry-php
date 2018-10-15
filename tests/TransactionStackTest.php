<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\TransactionStack;

class TransactionStackTest extends TestCase
{
    public function testConstructor()
    {
        $stack = new TransactionStack(['a', 'b']);

        $this->assertAttributeEquals(['a', 'b'], 'transactions', $stack);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $values argument must contain string values only.
     */
    public function testConstructorThrowsIfValuesAreNotAllStrings()
    {
        new TransactionStack(['a', 1]);
    }

    public function testClear()
    {
        $stack = new TransactionStack(['a', 'b']);

        $this->assertAttributeEquals(['a', 'b'], 'transactions', $stack);

        $stack->clear();

        $this->assertEmpty($stack);
    }

    public function testIsEmpty()
    {
        $stack = new TransactionStack();

        $this->assertEmpty($stack);
        $this->assertTrue($stack->isEmpty());

        $stack->push('a');

        $this->assertNotEmpty($stack);
        $this->assertFalse($stack->isEmpty());
    }

    public function testPush()
    {
        $stack = new TransactionStack();
        $stack->push('a');

        $this->assertAttributeEquals(['a'], 'transactions', $stack);

        $stack->push('b', 'c');

        $this->assertAttributeEquals(['a', 'b', 'c'], 'transactions', $stack);
    }

    /**
     * @dataProvider peekDataProvider
     */
    public function testPeek($initialData, $expectedData, $expectedRemainingData)
    {
        $stack = new TransactionStack($initialData);

        $this->assertEquals($expectedData, $stack->peek());
        $this->assertAttributeEquals($expectedRemainingData, 'transactions', $stack);
    }

    public function peekDataProvider()
    {
        return [
            [
                ['a', 'b'],
                'b',
                ['a', 'b'],
            ],
            [
                [],
                null,
                [],
            ],
        ];
    }

    /**
     * @dataProvider popDataProvider
     */
    public function testPop($initialData, $expectedData, $expectedRemainingData)
    {
        $stack = new TransactionStack($initialData);

        $this->assertEquals($expectedData, $stack->pop());
        $this->assertAttributeEquals($expectedRemainingData, 'transactions', $stack);
    }

    public function popDataProvider()
    {
        return [
            [
                ['a', 'b'],
                'b',
                ['a'],
            ],
            [
                [],
                null,
                [],
            ],
        ];
    }

    public function testCount()
    {
        $stack = new TransactionStack();

        $this->assertCount(0, $stack);

        $stack->push('a');

        $this->assertCount(1, $stack);
    }
}
