<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Profiling\Profile;
use Sentry\Profiling\Profiler;
use Sentry\SentrySdk;
use Sentry\Tests\StubTransport;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * @requires extension excimer
 */
final class ProfilerTest extends TestCase
{
    public function testStopReleasesTheExcimerProfiler(): void
    {
        $profiler = new Profiler(new Options());

        $this->assertInstanceOf(\ExcimerProfiler::class, $this->getExcimerProfiler($profiler));

        $profiler->start();
        $profiler->stop();

        $this->assertNull($this->getExcimerProfiler($profiler));
        $this->assertInstanceOf(Profile::class, $profiler->getProfile());

        // Calling start/stop again after the profiler has been released is a no-op
        $profiler->start();
        $profiler->stop();

        $this->assertInstanceOf(Profile::class, $profiler->getProfile());
    }

    public function testFinishingTransactionReleasesTheExcimerProfilerAndKeepsTheProfile(): void
    {
        StubTransport::$events = [];

        $hub = SentrySdk::init();
        $hub->bindClient(new Client(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sample_rate' => 1.0,
        ]), StubTransport::getInstance()));

        $transaction = new Transaction(TransactionContext::make()->setSampled(true), $hub);
        $transaction->initSpanRecorder();

        $profiler = $transaction->initProfiler();
        $profiler->start();

        $transaction->startChild(new SpanContext())->finish();
        $transaction->finish();

        $this->assertInstanceOf(Profiler::class, $profiler);
        $this->assertNull($this->getExcimerProfiler($profiler));

        $transactionEvents = array_values(array_filter(StubTransport::$events, static function (Event $event): bool {
            return $event->getType() === EventType::transaction();
        }));

        $this->assertCount(1, $transactionEvents);
        $this->assertSame($profiler->getProfile(), $transactionEvents[0]->getSdkMetadata('profile'));
    }

    private function getExcimerProfiler(Profiler $profiler): ?\ExcimerProfiler
    {
        $property = new \ReflectionProperty(Profiler::class, 'profiler');

        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($profiler);
    }
}
