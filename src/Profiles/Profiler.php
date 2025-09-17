<?php

declare(strict_types=1);

namespace Sentry\Profiles;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Options;
use Sentry\Util\SentryUid;

/**
 * @internal
 */
final class Profiler
{
    /**
     * @var \ExcimerProfiler|null
     */
    private $profiler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var float|null The start time of the profile as a Unix timestamp with microseconds
     */
    private $startTimeStamp;

    /**
     * @var string The profiler ID
     */
    private $profilerId;

    /**
     * @var float The sample rate (10.01ms/101 Hz)
     */
    private const SAMPLE_RATE = 0.0101;

    /**
     * @var int The maximum stack depth
     */
    private const MAX_STACK_DEPTH = 128;

    public function __construct(?Options $options = null)
    {
        $this->logger = $options !== null ? $options->getLoggerOrNullLogger() : new NullLogger();

        $this->initProfiler();
    }

    public function start(): void
    {
        if ($this->profiler !== null) {
            $this->profiler->start();
        }
    }

    public function stop(): ?\ExcimerLog
    {
        if ($this->profiler !== null) {
            $this->profiler->stop();

            return $this->profiler->flush();
        }

        return null;
    }

    public function getStartTimeStamp(): ?float
    {
        return $this->startTimeStamp;
    }

    public function getProfilerId(): ?string
    {
        return $this->profilerId;
    }

    private function initProfiler(): void
    {
        if (!\extension_loaded('excimer')) {
            $this->logger->warning('The profiler was started but is not available because the "excimer" extension is not loaded.');

            return;
        }

        $this->profiler = new \ExcimerProfiler();
        $this->startTimeStamp = microtime(true);
        $this->profilerId = SentryUid::generate();

        $this->profiler->setEventType(\EXCIMER_REAL);
        $this->profiler->setPeriod(self::SAMPLE_RATE);
        $this->profiler->setMaxDepth(self::MAX_STACK_DEPTH);
    }
}
