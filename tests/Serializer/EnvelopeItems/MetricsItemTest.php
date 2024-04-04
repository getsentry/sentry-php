<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Metrics\MetricsUnit;
use Sentry\Metrics\Types\CounterType;
use Sentry\Serializer\EnvelopItems\MetricsItem;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class MetricsItemTest extends TestCase
{
    /**
     * @dataProvider toEnvelopeItemDataProvider
     */
    public function testToEnvelopeItem(Event $event, string $expectedResult): void
    {
        ClockMock::withClockMock(1597790835);

        $result = MetricsItem::toEnvelopeItem($event);

        $this->assertSame($expectedResult, $result);
    }

    public static function toEnvelopeItemDataProvider(): iterable
    {
        $metric = new CounterType('abcABC123_-./äöü$%&abcABC123', 1.0, MetricsUnit::custom('abcABC123_-./äöü$%&abcABC123'), [
            'abcABC123_-./äöü$%&abcABC123' => "abc\n\r\t|,\\123",
        ], 1597790835);

        $event = Event::createMetrics(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMetrics([
            $metric,
        ]);

        yield [
            $event,
            <<<TEXT
{"type":"statsd","length":112}
abcABC123_-._abcABC123@abcABC123_abcABC123:1|c|#abcABC123_-./abcABC123:abc\\n\\r\\t\\u{7c}\\u{2c}\\\\123|T1597790835
TEXT
            ,
        ];
    }
}
