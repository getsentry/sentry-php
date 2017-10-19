<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


class Raven_Tests_TransactionStackTest extends \PHPUnit\Framework\TestCase
{
    public function testSimple()
    {
        $stack = new Raven_TransactionStack();
        $stack->push('hello');
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $foo = $stack->push('foo');
        $stack->push('bar');
        $stack->push('world');
        $this->assertEquals($stack->peek(), 'world');
        $this->assertEquals($stack->pop(), 'world');
        $this->assertEquals($stack->pop('foo'), 'foo');
        $this->assertEquals($stack->peek(), 'hello');
        $this->assertEquals($stack->pop(), 'hello');
        $this->assertEquals($stack->peek(), null);

        $stack->clear();
        $this->assertInternalType('array', $stack->stack);
        $this->assertEquals(0, count($stack->stack));
    }
}
