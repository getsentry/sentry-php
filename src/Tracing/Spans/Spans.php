<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\Scope;

class Spans
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var SpansAggregator
     */
    private $aggregator;

    private function __construct()
    {
        $this->aggregator = new SpansAggregator();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add(Span $span): void
    {
        $this->aggregator->add($span);
    }

    public function get(?SpanId $spanId): ?Span
    {
        return $this->aggregator->get($spanId);
    }

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }

    public function aggregator(): SpansAggregator
    {
        return $this->aggregator;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param Span|false|null      $parent
     */
    public static function startSpan(string $name, $parent = false, array $attributes = []): Span
    {
        $span = Span::make()->start($parent === false ? null : $parent);
        $span->setName($name);
        foreach ($attributes as $name => $value) {
            $span->setAttribute($name, $value);
        }
        // TODO: think how to handle nested segment spans
        if (!\is_bool($parent) && $parent !== null) {
            $span->applyFromParent($parent);
        }

        return $span;
    }
}
