<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Profiling\ContinuousProfiler;
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
    public function testRunningProfilerSendsChunksCompletedWhileSamplingWhenItIsFlushedOrStopped(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(5);
        $profiler->start();

        $profilerId = $profiler->getProfilerId();

        self::busyWait(0.2);

        // Sending while sampling would block the application, switch to another coroutine or fail outside of one
        $this->assertTrue($profiler->isRunning());
        $this->assertSame([], StubTransport::$events);

        $profiler->flush();

        $this->assertTrue($profiler->isRunning());
        $this->assertGreaterThanOrEqual(2, \count(StubTransport::$events));

        $flushedEvents = \count(StubTransport::$events);

        self::busyWait(0.1);

        $profiler->stop();

        $this->assertGreaterThan($flushedEvents, \count(StubTransport::$events));

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
    public function testPendingChunksAreLimited(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(2);
        $profiler->start();

        // Completes many more chunks than the 10 pending chunks that are kept
        self::busyWait(0.5);

        $profiler->capturePendingChunks();

        $this->assertCount(10, StubTransport::$events);

        $profiler->stop();
    }

    /**
     * @requires extension excimer
     */
    public function testCapturePendingChunksFlushesTheSamplesOnceTheFlushIntervalPassed(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(6060, 65.0, 0.05);
        $profiler->start();

        // Collects far fewer samples than Excimer needs to flush them
        self::busyWait(0.1);

        $profiler->capturePendingChunks();

        $this->assertTrue($profiler->isRunning());
        // The flushed samples are shorter than a chunk, so they are not split
        $this->assertCount(1, StubTransport::$events);

        $profiler->stop();
    }

    /**
     * @requires extension excimer
     */
    public function testCapturePendingChunksKeepsTheSamplesUntilTheFlushIntervalPassed(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        self::busyWait(0.1);

        $profiler->capturePendingChunks();

        $this->assertSame([], StubTransport::$events);

        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);
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

    /**
     * A forked process inherits the memory of the running profiler, but not the timer Excimer
     * samples with, so it would not be profiled anymore.
     *
     * The profiler is started and the process forked. The forked process asks for the profiler ID,
     * which replaces the inherited profiler with a new one without emitting any error, collects
     * samples and stops the profiler. The test then asserts that the forked process sent a chunk
     * with a new profiler ID, while the parent process keeps profiling with its own profiler.
     *
     * @requires extension excimer
     * @requires extension pcntl
     */
    public function testForkedProcessIsProfiledByANewProfiler(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        $parentProfilerId = $profiler->getProfilerId();

        $result = $this->runInForkedProcess(static function () use ($profiler): array {
            StubTransport::$events = [];

            error_clear_last();

            $childProfilerId = $profiler->getProfilerId();

            self::busyWait(0.2);

            $profiler->stop();

            return [
                'lastError' => error_get_last(),
                'childProfilerId' => $childProfilerId,
                'chunkProfilerIds' => self::getChunkProfilerIds(),
            ];
        });

        $this->assertNull($result['lastError']);
        $this->assertNotNull($result['childProfilerId']);
        $this->assertNotSame($parentProfilerId, $result['childProfilerId']);
        $this->assertSame([$result['childProfilerId']], $result['chunkProfilerIds']);

        // The parent process keeps profiling with its own profiler
        $this->assertSame($parentProfilerId, $profiler->getProfilerId());

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);
        $this->assertSame($parentProfilerId, StubTransport::$events[0]->getProfileChunk()->getProfilerId());
    }

    /**
     * Destroying the profiler inherited by a forked process passes the samples it has not flushed yet
     * to the flush callback. The parent process sends those samples itself, so the forked process must
     * not send them under the profiler ID of the parent process.
     *
     * The samples are buffered in batches and the chunks are short, so the samples collected before the
     * fork would fill several chunks while the inherited profiler is replaced.
     *
     * @requires extension excimer
     * @requires extension pcntl
     */
    public function testForkedProcessDoesNotSendTheSamplesOfTheParentProcess(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(1000, 0.1, 0.1);
        $profiler->start();

        self::busyWait(0.3);

        $result = $this->runInForkedProcess(static function () use ($profiler): array {
            StubTransport::$events = [];

            $profiler->getProfilerId();
            $profiler->capturePendingChunks();

            return ['chunkProfilerIds' => self::getChunkProfilerIds()];
        });

        $this->assertSame([], $result['chunkProfilerIds']);

        $profiler->stop();
    }

    /**
     * The chunks completed while sampling wait in the memory that a forked process inherits. The parent
     * process captures them itself, so the forked process must not capture them under the profiler ID of
     * the parent process.
     *
     * @requires extension excimer
     * @requires extension pcntl
     */
    public function testForkedProcessDoesNotCaptureThePendingChunksOfTheParentProcess(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(5);
        $profiler->start();

        $profilerId = $profiler->getProfilerId();

        self::busyWait(0.2);

        $result = $this->runInForkedProcess(static function () use ($profiler): array {
            StubTransport::$events = [];

            $profiler->capturePendingChunks();

            return ['chunkProfilerIds' => self::getChunkProfilerIds()];
        });

        $this->assertSame([], $result['chunkProfilerIds']);

        $profiler->capturePendingChunks();

        $this->assertNotEmpty(StubTransport::$events);
        $this->assertSame([$profilerId], array_values(array_unique(self::getChunkProfilerIds())));

        $profiler->stop();
    }

    private function bindClient(array $options): void
    {
        SentrySdk::init()->bindClient(new Client(new Options($options), StubTransport::getInstance()));
    }

    /**
     * Runs the callback in a forked process and returns its result. As the forked process cannot
     * report to the test directly, it writes its result to a file and kills itself, so the test
     * runner does not shut down a second time.
     *
     * @param callable(): array<string, mixed> $callback
     *
     * @return array<string, mixed>
     */
    private function runInForkedProcess(callable $callback): array
    {
        $resultFile = tempnam(sys_get_temp_dir(), 'sentry-profiler-fork');

        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                $result = $callback();
            } catch (\Throwable $exception) {
                $result = ['error' => (string) $exception];
            }

            file_put_contents($resultFile, json_encode($result));

            // Exits without running the shutdown of the test runner in the forked process
            posix_kill(getmypid(), \SIGKILL);
        }

        pcntl_waitpid($pid, $status);

        $result = json_decode((string) file_get_contents($resultFile), true);
        unlink($resultFile);

        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

        return $result;
    }

    /**
     * @return array<int, string|null>
     */
    private static function getChunkProfilerIds(): array
    {
        return array_map(static function (Event $event): ?string {
            return $event->getProfileChunk() !== null ? $event->getProfileChunk()->getProfilerId() : null;
        }, StubTransport::$events);
    }

    private static function busyWait(float $seconds): void
    {
        $end = microtime(true) + $seconds;

        while (microtime(true) < $end) {
            // Burn CPU so that Excimer collects samples
        }
    }
}
