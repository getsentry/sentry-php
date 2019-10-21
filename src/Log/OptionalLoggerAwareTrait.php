<?php

declare(strict_types=1);

namespace Sentry\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Trait to implement Psr\Log\LoggerAwareInterface similar to Psr\Log\LoggerAwareTrait
 * but with a protected getter for the logger that falls back
 * to NullLogger in case no logger has been set for the object.
 */
trait OptionalLoggerAwareTrait
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
