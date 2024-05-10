<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Sentry\Serializer\EnvelopItems\MetricsItem;

final class MetricsItemTest extends TestCase
{
    public function testEscapeTagValues(): void
    {
        // No escaping
        $this->assertSame('plain', MetricsItem::escapeTagValues('plain'));
        $this->assertSame('plain text', MetricsItem::escapeTagValues('plain text'));
        $this->assertSame('plain%text', MetricsItem::escapeTagValues('plain%text'));

        // Escape sequences
        $this->assertSame('plain \\\\\\\\ text', MetricsItem::escapeTagValues('plain \\\\ text'));
        $this->assertSame('plain\\u{2c}text', MetricsItem::escapeTagValues('plain,text'));
        $this->assertSame('plain\\u{7c}text', MetricsItem::escapeTagValues('plain|text'));
        $this->assertSame('plain 😅', MetricsItem::escapeTagValues('plain 😅'));

        // Escapable control characters
        $this->assertSame('plain\\\\ntext', MetricsItem::escapeTagValues("plain\ntext"));
        $this->assertSame('plain\\\\rtext', MetricsItem::escapeTagValues("plain\rtext"));
        $this->assertSame('plain\\\\ttext', MetricsItem::escapeTagValues("plain\ttext"));
    }
}
