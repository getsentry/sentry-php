<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


class Raven_Tests_StacktraceTest extends PHPUnit_Framework_TestCase
{
    public function testCanTraceParamContext()
    {
        function test($foo, $bar) {
            return debug_backtrace();
        }
        $stack = test('biz', 'baz');

        $frame = $stack[0];
        $params = Raven_Stacktrace::get_frame_context($frame);

        $this->assertEquals($params['foo'], 'biz');
        $this->assertEquals($params['bar'], 'baz');
    }
}
