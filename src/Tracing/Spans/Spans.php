<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\EventId;

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

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }

    public function aggregator(): SpansAggregator
    {
        return $this->aggregator;
    }
}
