<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Tests\ClientReport\ClientReportAssertions;
use Sentry\Tests\StubTransport;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanRecorder;

final class SpanRecorderTest extends TestCase
{
    use ClientReportAssertions;

    public function testAdd(): void
    {
        SentrySdk::init()->bindClient(new Client(new Options([
            'send_client_reports' => true,
        ]), StubTransport::getInstance()));

        $span1 = new class extends Span {
            public function __construct()
            {
                parent::__construct();

                $this->spanRecorder = new SpanRecorder(1);
                $this->spanRecorder->add($this);
            }

            public function getSpanRecorder(): SpanRecorder
            {
                return $this->spanRecorder;
            }
        };

        $span2 = $span1->startChild(new SpanContext());
        $span3 = $span2->startChild(new SpanContext()); // this should not end up being recorded

        $this->assertSame([$span1, $span2], $span1->getSpanRecorder()->getSpans());
        $this->assertClientReports(['span' => ['buffer_overflow' => 1]]);
    }
}
