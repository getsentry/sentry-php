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
     * @dataProvider stackFrameVariablesDataProvider
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $expectedVariables
     */
    public function testStackFrameVariablesFromBacktrace(array $options, array $expectedVariables): void
    {
        $options = new Options($options);
        $stacktraceBuilder = new StacktraceBuilder($options, new RepresentationSerializer($options));
        $backtrace = [
            [
                'file' => __FILE__,
                'line' => 42,
                'class' => self::class,
                'type' => '::',
                'function' => 'functionWithArguments',
                'args' => ['request-123', 'secret', ['api_token' => 'nested-secret', 'name' => 'alice']],
            ],
        ];

        $frames = $stacktraceBuilder->buildFromBacktrace($backtrace, __FILE__, __LINE__)->getFrames();

        $this->assertSame($expectedVariables, $frames[1]->getVars());
    }

    public static function stackFrameVariablesDataProvider(): \Generator
    {
        yield 'legacy behavior is unchanged' => [
            [],
            [
                'requestId' => 'request-123',
                'password' => 'secret',
                'metadata' => ['api_token' => 'nested-secret', 'name' => 'alice'],
            ],
        ];

        yield 'default data collection filters sensitive values' => [
            ['data_collection' => []],
            [
                'requestId' => 'request-123',
                'password' => '[Filtered]',
                'metadata' => ['api_token' => '[Filtered]', 'name' => 'alice'],
            ],
        ];

        yield 'collection can be disabled' => [
            ['data_collection' => ['stack_frame_variables' => false]],
            [],
        ];

        yield 'custom allow list' => [
            ['data_collection' => ['stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['requestId']]]],
            [
                'requestId' => 'request-123',
                'password' => '[Filtered]',
                'metadata' => '[Filtered]',
            ],
        ];

        yield 'custom deny list' => [
            ['data_collection' => ['stack_frame_variables' => ['mode' => 'denyList', 'terms' => ['requestId']]]],
            [
                'requestId' => '[Filtered]',
                'password' => '[Filtered]',
                'metadata' => ['api_token' => '[Filtered]', 'name' => 'alice'],
            ],
        ];
    }

    /**
     * @param array<string, string> $metadata
     */
    private static function functionWithArguments(string $requestId, string $password, array $metadata): void
    {
    }
}
