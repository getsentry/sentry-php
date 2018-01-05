<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function raven_test_recurse($times, $callback)
{
    $times -= 1;
    if ($times > 0) {
        return call_user_func('raven_test_recurse', $times, $callback);
    }

    return call_user_func($callback);
}

function raven_test_create_stacktrace($args=null, $times=3)
{
    return raven_test_recurse($times, 'debug_backtrace');
}

class Raven_Tests_StacktraceTest extends \PHPUnit\Framework\TestCase
{
    public function testCanTraceParamContext()
    {
        $stack = raven_test_create_stacktrace(array('biz', 'baz'), 0);

        if (isset($stack[0]['function']) and ($stack[0]['function'] == 'call_user_func')) {
            $offset = 2;
        } else {
            $offset = 1;
        }
        $frame = $stack[$offset];
        $params = Raven_Stacktrace::get_frame_context($frame);
        $this->assertEquals($params['args'], array('biz', 'baz'));
        $this->assertEquals($params['times'], 0);
    }

    public function testSimpleTrace()
    {
        $stack = array(
            array(
                'file'     => dirname(__FILE__).'/resources/a.php',
                'line'     => 9,
                'function' => 'a_test',
                'args'     => array('friend'),
            ),
            array(
                'file'     => dirname(__FILE__).'/resources/b.php',
                'line'     => 2,
                'args'     => array(
                    dirname(__FILE__).'/resources/a.php',
                ),
                'function' => 'include_once',
            )
        );

        $frames = Raven_Stacktrace::get_stack_info($stack, true);

        $frame = $frames[0];
        $this->assertEquals(2, $frame['lineno']);
        $this->assertNull($frame['function']);
        $this->assertEquals("include_once 'a.php';", $frame['context_line']);
        $this->assertFalse(isset($frame['vars']));
        $frame = $frames[1];
        $this->assertEquals(9, $frame['lineno']);
        $this->assertEquals('include_once', $frame['function']);
        $this->assertEquals('a_test($foo);', $frame['context_line']);
        $this->assertEquals(dirname(__FILE__) . '/resources/a.php', $frame['vars']['param1']);
    }

    public function testDoesNotModifyCaptureVars()
    {

        // PHP's errcontext as passed to the error handler contains REFERENCES to any vars that were in the global scope.
        // Modification of these would be really bad, since if control is returned (non-fatal error) we'll have altered the state of things!
        $originalFoo = 'bloopblarp';
        $newFoo = $originalFoo;
        $nestedArray = array(
            'key' => 'xxxxxxxxxx',
        );

        $frame = array(
            "file" => dirname(__FILE__) . "/resources/a.php",
            "line" => 9,
            "args"=> array(
                &$newFoo,
                &$nestedArray,
            ),
            "function" => "a_test",
        );

        $result = Raven_Stacktrace::get_frame_context($frame, 5);

        // Check we haven't modified our vars.
        $this->assertEquals($originalFoo, 'bloopblarp');
        $this->assertEquals($nestedArray['key'], 'xxxxxxxxxx');

        // Check that we did truncate the variable in our output
        $this->assertEquals($result['param1'], 'bloop');
        $this->assertEquals($result['param2']['key'], 'xxxxx');
    }

    public function testDoesFixFrameInfo()
    {
        if (isset($_ENV['HHVM']) and ($_ENV['HHVM'] == 1)) {
            $this->markTestSkipped('HHVM stacktrace behaviour');
            return;
        }

        /**
         * PHP's way of storing backstacks seems bass-ackwards to me
         * 'function' is not the function you're in; it's any function being
         * called, so we have to shift 'function' down by 1. Ugh.
         */
        $stack = raven_test_create_stacktrace();

        $frames = Raven_Stacktrace::get_stack_info($stack, true);
        // just grab the last few frames
        $frames = array_slice($frames, -6);
        $skip_call_user_func_fix = false;
        if (PHP_VERSION_ID >= 70000) {
            $skip_call_user_func_fix = true;
            foreach ($frames as &$frame) {
                if (isset($frame['function']) and ($frame['function'] == 'call_user_func')) {
                    $skip_call_user_func_fix = false;
                    break;
                }
            }
            unset($frame);
        }

        if ($skip_call_user_func_fix) {
            $frame = $frames[3];
            $this->assertEquals('raven_test_create_stacktrace', $frame['function']);
            $frame = $frames[4];
            $this->assertEquals('raven_test_recurse', $frame['function']);
            $frame = $frames[5];
            $this->assertEquals('raven_test_recurse', $frame['function']);
        } else {
            $frame = $frames[0];
            $this->assertEquals('raven_test_create_stacktrace', $frame['function']);
            $frame = $frames[1];
            $this->assertEquals('raven_test_recurse', $frame['function']);
            $frame = $frames[2];
            $this->assertEquals('call_user_func', $frame['function']);
            $frame = $frames[3];
            $this->assertEquals('raven_test_recurse', $frame['function']);
            $frame = $frames[4];
            $this->assertEquals('call_user_func', $frame['function']);
            $frame = $frames[5];
            $this->assertEquals('raven_test_recurse', $frame['function']);
        }
    }

    public function testInApp()
    {
        $stack = array(
            array(
                "file" => dirname(__FILE__) . "/resources/a.php",
                "line" => 11,
                "function" => "a_test",
            ),
            array(
                "file" => dirname(__FILE__) . "/resources/b.php",
                "line" => 3,
                "function" => "include_once",
            ),
        );

        $frames = Raven_Stacktrace::get_stack_info($stack, true, null, 0, null, dirname(__FILE__));

        $this->assertEquals($frames[0]['in_app'], true);
        $this->assertEquals($frames[1]['in_app'], true);
    }

    public function testInAppWithAnonymous()
    {
        $stack = array(
            array(
                "function" => "[Anonymous function]",
            ),
        );

        $frames = Raven_Stacktrace::get_stack_info($stack, true, null, 0, null, dirname(__FILE__));

        $this->assertEquals($frames[0]['in_app'], false);
    }

    public function testInAppWithEmptyFrame()
    {
        $stack = array(
            array(
                "function" => "{closure}",
            ),
            null
        );

        $frames = Raven_Stacktrace::get_stack_info($stack, true, null, 0, null, dirname(__FILE__));

        $this->assertEquals($frames[0]['in_app'], false);
    }

    public function testInAppWithExclusion()
    {
        $stack = array(
            array(
                "file" => dirname(__FILE__) . '/resources/foo/a.php',
                "line" => 11,
                "function" => "a_test",
            ),
            array(
                "file" => dirname(__FILE__) . '/resources/bar/b.php',
                "line" => 3,
                "function" => "include_once",
            ),
            array(
                "file" => dirname(__FILE__) . '/resources/foo/c.php',
                "line" => 3,
                "function" => "include_once",
            )
        );

        $frames = Raven_Stacktrace::get_stack_info(
            $stack, true, null, 0, null, dirname(__FILE__) . '/',
            array(dirname(__FILE__) . '/resources/bar/', dirname(__FILE__) . '/resources/foo/c.php'));

        // stack gets reversed
        $this->assertEquals($frames[0]['in_app'], false);
        $this->assertEquals($frames[1]['in_app'], false);
        $this->assertEquals($frames[2]['in_app'], true);
    }

    public function testBasePath()
    {
        $stack = array(
            array(
                "file" => dirname(__FILE__) . "/resources/a.php",
                "line" => 11,
                "function" => "a_test",
            ),
        );

        $frames = Raven_Stacktrace::get_stack_info($stack, true, null, 0, array(dirname(__FILE__) . '/'));

        $this->assertEquals($frames[0]['filename'], 'resources/a.php');
    }

    public function testNoBasePath()
    {
        $stack = array(
            array(
                "file" => dirname(__FILE__) . "/resources/a.php",
                "line" => 11,
                "function" => "a_test",
            ),
        );

        $frames = Raven_Stacktrace::get_stack_info($stack);
        $this->assertEquals($frames[0]['filename'], dirname(__FILE__) . '/resources/a.php');
    }

    public function testWithEvaldCode()
    {
        try {
            eval("throw new Exception('foobar');");
        } catch (Exception $ex) {
            $trace = $ex->getTrace();
            $frames = Raven_Stacktrace::get_stack_info($trace);
        }
        /**
         * @var array $frames
         */
        $this->assertEquals($frames[count($frames) -1]['filename'], __FILE__);
    }

    public function testWarningOnMissingFile()
    {
        $old_error_reporting = error_reporting();

        error_reporting(E_ALL);

        $trace = raven_test_create_stacktrace();

        $trace[0]['file'] = 'filedoesnotexists404.php';

        Raven_Stacktrace::get_stack_info($trace);

        $last_error = error_get_last();

        $this->assertNotEquals('SplFileObject::__construct(filedoesnotexists404.php): failed to open stream: No such file or directory', $last_error['message']);

        error_reporting($old_error_reporting);
    }
}
