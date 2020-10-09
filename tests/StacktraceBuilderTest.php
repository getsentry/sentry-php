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
}
