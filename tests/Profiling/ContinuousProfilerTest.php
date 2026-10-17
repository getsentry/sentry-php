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
