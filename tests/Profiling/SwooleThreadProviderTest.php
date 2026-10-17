<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Profiling\SwooleThreadProvider;
use Swoole\Coroutine;

final class SwooleThreadProviderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('swoole') && !\extension_loaded('openswoole')) {
            $this->markTestSkipped('The "swoole" or "openswoole" extension is required.');
        }
    }

    public function testOutsideOfACoroutineTheMainThreadIsProvided(): void
    {
        $threadProvider = new SwooleThreadProvider();

        $this->assertSame('0', $threadProvider->getThreadId());
        $this->assertSame('main', $threadProvider->getThreadName());
    }

    public function testEachCoroutineIsProvidedAsItsOwnThread(): void
    {
        $threadProvider = new SwooleThreadProvider();
        $threads = [];

        self::runCoroutine(static function () use ($threadProvider, &$threads): void {
            $threads[] = [$threadProvider->getThreadId(), $threadProvider->getThreadName(), Coroutine::getCid()];

            Coroutine::create(static function () use ($threadProvider, &$threads): void {
                $threads[] = [$threadProvider->getThreadId(), $threadProvider->getThreadName(), Coroutine::getCid()];
            });
        });

        $this->assertCount(2, $threads);
        $this->assertNotSame($threads[0][0], $threads[1][0]);

        foreach ($threads as [$threadId, $threadName, $coroutineId]) {
            $this->assertSame((string) $coroutineId, $threadId);
            $this->assertSame('coroutine ' . $coroutineId, $threadName);
        }
    }

    private static function runCoroutine(callable $callback): void
    {
        // OpenSwoole only provides the static method, Swoole only the function
        if (\function_exists('Swoole\Coroutine\run')) {
            \Swoole\Coroutine\run($callback);
        } else {
            Coroutine::run($callback);
        }
    }
}
