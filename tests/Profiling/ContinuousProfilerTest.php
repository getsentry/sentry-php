<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Profiling\ContinuousProfiler;
use Sentry\Profiling\MainThreadProvider;
use Sentry\Profiling\SwooleThreadProvider;
use Sentry\Profiling\ThreadProviderInterface;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tests\StubTransport;

final class ContinuousProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        StubTransport::$events = [];
    }

    /**
     * @requires extension excimer
     */
    public function testStartAndStopCapturesProfileChunk(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $startTimestamp = microtime(true);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        $this->assertTrue($profiler->isRunning());

        $profilerId = $profiler->getProfilerId();

        $this->assertNotNull($profilerId);
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', $profilerId));

        self::busyWait(0.1);

        $profiler->stop();

        $endTimestamp = microtime(true);

        $this->assertFalse($profiler->isRunning());
        $this->assertNull($profiler->getProfilerId());
        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];

        $this->assertSame(EventType::profileChunk(), $event->getType());

        $profileChunk = $event->getProfileChunk();

        $this->assertNotNull($profileChunk);
        $this->assertSame($profilerId, $profileChunk->getProfilerId());

        $payload = $profileChunk->getFormattedData($event);

        $this->assertNotNull($payload);
        $this->assertGreaterThanOrEqual(2, \count($payload['profile']['samples']));

        // Excimer timestamps samples with its own clock, so they are only roughly within the profiled time
        foreach ($payload['profile']['samples'] as $sample) {
            $this->assertGreaterThan($startTimestamp - 0.1, $sample['timestamp']);
            $this->assertLessThan($endTimestamp + 0.1, $sample['timestamp']);
        }
    }

    /**
     * @requires extension excimer
     */
    public function testStopDoesNotPassProfileChunkToEventProcessors(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $eventProcessorCalled = false;

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$eventProcessorCalled): void {
            $scope->addEventProcessor(static function (Event $event) use (&$eventProcessorCalled): Event {
                $eventProcessorCalled = true;

                return $event;
            });
        });

        $profiler = new ContinuousProfiler();
        $profiler->start();
        self::busyWait(0.05);
        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);
        $this->assertFalse($eventProcessorCalled);
    }

    /**
     * @requires extension excimer
     */
    public function testStopDiscardsProfileChunkWithTooFewSamples(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();
        $profiler->stop();

        $this->assertFalse($profiler->isRunning());
        $this->assertSame([], StubTransport::$events);
    }

    /**
     * @requires extension excimer
     */
    public function testStartWhileRunningKeepsCollectedSamples(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        $profilerId = $profiler->getProfilerId();

        self::busyWait(0.05);
        $secondStartTimestamp = microtime(true);

        $profiler->start();

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $profileChunk = $event->getProfileChunk();

        $this->assertNotNull($profileChunk);
        $this->assertSame($profilerId, $profileChunk->getProfilerId());

        $payload = $profileChunk->getFormattedData($event);

        $this->assertNotNull($payload);
        $this->assertLessThan($secondStartTimestamp, $payload['profile']['samples'][0]['timestamp']);
    }

    /**
     * @requires extension excimer
     */
    public function testEachStartBeginsANewProfilerSession(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        $firstProfilerId = $profiler->getProfilerId();

        $profiler->stop();
        $profiler->start();

        $this->assertNotNull($profiler->getProfilerId());
        $this->assertNotSame($firstProfilerId, $profiler->getProfilerId());

        $profiler->stop();
    }

    /**
     * @requires extension excimer
     */
    public function testSessionSamplingIsDecidedOnce(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();
        $profiler->stop();

        $this->bindClient(['profile_session_sample_rate' => 0.0]);

        $profiler->start();

        $this->assertTrue($profiler->isRunning());

        $profiler->stop();
    }

    /**
     * @requires extension excimer
     */
    public function testRunningProfilerSendsChunksWhenEnoughSamplesWereCollected(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(5);
        $profiler->start();

        $profilerId = $profiler->getProfilerId();

        self::busyWait(0.2);

        $this->assertTrue($profiler->isRunning());
        $this->assertGreaterThanOrEqual(2, \count(StubTransport::$events));

        $profiler->stop();

        $chunkIds = [];

        foreach (StubTransport::$events as $event) {
            $profileChunk = $event->getProfileChunk();

            $this->assertNotNull($profileChunk);
            $this->assertSame($profilerId, $profileChunk->getProfilerId());

            $chunkIds[] = $profileChunk->getChunkId();
        }

        $this->assertSame($chunkIds, array_unique($chunkIds));
    }

    /**
     * @requires extension excimer
     */
    public function testChunksAreSplitByDuration(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(6060, 0.1);
        $profiler->start();

        self::busyWait(0.05);
        // Excimer records a single sample for the whole time the application is blocked
        usleep(200000);
        self::busyWait(0.05);

        $profiler->stop();

        $this->assertCount(2, StubTransport::$events);

        foreach (StubTransport::$events as $event) {
            $profileChunk = $event->getProfileChunk();

            $this->assertNotNull($profileChunk);

            $payload = $profileChunk->getFormattedData($event);

            $this->assertNotNull($payload);

            $samples = $payload['profile']['samples'];

            $this->assertLessThan(0.1, end($samples)['timestamp'] - $samples[0]['timestamp']);
        }
    }

    public function testCoroutinesAreAttributedToTheirOwnThreadsWhenSwooleIsLoaded(): void
    {
        $profiler = new ContinuousProfiler();

        if (\extension_loaded('swoole') || \extension_loaded('openswoole')) {
            $this->assertInstanceOf(SwooleThreadProvider::class, $profiler->getThreadProvider());
        } else {
            $this->assertInstanceOf(MainThreadProvider::class, $profiler->getThreadProvider());
        }
    }

    /**
     * @requires extension excimer
     */
    public function testSamplesAreAttributedToTheThreadOfTheThreadProvider(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->setThreadProvider(new class implements ThreadProviderInterface {
            public function getThreadId(): string
            {
                return '42';
            }

            public function getThreadName(): string
            {
                return 'worker';
            }

            public function isConcurrent(): bool
            {
                return false;
            }
        });

        $profiler->start();
        self::busyWait(0.05);
        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $payload = $event->getProfileChunk()->getFormattedData($event);

        $this->assertNotNull($payload);
        $this->assertSame(['42'], array_values(array_unique(array_column($payload['profile']['samples'], 'thread_id'))));
        $this->assertEquals((object) ['42' => ['name' => 'worker']], $payload['profile']['thread_metadata']);
    }

    /**
     * @requires extension excimer
     */
    public function testEachSampleIsAttributedToTheThreadRunningWhenItWasTakenIfTheThreadProviderIsConcurrent(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->setThreadProvider(new class implements ThreadProviderInterface {
            /**
             * @var int
             */
            private $threadId = 0;

            public function getThreadId(): string
            {
                // Another logical execution runs every time the thread is asked for
                return (string) ++$this->threadId;
            }

            public function getThreadName(): string
            {
                return 'thread ' . $this->threadId;
            }

            public function isConcurrent(): bool
            {
                return true;
            }
        });

        $profiler->start();
        self::busyWait(0.05);
        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $payload = $event->getProfileChunk()->getFormattedData($event);

        $this->assertNotNull($payload);

        $threadIds = array_column($payload['profile']['samples'], 'thread_id');

        $this->assertGreaterThanOrEqual(2, \count($threadIds));
        $this->assertSame($threadIds, array_values(array_unique($threadIds)));

        foreach ($threadIds as $threadId) {
            $this->assertSame(['name' => 'thread ' . $threadId], $payload['profile']['thread_metadata']->{$threadId});
        }
    }

    /**
     * @requires extension excimer
     */
    public function testSamplesAreBufferedTogetherIfTheThreadProviderIsNotConcurrent(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->setThreadProvider(new class implements ThreadProviderInterface {
            /**
             * @var int
             */
            private $threadId = 0;

            public function getThreadId(): string
            {
                // Another logical execution runs every time the thread is asked for
                return (string) ++$this->threadId;
            }

            public function getThreadName(): string
            {
                return 'thread ' . $this->threadId;
            }

            public function isConcurrent(): bool
            {
                return false;
            }
        });

        $profiler->start();
        self::busyWait(0.05);
        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $payload = $event->getProfileChunk()->getFormattedData($event);

        $this->assertNotNull($payload);
        $this->assertSame(['1'], array_values(array_unique(array_column($payload['profile']['samples'], 'thread_id'))));
    }

    private function bindClient(array $options): void
    {
        SentrySdk::init()->bindClient(new Client(new Options($options), StubTransport::getInstance()));
    }

    private static function busyWait(float $seconds): void
    {
        $end = microtime(true) + $seconds;

        while (microtime(true) < $end) {
            // Burn CPU so that Excimer collects samples
        }
    }
}
