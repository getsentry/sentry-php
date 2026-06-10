<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Frame;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Util\CodeLocationResolver;

final class CodeLocationResolverTest extends TestCase
{
    public function testFindFirstInAppFrameForBacktrace(): void
    {
        $expectedLine = 123;
        $resolver = $this->createResolver([
            'prefixes' => [],
        ]);

        $frame = $resolver->findFirstInAppFrameForBacktrace($this->createQueryBacktrace($expectedLine));

        $this->assertNotNull($frame);
        $this->assertSame(__FILE__, $frame->getFile());
        $this->assertSame($expectedLine, $frame->getLine());
        $this->assertSame('App\\Repository\\UserRepository::findActiveUsers', $frame->getFunctionName());
    }

    public function testResolveFromBacktraceReturnsCodeLocationMetadata(): void
    {
        $expectedLine = 321;
        $resolver = $this->createResolver([
            'prefixes' => [\dirname(__DIR__)],
        ]);

        $location = $resolver->resolveFromBacktrace($this->createQueryBacktrace($expectedLine));

        $this->assertNotNull($location);
        $this->assertSame(\DIRECTORY_SEPARATOR . 'tests' . \DIRECTORY_SEPARATOR . 'CodeLocationResolverTest.php', $location['code.filepath']);
        $this->assertSame('App\\Repository\\UserRepository::findActiveUsers', $location['code.function']);
        $this->assertSame($expectedLine, $location['code.lineno']);
    }

    public function testResolveFromBacktraceReturnsNullWithoutInAppFrame(): void
    {
        $resolver = $this->createResolver();

        $location = $resolver->resolveFromBacktrace([
            [
                'file' => Frame::INTERNAL_FRAME_FILENAME,
                'line' => 0,
                'function' => 'internal',
            ],
            [
                'class' => 'Doctrine\\DBAL\\Connection',
                'function' => 'executeQuery',
            ],
        ]);

        $this->assertNull($location);
    }

    private function createResolver(array $options = []): CodeLocationResolver
    {
        $options = new Options($options);

        return new CodeLocationResolver($options, new RepresentationSerializer($options));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function createQueryBacktrace(int $line): array
    {
        return [
            [
                'file' => Frame::INTERNAL_FRAME_FILENAME,
                'line' => 0,
                'function' => 'internal',
            ],
            [
                'file' => __FILE__,
                'line' => $line,
                'class' => 'Doctrine\\DBAL\\Connection',
                'function' => 'executeQuery',
            ],
            [
                'class' => 'App\\Repository\\UserRepository',
                'function' => 'findActiveUsers',
            ],
        ];
    }
}
