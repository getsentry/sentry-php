<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Raven\TransactionStack;

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
        $stack = new TransactionStack();

        $this->assertAttributeEmpty('transactions', $stack);

        $stack->push('a', 'b');

        $this->assertAttributeEquals(['a', 'b'], 'transactions', $stack);

        $stack->clear();

        $this->assertAttributeEmpty('transactions', $stack);
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

        $this->assertAttributeEmpty('transactions', $stack);

        $stack->push('a');

        $this->assertAttributeEquals(['a'], 'transactions', $stack);

        $stack->push('b', 'c');

        $this->assertAttributeEquals(['a', 'b', 'c'], 'transactions', $stack);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $values argument must contain string values only.
     */
    public function testPushThrowsIfValuesAreNotAllStrings()
    {
        $stack = new TransactionStack();

        $this->assertAttributeEmpty('transactions', $stack);

        $stack->push('a', 1);
    }

    /**
     * @dataProvider peekDataProvider
     */
    public function testPeek($initialData, $expectedData, $expectedExceptionThrow)
    {
        if ($expectedExceptionThrow) {
            $this->expectException(\UnderflowException::class);
            $this->expectExceptionMessage('Peeking an empty stack is not allowed.');
        }

        $stack = new TransactionStack($initialData);

        $this->assertEquals($expectedData, $stack->peek());
    }

    public function peekDataProvider()
    {
        return [
            [
                ['a', 'b'],
                'b',
                false,
            ],
            [
                [],
                null,
                true,
            ],
        ];
    }

    /**
     * @dataProvider popDataProvider
     */
    public function testPop($initialData, $expectedData, $expectedRemainingData, $expectedExceptionThrow)
    {
        if ($expectedExceptionThrow) {
            $this->expectException(\UnderflowException::class);
            $this->expectExceptionMessage('Popping an empty stack is not allowed.');
        }

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
                false,
            ],
            [
                [],
                null,
                [],
                true,
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
