<?php

declare(strict_types=1);

namespace Sentry\Tests\ClientReport;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientReport\ClientReportAggregator;
use Sentry\ClientReport\Reason;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Tests\StubLogger;
use Sentry\Tests\StubTransport;
use Sentry\Transport\DataCategory;

class ClientReportAggregatorTest extends TestCase
{
    protected function setUp(): void
    {
        StubTransport::$events = [];
        StubLogger::$logs = [];
        SentrySdk::init()->bindClient(new Client(new Options([
            'logger' => StubLogger::getInstance(),
            'default_integrations' => false,
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

    public function testNegativeQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), -10);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertNotEmpty(StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => -10]], StubLogger::$logs[0]);
    }

    public function testZeroQuantityDiscarded(): void
    {
        ClientReportAggregator::getInstance()->add(DataCategory::profile(), Reason::eventProcessor(), 0);
        ClientReportAggregator::getInstance()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertCount(1, StubLogger::$logs);
        $this->assertSame(['level' => 'debug', 'message' => 'Dropping Client report with category={category} and reason={} because quantity is zero or negative ({quantity})', 'context' => ['category' => 'profile', 'reason' => 'event_processor', 'quantity' => 0]], StubLogger::$logs[0]);
    }
}
