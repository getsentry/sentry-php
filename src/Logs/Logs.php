<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\EventId;

class Logs
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var LogsAggregator
     */
    private $aggregator;

    private function __construct()
    {
        $this->aggregator = new LogsAggregator();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function trace(string $message): void
    {
        $this->aggregator->add(LogLevel::trace(), $message);
    }

    public function debug(string $message): void
    {
        $this->aggregator->add(LogLevel::debug(), $message);
    }

    public function info(string $message): void
    {
        $this->aggregator->add(LogLevel::info(), $message);
    }

    public function warn(string $message): void
    {
        $this->aggregator->add(LogLevel::warn(), $message);
    }

    public function error(string $message): void
    {
        $this->aggregator->add(LogLevel::error(), $message);
    }

    public function fatal(string $message): void
    {
        $this->aggregator->add(LogLevel::fatal(), $message);
    }

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }
}
