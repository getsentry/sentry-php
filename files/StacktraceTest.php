<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Frame;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Stacktrace;
use Sentry\StacktraceBuilder;

final class StacktraceTest extends TestCase
{
    public function testConstructorThrowsIfFramesListIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a non empty list of frames.');

        new Stacktrace([]);
    }

    /**
     * @dataProvider constructorThrowsIfFramesListContainsUnexpectedValueDataProvider
     */
    public function testConstructorThrowsIfFramesListContainsUnexpectedValue(array $values, string $expectedExceptionMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessage);

        new Stacktrace($values);
    }

    public static function constructorThrowsIfFramesListContainsUnexpectedValueDataProvider(): \Generator
    {
        yield [
            [
                new Frame(__FUNCTION__, __FILE__, __LINE__),
                10,
            ],
            '/^Expected an instance of the "Sentry\\\\Frame" class\. Got: "int"\.$/',
        ];

        yield [
            [(object) []],
            '/^Expected an instance of the "Sentry\\\\Frame" class\. Got: "stdClass"\.$/',
        ];

        yield [
            [new class {
            }],
            '/^Expected an instance of the "Sentry\\\\Frame" class\. Got: "class@anonymous.*"\.$/',
        ];
    }

    public function testAddFrame(): void
    {
        $stacktrace = new Stacktrace([
            new Frame('function_1', 'path/to/file_1', 10),
        ]);

        $stacktrace->addFrame(new Frame('function_2', 'path/to/file_2', 20));

        $frames = $stacktrace->getFrames();

        $this->assertCount(2, $frames);
        $this->assertFrameEquals($frames[0], 'function_2', 'path/to/file_2', 20);
        $this->assertFrameEquals($frames[1], 'function_1', 'path/to/file_1', 10);
    }

    /**
     * @dataProvider removeFrameDataProvider
     */
    public function testRemoveFrame(int $index, ?string $expectedExceptionMessage): void
    {
        if ($expectedExceptionMessage !== null) {
            $this->expectException(\OutOfBoundsException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $stacktrace = new Stacktrace([
            new Frame('test_function', 'path/to/file', 12),
            new Frame('test_function_parent', 'path/to/file', 12),
        ]);

        $this->assertCount(2, $stacktrace->getFrames());

        $stacktrace->removeFrame($index);

        $frames = $stacktrace->getFrames();

        $this->assertCount(1, $frames);
        $this->assertFrameEquals($frames[0], 'test_function_parent', 'path/to/file', 12);
    }

    public static function removeFrameDataProvider(): \Generator
    {
        yield [
            -1,
            'Cannot remove the frame at index -1.',
        ];

        yield [
            2,
            'Cannot remove the frame at index 2.',
        ];

        yield [
            0,
            null,
        ];
    }

    /**
     * @dataProvider buildFromBacktraceDataProvider
     */
    public function testCreateFromBacktrace(Options $options, array $backtrace, array $expectedFramesData): void
    {
        $stacktraceBuilder = new StacktraceBuilder($options, new RepresentationSerializer($options));
        $stacktrace = $stacktraceBuilder->buildFromBacktrace($backtrace['backtrace'], $backtrace['file'], $backtrace['line']);
        $frames = $stacktrace->getFrames();

        for ($i = 0, $count = \count($frames); $i < $count; ++$i) {
            $this->assertFrameEquals($frames[$i], $expectedFramesData[$i][0], $expectedFramesData[$i][1], $expectedFramesData[$i][2]);
        }
    }

    public static function buildFromBacktraceDataProvider(): \Generator
    {
        yield 'Plain backtrace' => [
            new Options(),
            [
                'file' => 'path/to/file',
                'line' => 12,
                'backtrace' => [
                    [
                        'file' => 'path/to/file',
                        'function' => 'triggerError',
                        'line' => 7,
                        'class' => 'TestClass',
                    ],
                    [
                        'file' => 'path/to/file',
                        'line' => 16,
                        'class' => 'TestClass',
                        'function' => 'crashyFunction',
                    ],
                ],
            ],
            [
                [
                    null,
                    'path/to/file',
                    16,
                ],
                [
                    'TestClass::crashyFunction',
                    'path/to/file',
                    7,
                ],
                [
                    'TestClass::triggerError',
                    'path/to/file',
                    12,
                ],
            ],
        ];

        yield 'Backtrace containing anonymous frame' => [
            new Options(),
            [
                'file' => 'path/to/file',
                'line' => 12,
                'backtrace' => [
                    [
                        'function' => 'triggerError',
                        'class' => 'TestClass',
                    ],
                    [
                        'file' => 'path/to/file',
                        'line' => 7,
                        'function' => 'call_user_func',
                    ],
                ],
            ],
            [
                [
                    null,
                    'path/to/file',
                    7,
                ],
                [
                    'call_user_func',
                    Frame::INTERNAL_FRAME_FILENAME,
                    0,
                ],
                [
                    'TestClass::triggerError',
                    'path/to/file',
                    12,
                ],
            ],
        ];

        yield 'Backtrace with frame containing memory address in PHP <7.4.2 format' => [
            new Options([
                'prefixes' => ['/path-prefix'],
            ]),
            [
                'file' => 'path/to/file',
                'line' => 12,
                'backtrace' => [
                    [
                        'class' => "class@anonymous\x00/path/to/app/consumer.php0x7fc3bc369418",
                        'function' => 'messageCallback',
                        'type' => '->',
                    ],
                    [
                        'class' => "class@anonymous\x00/path-prefix/path/to/app/consumer.php0x7fc3bc369418",
                        'function' => 'messageCallback',
                        'type' => '->',
                    ],
                ],
            ],
            [
                [
                    null,
                    Frame::INTERNAL_FRAME_FILENAME,
                    0,
                ],
                [
                    "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
                    Frame::INTERNAL_FRAME_FILENAME,
                    0,
                ],
                [
                    "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
                    'path/to/file',
                    12,
                ],
            ],
        ];

        yield 'Backtrace with frame containing memory address in PHP >=7.4.2 format' => [
            new Options([
                'prefixes' => ['/path-prefix'],
            ]),
            [
                'file' => 'path/to/file',
                'line' => 12,
                'backtrace' => [
                    [
                        'class' => "class@anonymous\x00/path/to/app/consumer.php:12$3e0a7",
                        'function' => 'messageCallback',
                        'type' => '->',
                    ],
                    [
                        'class' => "class@anonymous\x00/path-prefix/path/to/app/consumer.php:12$3e0a7",
                        'function' => 'messageCallback',
                        'type' => '->',
                    ],
                ],
            ],
            [
                [
                    null,
                    Frame::INTERNAL_FRAME_FILENAME,
                    0,
                ],
                [
                    "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
                    Frame::INTERNAL_FRAME_FILENAME,
                    0,
                ],
                [
                    "class@anonymous\x00/path/to/app/consumer.php::messageCallback",
                    'path/to/file',
                    12,
                ],
            ],
        ];
    }

    private function assertFrameEquals(Frame $frame, ?string $method, string $file, int $line): void
    {
        $this->assertSame($method, $frame->getFunctionName());
        $this->assertSame($file, $frame->getFile());
        $this->assertSame($line, $frame->getLine());
    }
}
