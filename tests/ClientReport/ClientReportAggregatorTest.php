<?php

declare(strict_types=1);

namespace Sentry\Tests\ClientReport;

use PHPUnit\Framework\TestCase;
use Sentry\Attachment\Attachment;
use Sentry\Client;
use Sentry\ClientReport\ClientReportAggregator;
use Sentry\ClientReport\Reason;
use Sentry\Event;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;
use Sentry\Metrics\Types\CounterMetric;
use Sentry\Metrics\Types\DistributionMetric;
use Sentry\Options;
use Sentry\Profiling\Profile;
use Sentry\SentrySdk;
use Sentry\Serializer\EnvelopItems\LogsItem;
use Sentry\Serializer\EnvelopItems\MetricsItem;
use Sentry\State\Hub;
use Sentry\Tests\StubLogger;
use Sentry\Tests\StubTransport;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Transport\DataCategory;
use Sentry\Unit;

class ClientReportAggregatorTest extends TestCase
{
    use ClientReportAssertions;

    protected function setUp(): void
    {
        ini_set('zend.exception_ignore_args', '0');
        StubTransport::$events = [];
        StubLogger::$logs = [];
        SentrySdk::init()->bindClient(new Client(new Options([
            'logger' => StubLogger::getInstance(),
        ]), StubTransport::getInstance()));
    }

    public function testAddClientReport(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::error(), Reason::beforeSend(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertCount(1, StubTransport::$events);
        $reports = StubTransport::$events[0]->getClientReports();
        $this->assertCount(2, $reports);

        $report = $reports[0];
        $this->assertSame(DataCategory::profile()->getValue(), $report->getCategory());
        $this->assertSame(Reason::eventProcessor()->getValue(), $report->getReason());
        $this->assertSame(10, $report->getQuantity());

        $report = $reports[1];
        $this->assertSame(DataCategory::error()->getValue(), $report->getCategory());
        $this->assertSame(Reason::beforeSend()->getValue(), $report->getReason());
        $this->assertSame(10, $report->getQuantity());
    }

    public function testClientReportAggregation(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertCount(1, StubTransport::$events);
        $reports = StubTransport::$events[0]->getClientReports();
        $this->assertCount(1, $reports);

        $report = $reports[0];
        $this->assertSame(DataCategory::profile()->getValue(), $report->getCategory());
        $this->assertSame(Reason::eventProcessor()->getValue(), $report->getReason());
        $this->assertSame(40, $report->getQuantity());
    }

    public function testFlushDoesNotOverwriteLastEventId(): void
    {
        $hub = SentrySdk::getCurrentHub();
        $eventId = $hub->captureMessage('foo');

        $this->assertNotNull($eventId);
        $this->assertSame($eventId, $hub->getLastEventId());

        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertSame($eventId, $hub->getLastEventId());
    }

    public function testNegativeQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), -10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertNotEmpty(StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={reason} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => -10]], StubLogger::$logs[0]);
    }

    public function testZeroQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 0);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertCount(1, StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={reason} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => 0]], StubLogger::$logs[0]);
    }

    public function testNegativeQuantityDiscardedWhenNoClientIsBound(): void
    {
        SentrySdk::setCurrentHub(new Hub());

        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), -10);

        SentrySdk::setCurrentHub(new Hub(new Client(new Options([
            'logger' => StubLogger::getInstance(),
        ]), StubTransport::getInstance())));

        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertEmpty(StubLogger::$logs);
    }

    public function testAddEventForError(): void
    {
        $event = Event::createEvent();
        $event->setAttachments([
            Attachment::fromBytes('foo.txt', 'foo'),
            Attachment::fromBytes('bar.txt', 'barbaz'),
        ]);

        ClientReportAggregator::getInstance()->addEvent($event, Reason::beforeSend());

        $this->assertClientReports([
            'error' => ['before_send' => 1],
            'attachment' => ['before_send' => 9],
        ]);
    }

    public function testAddEventForTransaction(): void
    {
        $event = Event::createTransaction();
        $event->setSpans([new Span(), new Span()]);
        $event->setSdkMetadata('profile', new Profile());

        ClientReportAggregator::getInstance()->addEvent($event, Reason::ratelimitBackoff());

        $this->assertClientReports([
            'transaction' => ['ratelimit_backoff' => 1],
            'span' => ['ratelimit_backoff' => 3],
            'profile' => ['ratelimit_backoff' => 1],
        ]);
    }

    public function testAddEventForTransactionWithoutSpans(): void
    {
        ClientReportAggregator::getInstance()->addEvent(Event::createTransaction(), Reason::sampleRate());

        $this->assertClientReports([
            'transaction' => ['sample_rate' => 1],
            'span' => ['sample_rate' => 1],
        ]);
    }

    public function testAddEventForCheckIn(): void
    {
        ClientReportAggregator::getInstance()->addEvent(Event::createCheckIn(), Reason::beforeSend());

        $this->assertClientReports([
            'monitor' => ['before_send' => 1],
        ]);
    }

    public function testAddEventForLogs(): void
    {
        $event = Event::createLogs()->setLogs([
            (new Log(1597790835.123456, '566e3688a61d4bc888951642d6f14a19', LogLevel::info(), 'User jane logged in from 10.0.0.1'))
                ->setAttribute('sentry.release', '1.2.3')
                ->setAttribute('sentry.environment', 'production')
                ->setAttribute('sentry.sdk.name', 'sentry.php')
                ->setAttribute('sentry.message.template', 'User %s logged in from %s')
                ->setAttribute('sentry.message.parameter.0', 'jane')
                ->setAttribute('user.id', 42)
                ->setAttribute('duration', 12.5)
                ->setAttribute('http.ok', true),
            new Log(1597790835.123456, '566e3688a61d4bc888951642d6f14a19', LogLevel::error(), 'Something went wrong'),
        ]);

        ClientReportAggregator::getInstance()->addEvent($event, Reason::ratelimitBackoff());

        $reports = self::getClientReports();

        $this->assertSame(['log_item', 'log_byte'], array_keys($reports));
        $this->assertSame(['ratelimit_backoff' => 2], $reports['log_item']);
        $this->assertEstimatedSize(LogsItem::toEnvelopeItem($event), $reports['log_byte']['ratelimit_backoff']);
    }

    public function testAddEventForMetrics(): void
    {
        $event = Event::createMetrics()->setMetrics([
            new CounterMetric('api.requests', 1, new TraceId('566e3688a61d4bc888951642d6f14a19'), new SpanId('566e3688a61d4bc8'), ['sentry.environment' => 'production', 'route' => 'users.show', 'cached' => false], 1597790835.123456, null),
            new DistributionMetric('api.duration', 12.75, new TraceId('566e3688a61d4bc888951642d6f14a19'), new SpanId('566e3688a61d4bc8'), ['sentry.environment' => 'production'], 1597790835.123456, Unit::millisecond()),
        ]);

        ClientReportAggregator::getInstance()->addEvent($event, Reason::ratelimitBackoff());

        $reports = self::getClientReports();

        $this->assertSame(['trace_metric', 'trace_metric_byte'], array_keys($reports));
        $this->assertSame(['ratelimit_backoff' => 2], $reports['trace_metric']);
        $this->assertEstimatedSize(MetricsItem::toEnvelopeItem($event), $reports['trace_metric_byte']['ratelimit_backoff']);
    }

    public function testAddEventIgnoresClientReports(): void
    {
        ClientReportAggregator::getInstance()->addEvent(Event::createClientReport(), Reason::sendError());

        $this->assertClientReports([]);
    }

    public function testNothingIsRecordedWhenClientReportsAreDisabled(): void
    {
        SentrySdk::getCurrentHub()->bindClient(new Client(new Options([
            'send_client_reports' => false,
        ]), StubTransport::getInstance()));

        ClientReportAggregator::getInstance()->add(DataCategory::error(), Reason::beforeSend(), 1);
        ClientReportAggregator::getInstance()->addEvent(Event::createEvent(), Reason::beforeSend());

        $this->assertClientReports([]);
    }

    /**
     * Asserts that the estimated size is within 5% of the serialized items, so that a change of the
     * serialized format must be reflected in the estimate.
     */
    private function assertEstimatedSize(string $envelopeItem, int $estimatedSize): void
    {
        $serializedSize = \strlen(explode("\n", $envelopeItem, 2)[1]);

        $this->assertEqualsWithDelta($serializedSize, $estimatedSize, $serializedSize * 0.05);
    }
}
