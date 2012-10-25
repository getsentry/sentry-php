<?php

use Raven\Stacktrace;

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


function Raven_test_recurse($times, $callback) {
    $times -= 1;
    if ($times > 0) {
        return call_user_func('Raven_test_recurse', $times, $callback);
    }
    return call_user_func($callback);
}

function Raven_test_create_stacktrace($args=null, $times=3) {
    return Raven_test_recurse($times, 'debug_backtrace');
}

class Raven_Tests_StacktraceTest extends PHPUnit_Framework_TestCase
{
    public function testCanTraceParamContext()
    {
        $stack = Raven_test_create_stacktrace(array('biz', 'baz'), 0);

        $frame = $stack[2];
        $params = Stacktrace::get_frame_context($frame);
        $this->assertEquals($params['args'], array('biz', 'baz'));
        $this->assertEquals($params['times'], 0);
    }

    public function testDoesFixFrameInfo()
    {
        /**
         * PHP's way of storing backstacks seems bass-ackwards to me
         * 'function' is not the function you're in; it's any function being
         * called, so we have to shift 'function' down by 1. Ugh.
         */
        $stack = Raven_test_create_stacktrace();

        $frames = Stacktrace::get_stack_info($stack, true);
        // just grab last three frames
        $frames = array_slice($frames, -7);
        $frame = $frames[0];
        $this->assertEquals($frame['module'], 'StacktraceTest.php:Raven_Tests_StacktraceTest');
        $this->assertEquals($frame['function'], 'testDoesFixFrameInfo');
        $frame = $frames[1];
        $this->assertEquals($frame['module'], 'StacktraceTest.php');
        $this->assertEquals($frame['function'], 'Raven_test_create_stacktrace');
        $frame = $frames[2];
        $this->assertEquals($frame['module'], 'StacktraceTest.php');
        $this->assertEquals($frame['function'], 'Raven_test_recurse');
        $frame = $frames[3];
        $this->assertEquals($frame['module'], '[Anonymous function]');
        $this->assertEquals($frame['function'], 'call_user_func');
        $frame = $frames[4];
        $this->assertEquals($frame['module'], 'StacktraceTest.php');
        $this->assertEquals($frame['function'], 'Raven_test_recurse');
        $frame = $frames[5];
        $this->assertEquals($frame['module'], '[Anonymous function]');
        $this->assertEquals($frame['function'], 'call_user_func');
        $frame = $frames[6];
        $this->assertEquals($frame['module'], 'StacktraceTest.php');
        $this->assertEquals($frame['function'], 'Raven_test_recurse');
    }
}
