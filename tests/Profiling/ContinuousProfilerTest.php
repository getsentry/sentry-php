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
