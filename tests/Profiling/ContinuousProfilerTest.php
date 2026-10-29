<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientInterface;
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
    public function testCapturePendingChunksKeepsTheChunksWhileNoClientIsBound(): void
    {
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        $profiler = new ContinuousProfiler(5);
        $profiler->start();

        try {
            self::busyWait(0.1);

            SentrySdk::init();

            $profiler->capturePendingChunks();

            $this->bindClient(['profile_session_sample_rate' => 1.0]);

            $this->assertSame([], StubTransport::$events);

            $profiler->capturePendingChunks();

            $this->assertNotEmpty(StubTransport::$events);
        } finally {
            $profiler->stop();
        }
    }

    /**
     * @requires extension excimer
     */
    public function testStopIsNotInterruptedWhenAChunkCannotBeSent(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options(['profile_session_sample_rate' => 1.0]));
        $client->method('captureEvent')
            ->willThrowException(new \RuntimeException('Failed to capture the event.'));

        SentrySdk::init()->bindClient($client);

        $profiler = new ContinuousProfiler();
        $profiler->start();

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertFalse($profiler->isRunning());
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
     * The root spans of the parent process are finished by the parent process, so they must not keep the
     * profiler of a forked process running once the root spans of the forked process are finished.
     *
     * @requires extension excimer
     * @requires extension pcntl
     */
    public function testForkedProcessIsNotProfiledForTheRootSpansOfTheParentProcess(): void
    {
        $this->bindClient([
            'profile_lifecycle' => 'trace',
            'profile_session_sample_rate' => 1.0,
        ]);

        $profiler = new ContinuousProfiler();
        $profiler->startForRootSpan('566e3688a61d4bc8');

        $result = $this->runInForkedProcess(static function () use ($profiler): array {
            $profiler->startForRootSpan('b0e6f15b45c36b12');
            $profiler->stopForRootSpan('b0e6f15b45c36b12');

            return ['isRunning' => $profiler->isRunning()];
        });

        $this->assertFalse($result['isRunning']);

        // The parent process keeps profiling for its own root span
        $this->assertTrue($profiler->isRunning());

        $profiler->stopForRootSpan('566e3688a61d4bc8');

        $this->assertFalse($profiler->isRunning());
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

    /**
     * @requires extension excimer
     */
    public function testStartWithoutClientKeepsTheSamplesIfTheBoundClientSamplesTheSession(): void
    {
        $profiler = new ContinuousProfiler();
        $profiler->start();

        $this->assertTrue($profiler->isRunning());

        self::busyWait(0.05);

        $bindTimestamp = microtime(true);

        $profiler->onClientBound($this->bindClient(['profile_session_sample_rate' => 1.0]));

        $this->assertTrue($profiler->isRunning());

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertCount(1, StubTransport::$events);

        $profileChunk = StubTransport::$events[0]->getProfileChunk();

        $this->assertNotNull($profileChunk);

        $payload = $profileChunk->getFormattedData(StubTransport::$events[0]);

        $this->assertNotNull($payload);
        $this->assertLessThan($bindTimestamp, $payload['profile']['samples'][0]['timestamp']);
    }

    /**
     * @requires extension excimer
     */
    public function testStartWithoutClientDiscardsTheSamplesIfTheBoundClientDoesNotSampleTheSession(): void
    {
        $profiler = new ContinuousProfiler();
        $profiler->start();

        self::busyWait(0.05);

        $profiler->onClientBound($this->bindClient(['profile_session_sample_rate' => 0.0]));

        $this->assertFalse($profiler->isRunning());

        $profiler->stop();
        $profiler->start();

        $this->assertFalse($profiler->isRunning());
        $this->assertSame([], StubTransport::$events);
    }

    /**
     * @requires extension excimer
     */
    public function testStartWithoutClientAfterReInitKeepsTheSessionUnsampled(): void
    {
        $profiler = new ContinuousProfiler();

        $this->bindClient(['profile_session_sample_rate' => 0.0]);

        $profiler->start();

        SentrySdk::init();

        $profiler->start();

        $this->assertFalse($profiler->isRunning());

        $profiler->onClientBound($this->bindClient(['profile_session_sample_rate' => 0.0]));

        $this->assertFalse($profiler->isRunning());
    }

    /**
     * @requires extension excimer
     */
    public function testStartWithoutClientDiscardsTheSamplesIfTheBoundClientUsesTheTraceLifecycle(): void
    {
        $profiler = new ContinuousProfiler();
        $profiler->start();

        self::busyWait(0.05);

        $profiler->onClientBound($this->bindClient([
            'profile_lifecycle' => 'trace',
            'profile_session_sample_rate' => 1.0,
        ]));

        $this->assertFalse($profiler->isRunning());
        $this->assertSame([], StubTransport::$events);

        $profiler->startForRootSpan('566e3688a61d4bc8');

        $this->assertTrue($profiler->isRunning());

        $profiler->stopForRootSpan('566e3688a61d4bc8');
    }

    /**
     * @requires extension excimer
     */
    public function testChunksFilledBeforeAClientIsBoundAreDropped(): void
    {
        $profiler = new ContinuousProfiler(5);
        $profiler->start();

        self::busyWait(0.2);

        $bindTimestamp = microtime(true);

        $profiler->onClientBound($this->bindClient(['profile_session_sample_rate' => 1.0]));

        self::busyWait(0.05);

        $profiler->stop();

        $samplesBeforeBind = 0;

        foreach (StubTransport::$events as $event) {
            $profileChunk = $event->getProfileChunk();

            $this->assertNotNull($profileChunk);

            $payload = $profileChunk->getFormattedData($event);

            $this->assertNotNull($payload);

            foreach ($payload['profile']['samples'] as $sample) {
                if ($sample['timestamp'] < $bindTimestamp) {
                    ++$samplesBeforeBind;
                }
            }
        }

        // Only the samples of the chunk that was not full yet when the client was bound are kept
        $this->assertLessThan(5, $samplesBeforeBind);
    }

    /**
     * @requires extension excimer
     */
    public function testSamplesAreNotSentBeforeTheSessionIsDecided(): void
    {
        $profiler = new ContinuousProfiler();
        $profiler->start();

        // The client is bound without deciding the session of this profiler
        $this->bindClient(['profile_session_sample_rate' => 1.0]);

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertSame([], StubTransport::$events);
    }

    private function bindClient(array $options): Client
    {
        $client = new Client(new Options($options), StubTransport::getInstance());

        SentrySdk::init()->bindClient($client);

        return $client;
    }

    /**
     * Runs the callback in a forked process and returns its result. As the forked process cannot
     * report to the test directly, it writes its result to a file and kills itself, so the test
     * runner does not shut down a second time.
     *
     * On Linux, Excimer does not reset its locks in a forked process, so a fork while its timer thread
     * holds one leaves the forked process waiting for that lock forever. Such a process is killed and
     * the callback is run in a new one.
     *
     * @param callable(): array<string, mixed> $callback
     *
     * @return array<string, mixed>
     */
    private function runInForkedProcess(callable $callback): array
    {
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $result = $this->tryRunInForkedProcess($callback, 10.0);

            if ($result !== null) {
                $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

                return $result;
            }
        }

        $this->fail('The forked process did not report a result.');
    }

    /**
     * @param callable(): array<string, mixed> $callback
     *
     * @return array<string, mixed>|null The result, or null if the forked process did not report one in time
     */
    private function tryRunInForkedProcess(callable $callback, float $timeout): ?array
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

        $deadline = microtime(true) + $timeout;

        while (pcntl_waitpid($pid, $status, \WNOHANG) === 0) {
            if (microtime(true) >= $deadline) {
                posix_kill($pid, \SIGKILL);
                pcntl_waitpid($pid, $status);

                break;
            }

            usleep(10000);
        }

        $result = json_decode((string) file_get_contents($resultFile), true);
        unlink($resultFile);

        return \is_array($result) ? $result : null;
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
