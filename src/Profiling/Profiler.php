<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Options;

/**
 * @internal
 */
final class Profiler
{
    /**
     * @var \ExcimerProfiler|null
     */
    private static $profiler;

    /**
     * @var float|null
     */
    private static $startedAt;

    /**
     * @var Profile
     */
    private $profile;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
        $this->profile = new Profile($options);

        $this->initProfiler();
    }

    public function start(): void
    {
        if (self::$profiler === null) {
            return;
        }

        // Prevent starting the profiler multiple times since that will discard the previous data
        // If you need to "restart" profiling, stop first to remove data and start again to start fresh
        if (self::$startedAt !== null) {
            return;
        }

        self::startProfiling();
    }

    public function stop(): void
    {
        if (self::$profiler === null) {
            return;
        }

        self::$profiler->stop();

        $this->profile->setExcimerLog(self::$profiler->flush());

        if (self::$startedAt !== null) {
            $this->profile->setStartTimeStamp(self::$startedAt);

            self::$startedAt = null;
        }
    }

    public function getProfile(): ?Profile
    {
        if (self::$profiler === null) {
            return null;
        }

        return $this->profile;
    }

    private function initProfiler(): void
    {
        if (self::$startedAt !== null) {
            $this->logger->debug('The profiler was already started, will continue profiling.', ['started_at' => self::$startedAt]);

            $this->profile->setStartTimeStamp(self::$startedAt);

            return;
        }

        self::setupProfiler();

        if (self::$profiler === null) {
            $this->logger->warning('The profiler was started but is not available because the "excimer" extension is not loaded.');

            return;
        }

        self::startProfiling();
    }

    private static function setupProfiler(): void
    {
        // Setup is only needed once
        if (self::$profiler !== null) {
            return;
        }

        if (!\extension_loaded('excimer')) {
            return;
        }

        $profiler = new \ExcimerProfiler();

        $profiler->setPeriod(self::SAMPLE_RATE);
        $profiler->setMaxDepth(self::MAX_STACK_DEPTH);
        $profiler->setEventType(\EXCIMER_REAL);

        self::$profiler = $profiler;
    }

    public static function startProfiling(): void
    {
        self::setupProfiler();

        if (self::$profiler === null) {
            return;
        }

        self::$profiler->start();
        self::$startedAt = microtime(true);
    }

    public static function maybeDiscardProfilingData(): void
    {
        // If we don't have an instance of the profiler there is no data to discard making this a no-op if the profiler was not started
        if (self::$profiler === null) {
            return;
        }

        self::$profiler->stop();

        self::$profiler = null;
        self::$startedAt = null;
    }
}
