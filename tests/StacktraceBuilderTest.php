<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\StacktraceBuilder;

final class StacktraceBuilderTest extends TestCase
{
    public function testBuildFromBacktrace(): void
    {
        $options = new Options(['default_integrations' => false]);
        $representationSerializer = new RepresentationSerializer($options);
        $stacktraceBuilder = new StacktraceBuilder($options, $representationSerializer);
        $backtrace = [
            [
                'file' => '/in/jXVmi',
                'line' => 9,
                'function' => 'main',
                'args' => [],
            ],
            [
                'file' => '/in/jXVmi',
                'line' => 5,
                'function' => '{closure}',
                'args' => [],
            ],
        ];

        $stacktrace = $stacktraceBuilder->buildFromBacktrace($backtrace, __FILE__, $expectedLine = __LINE__);
        $frames = $stacktrace->getFrames();

        $this->assertCount(3, $frames);

        $this->assertNull($frames[0]->getFunctionName());
        $this->assertSame('/in/jXVmi', $frames[0]->getFile());
        $this->assertSame('/in/jXVmi', $frames[0]->getAbsoluteFilePath());
        $this->assertSame(5, $frames[0]->getLine());

        $this->assertSame('{closure}', $frames[1]->getFunctionName());
        $this->assertSame('/in/jXVmi', $frames[1]->getFile());
        $this->assertSame('/in/jXVmi', $frames[1]->getAbsoluteFilePath());
        $this->assertSame(9, $frames[1]->getLine());

        $this->assertSame('main', $frames[2]->getFunctionName());
        $this->assertSame(__FILE__, $frames[2]->getAbsoluteFilePath());
        $this->assertSame($expectedLine, $frames[2]->getLine());
    }

    /**
     * @dataProvider realExceptionStackFrameVariablesDataProvider
     *
     * @param array<string, mixed>                $options
     * @param array<string, array<string, mixed>> $expectedVariables
     */
    public function testStackFrameVariablesFromRealException(array $options, array $expectedVariables): void
    {
        $previousIgnoreArgs = \ini_get('zend.exception_ignore_args');

        try {
            if ($previousIgnoreArgs !== false
                && (ini_set('zend.exception_ignore_args', '0') === false
                    || \ini_get('zend.exception_ignore_args') !== '0')) {
                $this->markTestSkipped('zend.exception_ignore_args cannot be disabled.');
            }

            $exception = self::createNestedException();
            $sdkOptions = new Options($options);
            $stacktraceBuilder = new StacktraceBuilder(
                $sdkOptions,
                new RepresentationSerializer($sdkOptions)
            );
            $frames = $stacktraceBuilder->buildFromException($exception)->getFrames();
            $actualVariables = [];

            foreach ($frames as $frame) {
                $rawFunctionName = $frame->getRawFunctionName();

                if ($rawFunctionName === null) {
                    continue;
                }

                $separatorPosition = strrpos($rawFunctionName, '::');
                $methodName = $separatorPosition === false
                    ? $rawFunctionName
                    : substr($rawFunctionName, $separatorPosition + 2);

                if (\array_key_exists($methodName, $expectedVariables)) {
                    $actualVariables[$methodName] = $frame->getVars();
                }
            }

            ksort($actualVariables);
            ksort($expectedVariables);

            $this->assertSame($expectedVariables, $actualVariables);
        } finally {
            if ($previousIgnoreArgs !== false) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    public static function realExceptionStackFrameVariablesDataProvider(): \Generator
    {
        yield 'legacy behavior is unchanged' => [
            [],
            [
                'stackFrameInner' => [
                    'apiToken' => 'nested-secret',
                    'safeValue' => 'safe',
                ],
                'stackFrameMiddle' => [
                    'metadata' => [
                        'api_token' => 'nested-secret',
                        'name' => 'alice',
                    ],
                ],
                'stackFrameOuter' => [
                    'requestId' => 'request-123',
                    'password' => 'secret',
                ],
            ],
        ];

        yield 'default data collection filters mandatory sensitive values' => [
            ['data_collection' => []],
            [
                'stackFrameInner' => [
                    'apiToken' => '[Filtered]',
                    'safeValue' => 'safe',
                ],
                'stackFrameMiddle' => [
                    'metadata' => [
                        'api_token' => '[Filtered]',
                        'name' => 'alice',
                    ],
                ],
                'stackFrameOuter' => [
                    'requestId' => 'request-123',
                    'password' => '[Filtered]',
                ],
            ],
        ];

        yield 'collection can be disabled with boolean shorthand' => [
            ['data_collection' => ['stack_frame_variables' => false]],
            [
                'stackFrameInner' => [],
                'stackFrameMiddle' => [],
                'stackFrameOuter' => [],
            ],
        ];

        yield 'allow list filters values not matching configured terms' => [
            [
                'data_collection' => [
                    'stack_frame_variables' => [
                        'mode' => 'allowList',
                        'terms' => ['request'],
                    ],
                ],
            ],
            [
                'stackFrameInner' => [
                    'apiToken' => '[Filtered]',
                    'safeValue' => '[Filtered]',
                ],
                'stackFrameMiddle' => [
                    'metadata' => '[Filtered]',
                ],
                'stackFrameOuter' => [
                    'requestId' => 'request-123',
                    'password' => '[Filtered]',
                ],
            ],
        ];

        yield 'deny list combines mandatory and custom terms' => [
            [
                'data_collection' => [
                    'stack_frame_variables' => [
                        'mode' => 'denyList',
                        'terms' => ['request'],
                    ],
                ],
            ],
            [
                'stackFrameInner' => [
                    'apiToken' => '[Filtered]',
                    'safeValue' => 'safe',
                ],
                'stackFrameMiddle' => [
                    'metadata' => [
                        'api_token' => '[Filtered]',
                        'name' => 'alice',
                    ],
                ],
                'stackFrameOuter' => [
                    'requestId' => '[Filtered]',
                    'password' => '[Filtered]',
                ],
            ],
        ];
    }

    private static function createNestedException(): \RuntimeException
    {
        try {
            self::stackFrameOuter('request-123', 'secret');
        } catch (\RuntimeException $exception) {
            return $exception;
        }

        throw new \LogicException('Expected the nested stack frame fixture to throw.');
    }

    private static function stackFrameOuter(string $requestId, string $password): void
    {
        self::stackFrameMiddle([
            'api_token' => 'nested-secret',
            'name' => 'alice',
        ]);
    }

    /**
     * @param array<string, string> $metadata
     */
    private static function stackFrameMiddle(array $metadata): void
    {
        self::stackFrameInner($metadata['api_token'], 'safe');
    }

    private static function stackFrameInner(string $apiToken, string $safeValue): void
    {
        throw new \RuntimeException('Real nested stack frame fixture.');
    }
}
