<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Frame;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\Serializer;
use Sentry\Stacktrace;

final class StacktraceTest extends TestCase
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var RepresentationSerializer
     */
    private $representationSerializer;

    protected function setUp(): void
    {
        $this->options = new Options();
        $this->serializer = new Serializer($this->options);
        $this->representationSerializer = new RepresentationSerializer($this->options);
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testConstructorThrowsDeprecationErrorIfLastArgumentIsNotSetToFalse(): void
    {
        new Stacktrace($this->options, $this->serializer, $this->representationSerializer);
    }

    public function testGetFramesAndToArray(): void
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);

        $stacktrace->addFrame('path/to/file', 1, ['file' => 'path/to/file', 'line' => 1, 'class' => 'TestClass']);
        $stacktrace->addFrame('path/to/file', 2, ['file' => 'path/to/file', 'line' => 2, 'function' => 'test_function']);
        $stacktrace->addFrame('path/to/file', 3, ['file' => 'path/to/file', 'line' => 3, 'function' => 'test_function', 'class' => 'TestClass']);

        $frames = $stacktrace->getFrames();

        $this->assertCount(3, $frames);
        $this->assertEquals($frames, $stacktrace->toArray());
        $this->assertFrameEquals($frames[0], 'TestClass::test_function', 'path/to/file', 3);
        $this->assertFrameEquals($frames[1], 'test_function', 'path/to/file', 2);
        $this->assertFrameEquals($frames[2], null, 'path/to/file', 1);
    }

    public function testStacktraceJsonSerialization(): void
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);

        $stacktrace->addFrame('path/to/file', 1, ['file' => 'path/to/file', 'line' => 1, 'function' => 'test_function']);
        $stacktrace->addFrame('path/to/file', 2, ['file' => 'path/to/file', 'line' => 2, 'function' => 'test_function', 'class' => 'TestClass']);
        $stacktrace->addFrame('path/to/file', 3, ['file' => 'path/to/file', 'line' => 3, 'class' => 'TestClass']);

        $frames = json_encode($stacktrace->getFrames());
        $serializedStacktrace = json_encode($stacktrace);

        $this->assertNotFalse($frames);
        $this->assertNotFalse($serializedStacktrace);
        $this->assertJsonStringEqualsJsonString($frames, $serializedStacktrace);
    }

    public function testAddFrame(): void
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);
        $frames = [
            $this->getJsonFixture('frames/eval.json'),
            $this->getJsonFixture('frames/runtime_created.json'),
            $this->getJsonFixture('frames/function.json'),
            $this->getJsonFixture('frames/missing_function_key.json'),
        ];

        foreach ($frames as $frame) {
            $stacktrace->addFrame($frame['file'], $frame['line'], $frame);
        }

        $frames = $stacktrace->getFrames();

        $this->assertCount(4, $frames);
        $this->assertFrameEquals($frames[0], null, 'path/to/file', 12);
        $this->assertFrameEquals($frames[1], 'TestClass::test_function', 'path/to/file', 12);
        $this->assertFrameEquals($frames[2], 'test_function', 'path/to/file', 12);
        $this->assertFrameEquals($frames[3], 'test_function', 'path/to/file', 12);
    }

    public function testAddFrameSerializesMethodArguments(): void
    {
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);
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

    public function testAddFrameStripsPath(): void
    {
        $this->options->setPrefixes(['path/to/', 'path/to/app']);

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);

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

    /**
     * @group legacy
     *
     * @dataProvider addFrameSetsInAppFlagCorrectlyDataProvider
     */
    public function testAddFrameSetsInAppFlagCorrectly(array $options, string $file, string $functionName, bool $expectedResult): void
    {
        $options = new Options($options);
        $stacktrace = new Stacktrace(
            $options,
            new Serializer($options),
            new RepresentationSerializer($options),
            false
        );

        $stacktrace->addFrame($file, 0, ['function' => $functionName]);

        $this->assertSame($expectedResult, $stacktrace->getFrame(0)->isInApp());
    }

    public function addFrameSetsInAppFlagCorrectlyDataProvider(): \Generator
    {
        yield 'No config specified' => [
            [
                'project_root' => null,
                'in_app_exclude' => [],
                'in_app_include' => [],
            ],
            '[internal]',
            'test_function',
            false,
        ];

        yield 'project_root specified && file path matching project_root' => [
            [
                'project_root' => 'path/to',
                'in_app_exclude' => [],
                'in_app_include' => [],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'project_root specified && file path matching project_root && file path matching in_app_exclude' => [
            [
                'project_root' => 'path/to/file',
                'in_app_exclude' => [
                    'path/to',
                ],
                'in_app_include' => [],
            ],
            'path/to/file',
            'test_function',
            false,
        ];

        yield 'project_root specified && file path not maching project_root && file path matching in_app_include' => [
            [
                'project_root' => 'nested/path/to',
                'in_app_exclude' => [],
                'in_app_include' => [
                    'path/to',
                ],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'in_app_include specified && file path not matching' => [
            [
                'project_root' => null,
                'in_app_exclude' => [],
                'in_app_include' => [
                    'path/to/nested/file',
                ],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'in_app_include not specified && file path not matching' => [
            [
                'project_root' => null,
                'in_app_exclude' => [],
                'in_app_include' => [],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'in_app_include specified && file path matching' => [
            [
                'project_root' => null,
                'in_app_exclude' => [],
                'in_app_include' => [
                    'path/to/nested/file',
                    'path/to/file',
                ],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_include' => [
            [
                'project_root' => null,
                'in_app_exclude' => [
                    'path/to/nested/file',
                ],
                'in_app_include' => [
                    'path/to/file',
                ],
            ],
            'path/to/file',
            'test_function',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_exclude' => [
            [
                'project_root' => null,
                'in_app_exclude' => [
                    'path/to/nested/file',
                ],
                'in_app_include' => [
                    'path/to/file',
                ],
            ],
            'path/to/nested/file',
            'test_function',
            false,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_include && in_app_include prioritized over in_app_exclude' => [
            [
                'project_root' => null,
                'in_app_exclude' => [
                    'path/to/file',
                ],
                'in_app_include' => [
                    'path/to/file/nested',
                ],
            ],
            'path/to/file/nested',
            'test_function',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_exclude && in_app_exclude prioritized over in_app_include' => [
            [
                'project_root' => null,
                'in_app_exclude' => [
                    'path/to/file',
                ],
                'in_app_include' => [
                    'path/to/file/nested',
                ],
            ],
            'path/to/file',
            'test_function',
            false,
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider addFrameRespectsContextLinesOptionDataProvider
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testAddFrameRespectsContextLinesOption(string $fixture, int $lineNumber, ?int $contextLines, int $preContextCount, int $postContextCount): void
    {
        $this->options->setContextLines($contextLines);

        $fileContent = explode("\n", $this->getFixture($fixture));
        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame($this->getFixturePath($fixture), $lineNumber, ['function' => '[unknown]']);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertCount($preContextCount, $frames[0]->getPreContext());
        $this->assertCount($postContextCount, $frames[0]->getPostContext());

        for ($i = 0; $i < $preContextCount; ++$i) {
            $this->assertSame(rtrim($fileContent[$i + ($lineNumber - $preContextCount - 1)]), $frames[0]->getPreContext()[$i]);
        }

        if (null === $contextLines) {
            $this->assertNull($frames[0]->getContextLine());
        } else {
            $this->assertSame(rtrim($fileContent[$lineNumber - 1]), $frames[0]->getContextLine());
        }

        for ($i = 0; $i < $postContextCount; ++$i) {
            $this->assertSame(rtrim($fileContent[$i + $lineNumber]), $frames[0]->getPostContext()[$i]);
        }
    }

    public function addFrameRespectsContextLinesOptionDataProvider(): \Generator
    {
        yield 'skip reading code when context_lines option == 0' => [
            'code/ShortFile.php',
            3,
            0,
            0,
            0,
        ];

        yield 'skip reading pre_context and post_context when context_lines == null' => [
            'code/ShortFile.php',
            3,
            null,
            0,
            0,
        ];

        yield 'read code from short file' => [
            'code/ShortFile.php',
            3,
            2,
            2,
            2,
        ];

        yield 'read code from long file' => [
            'code/LongFile.php',
            8,
            2,
            2,
            2,
        ];

        yield 'read code from long file near end of file' => [
            'code/LongFile.php',
            11,
            5,
            5,
            2,
        ];

        yield 'read code from long file near beginning of file' => [
            'code/LongFile.php',
            3,
            5,
            2,
            5,
        ];
    }

    /**
     * @dataProvider removeFrameDataProvider
     */
    public function testRemoveFrame(int $index, bool $throwException): void
    {
        if ($throwException) {
            $this->expectException(\OutOfBoundsException::class);
            $this->expectExceptionMessage('Invalid frame index to remove.');
        }

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);
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

    public function removeFrameDataProvider(): array
    {
        return [
            [-1, true],
            [2, true],
            [0, false],
        ];
    }

    public function testCreateFromBacktrace(): void
    {
        $fixture = $this->getJsonFixture('backtraces/exception.json');
        $frames = Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, $fixture['backtrace'], $fixture['file'], $fixture['line'], false)->getFrames();

        $this->assertFrameEquals($frames[0], null, 'path/to/file', 16);
        $this->assertFrameEquals($frames[1], 'TestClass::crashyFunction', 'path/to/file', 7);
        $this->assertFrameEquals($frames[2], 'TestClass::triggerError', 'path/to/file', 12);
    }

    public function testCreateFromBacktraceWithAnonymousFrame(): void
    {
        $fixture = $this->getJsonFixture('backtraces/anonymous_frame.json');
        $frames = Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, $fixture['backtrace'], $fixture['file'], $fixture['line'], false)->getFrames();

        $this->assertFrameEquals($frames[0], null, 'path/to/file', 7);
        $this->assertFrameEquals($frames[1], 'call_user_func', '[internal]', 0);
        $this->assertFrameEquals($frames[2], 'TestClass::triggerError', 'path/to/file', 12);
    }

    public function testCreateFromBacktraceWithAnonymousClass(): void
    {
        $this->options->setPrefixes(['/path-prefix']);

        $fixture = $this->getJsonFixture('backtraces/anonymous_frame_with_memory_address.json');
        $frames = Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, $fixture['backtrace'], $fixture['file'], $fixture['line'], false)->getFrames();

        $this->assertFrameEquals(
            $frames[0],
            null,
            '[internal]',
            0
        );

        $this->assertFrameEquals(
            $frames[1],
            "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
            '[internal]',
            0
        );

        $this->assertFrameEquals(
            $frames[2],
            "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
            'path/to/file',
            12
        );
    }

    /**
     * @group legacy
     *
     * @dataProvider createFromBacktraceThrowsDeprecationErrorIfLastArgumentIsNotSetToFalseDataProvider
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testCreateFromBacktraceThrowsDeprecationErrorIfLastArgumentIsNotSetToFalse(array ...$constructorArguments): void
    {
        Stacktrace::createFromBacktrace($this->options, $this->serializer, $this->representationSerializer, [], __FILE__, __LINE__, ...$constructorArguments);
    }

    public function createFromBacktraceThrowsDeprecationErrorIfLastArgumentIsNotSetToFalseDataProvider(): \Generator
    {
        yield [[true]];

        yield [[1]];

        yield [['foo']];

        yield [[new class() {
        }]];

        yield [[]];
    }

    public function testGetFrameArgumentsDoesNotModifyCapturedArgs(): void
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

        $stacktrace = new Stacktrace(new Options(['max_value_length' => 5]), $this->serializer, $this->representationSerializer, false);
        $result = $stacktrace->getFrameArguments($frame);

        // Check we haven't modified our vars.
        $this->assertEquals($originalFoo, 'bloopblarp');
        $this->assertEquals($nestedArray['key'], 'xxxxxxxxxx');

        // Check that we did truncate the variable in our output
        $this->assertEquals($result['param1'], 'bloop');
        $this->assertEquals($result['param2']['key'], 'xxxxx');
    }

    public function testPreserveXdebugFrameArgumentNames(): void
    {
        $frame = [
            'file' => __DIR__ . '/resources/a.php',
            'line' => 9,
            'args' => [
                'foo' => 'bar',
                'alice' => 'bob',
            ],
            'function' => 'a_test',
        ];

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer, false);
        $result = $stacktrace->getFrameArguments($frame);

        $this->assertEquals('bar', $result['foo']);
        $this->assertEquals('bob', $result['alice']);
    }

    private function getFixturePath(string $file): string
    {
        $filePath = realpath(__DIR__ . \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR . $file);

        if (false === $filePath) {
            throw new \RuntimeException(sprintf('The fixture file at path "%s" could not be found.', $file));
        }

        return $filePath;
    }

    private function getFixture(string $file): string
    {
        $fileContent = file_get_contents($this->getFixturePath($file));

        if (false === $fileContent) {
            throw new \RuntimeException(sprintf('The fixture file at path "%s" could not be read.', $file));
        }

        return $fileContent;
    }

    private function getJsonFixture(string $file): array
    {
        $decodedData = json_decode($this->getFixture($file), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(sprintf('Could not decode the fixture file at path "%s". Error was: %s', $this->getFixturePath($file), json_last_error_msg()));
        }

        return $decodedData;
    }

    private function assertFrameEquals(Frame $frame, ?string $method, string $file, int $line): void
    {
        $this->assertSame($method, $frame->getFunctionName());
        $this->assertSame($file, $frame->getFile());
        $this->assertSame($line, $frame->getLine());
    }
}
