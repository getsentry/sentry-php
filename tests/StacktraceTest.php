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
use Sentry\Frame;
use Sentry\Options;
use Sentry\ReprSerializer;
use Sentry\Serializer;
use Sentry\Stacktrace;

class StacktraceTest extends TestCase
{
    /**
     * @var Options
     */
    protected $options;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var ReprSerializer
     */
    protected $representationSerializer;

    protected function setUp()
    {
        $this->options = new Options();
        $this->serializer = new Serializer($this->options->getMbDetectOrder());
        $this->representationSerializer = new ReprSerializer($this->options->getMbDetectOrder());
    }

    public function testGetFramesAndToArray()
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/file', 1, ['file' => 'path/to/file', 'line' => 1, 'function' => 'test_function']);
        $stacktrace->addFrame('path/to/file', 2, ['file' => 'path/to/file', 'line' => 2, 'function' => 'test_function', 'class' => 'TestClass']);

        $frames = $stacktrace->getFrames();

        $this->assertCount(2, $frames);
        $this->assertEquals($frames, $stacktrace->toArray());
        $this->assertFrameEquals($frames[0], 'TestClass::test_function', 'path/to/file', 2);
        $this->assertFrameEquals($frames[1], 'test_function', 'path/to/file', 1);
    }

    public function testStacktraceJsonSerialization()
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/file', 1, ['file' => 'path/to/file', 'line' => 1, 'function' => 'test_function']);
        $stacktrace->addFrame('path/to/file', 2, ['file' => 'path/to/file', 'line' => 2, 'function' => 'test_function', 'class' => 'TestClass']);

        $frames = json_encode($stacktrace->getFrames());
        $serializedStacktrace = json_encode($stacktrace);

        $this->assertNotFalse($frames);
        $this->assertNotFalse($serializedStacktrace);
        $this->assertJsonStringEqualsJsonString($frames, $serializedStacktrace);
    }

    public function testAddFrame()
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);
        $frames = [
            $this->getJsonFixture('frames/eval.json'),
            $this->getJsonFixture('frames/runtime_created.json'),
            $this->getJsonFixture('frames/function.json'),
        ];

        foreach ($frames as $frame) {
            $stacktrace->addFrame($frame['file'], $frame['line'], $frame);
        }

        $frames = $stacktrace->getFrames();

        $this->assertCount(3, $frames);
        $this->assertFrameEquals($frames[0], 'TestClass::test_function', 'path/to/file', 12);
        $this->assertFrameEquals($frames[1], 'test_function', 'path/to/file', 12);
        $this->assertFrameEquals($frames[2], 'test_function', 'path/to/file', 12);
    }

    public function testAddFrameSerializesMethodArguments()
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);
        $stacktrace->addFrame('path/to/file', 12, [
            'file' => 'path/to/file',
            'line' => 12,
            'function' => 'test_function',
            'args' => [1, 'foo'],
        ]);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertFrameEquals($frames[0], 'test_function', 'path/to/file', 12);
        $this->assertEquals(['param1' => 1, 'param2' => 'foo'], $frames[0]->getVars());
    }

    public function testAddFrameStripsPath()
    {
        $this->options->setPrefixes(['path/to/', 'path/to/app']);

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/app/file', 12, ['function' => 'test_function_parent_parent_parent']);
        $stacktrace->addFrame('path/to/file', 12, ['function' => 'test_function_parent_parent']);
        $stacktrace->addFrame('path/not/of/app/path/to/file', 12, ['function' => 'test_function_parent']);
        $stacktrace->addFrame('path/not/of/app/to/file', 12, ['function' => 'test_function']);

        $frames = $stacktrace->getFrames();

        $this->assertFrameEquals($frames[0], 'test_function', 'path/not/of/app/to/file', 12);
        $this->assertFrameEquals($frames[1], 'test_function_parent', 'path/not/of/app/path/to/file', 12);
        $this->assertFrameEquals($frames[2], 'test_function_parent_parent', 'file', 12);
        $this->assertFrameEquals($frames[3], 'test_function_parent_parent_parent', 'app/file', 12);
    }

    public function testAddFrameMarksAsInApp()
    {
        $this->options->setProjectRoot('path/to');
        $this->options->setExcludedProjectPaths(['path/to/excluded/path']);

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/file', 12, ['function' => 'test_function']);
        $stacktrace->addFrame('path/to/excluded/path/to/file', 12, ['function' => 'test_function']);

        $frames = $stacktrace->getFrames();

        $this->assertTrue($frames[0]->isInApp());
        $this->assertFalse($frames[1]->isInApp());
    }

    public function testAddFrameReadsCodeFromShortFile()
    {
        $fileContent = explode("\n", $this->getFixture('code/ShortFile.php'));
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame($this->getFixturePath('code/ShortFile.php'), 3, ['function' => '[unknown]']);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertCount(2, $frames[0]->getPreContext());
        $this->assertCount(2, $frames[0]->getPostContext());

        for ($i = 0; $i < 2; ++$i) {
            $this->assertEquals(rtrim($fileContent[$i]), $frames[0]->getPreContext()[$i]);
        }

        $this->assertEquals(rtrim($fileContent[2]), $frames[0]->getContextLine());

        for ($i = 0; $i < 2; ++$i) {
            $this->assertEquals(rtrim($fileContent[$i + 3]), $frames[0]->getPostContext()[$i]);
        }
    }

    public function testAddFrameReadsCodeFromLongFile()
    {
        $fileContent = explode("\n", $this->getFixture('code/LongFile.php'));
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame($this->getFixturePath('code/LongFile.php'), 8, [
            'function' => '[unknown]',
        ]);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertCount(5, $frames[0]->getPreContext());
        $this->assertCount(5, $frames[0]->getPostContext());

        for ($i = 0; $i < 5; ++$i) {
            $this->assertEquals(rtrim($fileContent[$i + 2]), $frames[0]->getPreContext()[$i]);
        }

        $this->assertEquals(rtrim($fileContent[7]), $frames[0]->getContextLine());

        for ($i = 0; $i < 5; ++$i) {
            $this->assertEquals(rtrim($fileContent[$i + 8]), $frames[0]->getPostContext()[$i]);
        }
    }

    /**
     * @dataProvider removeFrameDataProvider
     */
    public function testRemoveFrame($index, $throwException)
    {
        if ($throwException) {
            $this->expectException(\OutOfBoundsException::class);
            $this->expectExceptionMessage('Invalid frame index to remove.');
        }

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/file', 12, [
            'function' => 'test_function_parent',
        ]);

        $stacktrace->addFrame('path/to/file', 12, [
            'function' => 'test_function',
        ]);

        $this->assertCount(2, $stacktrace->getFrames());

        $stacktrace->removeFrame($index);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertFrameEquals($frames[0], 'test_function_parent', 'path/to/file', 12);
    }

    public function removeFrameDataProvider()
    {
        return [
            [-1, true],
            [2, true],
            [0, false],
        ];
    }

    public function testFromBacktrace()
    {
        $fixture = $this->getJsonFixture('backtraces/exception.json');
        $frames = Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, $fixture['backtrace'], $fixture['file'], $fixture['line'])->getFrames();

        $this->assertFrameEquals($frames[0], null, 'path/to/file', 16);
        $this->assertFrameEquals($frames[1], 'TestClass::crashyFunction', 'path/to/file', 7);
        $this->assertFrameEquals($frames[2], 'TestClass::triggerError', 'path/to/file', 12);
    }

    public function testFromBacktraceWithAnonymousFrame()
    {
        $fixture = $this->getJsonFixture('backtraces/anonymous_frame.json');
        $frames = Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, $fixture['backtrace'], $fixture['file'], $fixture['line'])->getFrames();

        $this->assertFrameEquals($frames[0], null, 'path/to/file', 7);
        $this->assertFrameEquals($frames[1], 'call_user_func', '[internal]', 0);
        $this->assertFrameEquals($frames[2], 'TestClass::triggerError', 'path/to/file', 12);
    }

    public function testInAppWithEmptyFrame()
    {
        $stack = [
            [
                'function' => '{closure}',
            ],
            null,
        ];

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);
        $stacktrace->addFrame('/some/file', 123, $stack);
        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertContainsOnlyInstancesOf(Frame::class, $frames);
        $this->assertFalse($frames[0]->isInApp());
    }

    public function testGetFrameArgumentsDoesNotModifyCapturedArgs()
    {
        // PHP's errcontext as passed to the error handler contains REFERENCES to any vars that were in the global scope.
        // Modification of these would be really bad, since if control is returned (non-fatal error) we'll have altered the state of things!
        $originalFoo = 'bloopblarp';
        $newFoo = $originalFoo;
        $nestedArray = [
            'key' => 'xxxxxxxxxx',
        ];

        $frame = [
            'file' => __DIR__ . '/resources/a.php',
            'line' => 9,
            'args' => [
                &$newFoo,
                &$nestedArray,
            ],
            'function' => 'a_test',
        ];

        $result = Stacktrace::getFrameArguments($frame, 5);

        // Check we haven't modified our vars.
        $this->assertEquals($originalFoo, 'bloopblarp');
        $this->assertEquals($nestedArray['key'], 'xxxxxxxxxx');

        // Check that we did truncate the variable in our output
        $this->assertEquals($result['param1'], 'bloop');
        $this->assertEquals($result['param2']['key'], 'xxxxx');
    }

    protected function getFixturePath($file)
    {
        return realpath(__DIR__ . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR . $file);
    }

    protected function getFixture($file)
    {
        return file_get_contents($this->getFixturePath($file));
    }

    protected function getJsonFixture($file)
    {
        return json_decode($this->getFixture($file), true);
    }

    protected function assertFrameEquals(Frame $frame, $method, $file, $line)
    {
        $this->assertSame($method, $frame->getFunctionName());
        $this->assertSame($file, $frame->getFile());
        $this->assertSame($line, $frame->getLine());
    }
}
