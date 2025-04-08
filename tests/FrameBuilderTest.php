<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Frame;
use Sentry\FrameBuilder;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;

final class FrameBuilderTest extends TestCase
{
    /**
     * @dataProvider buildFromBacktraceFrameDataProvider
     */
    public function testBuildFromBacktraceFrame(Options $options, array $backtraceFrame, Frame $expectedFrame): void
    {
        $frameBuilder = new FrameBuilder($options, new RepresentationSerializer($options));
        $frame = $frameBuilder->buildFromBacktraceFrame($backtraceFrame['file'], $backtraceFrame['line'], $backtraceFrame);

        $this->assertSame($expectedFrame->getFunctionName(), $frame->getFunctionName());
        $this->assertSame($expectedFrame->getRawFunctionName(), $frame->getRawFunctionName());
        $this->assertSame($expectedFrame->getFile(), $frame->getFile());
        $this->assertSame($expectedFrame->getLine(), $frame->getLine());
        $this->assertSame($expectedFrame->getAbsoluteFilePath(), $frame->getAbsoluteFilePath());
    }

    public static function buildFromBacktraceFrameDataProvider(): \Generator
    {
        yield [
            new Options([]),
            [
                'file' => '/path/to/file(10) : eval()\'d code',
                'line' => 20,
                'function' => 'test_function',
            ],
            new Frame('test_function', '/path/to/file', 10, null, '/path/to/file'),
        ];

        yield [
            new Options([]),
            [
                'file' => '/path/to/file(10) : runtime-created function',
                'line' => 20,
                'function' => 'test_function',
            ],
            new Frame('test_function', '/path/to/file', 10, null, '/path/to/file'),
        ];

        yield [
            new Options([]),
            [
                'file' => '/path/to/file',
                'line' => 10,
                'function' => 'test_function',
                'class' => 'TestClass',
            ],
            new Frame('TestClass::test_function', '/path/to/file', 10, 'TestClass::test_function', '/path/to/file'),
        ];

        yield [
            new Options([]),
            [
                'file' => '/path/to/file',
                'line' => 10,
                'function' => 'test_function',
            ],
            new Frame('test_function', '/path/to/file', 10, null, '/path/to/file'),
        ];

        yield [
            new Options([]),
            [
                'file' => '/path/to/file',
                'line' => 10,
                'function' => 'test_function',
                'class' => "class@anonymous\0/path/to/file",
            ],
            new Frame("class@anonymous\0/path/to/file::test_function", '/path/to/file', 10, "class@anonymous\0/path/to/file::test_function", '/path/to/file'),
        ];

        yield [
            new Options([
                'prefixes' => ['/path/to'],
            ]),
            [
                'file' => '/path/to/file',
                'line' => 10,
                'function' => 'test_function',
                'class' => "class@anonymous\0/path/to/file",
            ],
            new Frame("class@anonymous\0/file::test_function", '/file', 10, "class@anonymous\0/path/to/file::test_function", '/path/to/file'),
        ];

        yield [
            new Options([
                'prefixes' => [
                    '/path/to',
                    '/path/to/app',
                ],
            ]),
            [
                'file' => '/path/to/app/file',
                'line' => 10,
            ],
            new Frame(null, '/app/file', 10, null, '/path/to/app/file'),
        ];

        yield [
            new Options([
                'prefixes' => [
                    '/path/to',
                    '/path/to/app',
                ],
            ]),
            [
                'file' => '/path/to/file',
                'line' => 10,
            ],
            new Frame(null, '/file', 10, null, '/path/to/file'),
        ];

        yield [
            new Options([
                'prefixes' => [
                    '/path/to',
                    '/path/to/app',
                ],
            ]),
            [
                'file' => 'path/not/of/app/path/to/file',
                'line' => 10,
            ],
            new Frame(null, 'path/not/of/app/path/to/file', 10, null, 'path/not/of/app/path/to/file'),
        ];

        yield [
            new Options([
                'prefixes' => [
                    '/path/to',
                    '/path/to/app',
                ],
            ]),
            [
                'file' => 'path/not/of/app/to/file',
                'line' => 10,
            ],
            new Frame(null, 'path/not/of/app/to/file', 10, null, 'path/not/of/app/to/file'),
        ];

        yield [
            new Options([
                'prefixes' => ['/path/to'],
            ]),
            [
                'file' => '/path/to/file',
                'line' => 10,
                'function' => 'test_function',
                'class' => "App\\ClassName@anonymous\0/path/to/file:85$29e",
            ],
            new Frame("App\\ClassName@anonymous\0/file::test_function", '/file', 10, "App\\ClassName@anonymous\0/path/to/file:85$29e::test_function", '/path/to/file'),
        ];
    }

    /**
     * @dataProvider addFrameSetsInAppFlagCorrectlyDataProvider
     */
    public function testAddFrameSetsInAppFlagCorrectly(Options $options, string $file, bool $expectedResult): void
    {
        $stacktraceBuilder = new FrameBuilder($options, new RepresentationSerializer($options));
        $frame = $stacktraceBuilder->buildFromBacktraceFrame($file, __LINE__, []);

        $this->assertSame($expectedResult, $frame->isInApp());
    }

    public static function addFrameSetsInAppFlagCorrectlyDataProvider(): \Generator
    {
        yield 'No config specified' => [
            new Options([
                'in_app_exclude' => [],
                'in_app_include' => [],
            ]),
            '[internal]',
            false,
        ];

        yield 'in_app_include specified && file path not matching' => [
            new Options([
                'in_app_exclude' => [],
                'in_app_include' => [
                    'path/to/nested/file',
                ],
            ]),
            'path/to/file',
            true,
        ];

        yield 'in_app_include not specified && file path not matching' => [
            new Options([
                'in_app_exclude' => [],
                'in_app_include' => [],
            ]),
            'path/to/file',
            true,
        ];

        yield 'in_app_include specified && file path matching' => [
            new Options([
                'in_app_exclude' => [],
                'in_app_include' => [
                    'path/to/nested/file',
                    'path/to/file',
                ],
            ]),
            'path/to/file',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_include' => [
            new Options([
                'in_app_exclude' => [
                    'path/to/nested/file',
                ],
                'in_app_include' => [
                    'path/to/file',
                ],
            ]),
            'path/to/file',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_exclude' => [
            new Options([
                'in_app_exclude' => [
                    'path/to/nested/file',
                ],
                'in_app_include' => [
                    'path/to/file',
                ],
            ]),
            'path/to/nested/file',
            false,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_include && in_app_include prioritized over in_app_exclude' => [
            new Options([
                'in_app_exclude' => [
                    'path/to/file',
                ],
                'in_app_include' => [
                    'path/to/file/nested',
                ],
            ]),
            'path/to/file/nested',
            true,
        ];

        yield 'in_app_include specified && in_app_exclude specified && file path matching in_app_exclude && in_app_exclude prioritized over in_app_include' => [
            new Options([
                'in_app_exclude' => [
                    'path/to/file',
                ],
                'in_app_include' => [
                    'path/to/file/nested',
                ],
            ]),
            'path/to/file',
            false,
        ];
    }
}
