<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Sentry\Logs\LogLevel;
use Sentry\Logs\LogsAggregator;

/**
 * This Monolog handler logs every message to Sentry's logs system using the LogsAggregator.
 * 
 * Unlike the regular Handler which creates Sentry events, this handler specifically
 * targets the new Sentry logs telemetry feature.
 *
 * @author Sentry Team
 */
final class LogsHandler extends AbstractProcessingHandler
{
    use CompatibilityProcessingHandlerTrait;

    /**
     * @var LogsAggregator
     */
    private $logsAggregator;

    /**
     * @var bool Whether to include Monolog context and extra data as attributes
     */
    private $includeMonologData;

    /**
     * {@inheritdoc}
     *
     * @param LogsAggregator|null $logsAggregator The logs aggregator to use. If null, creates a new one.
     * @param bool $includeMonologData Whether to include Monolog context and extra data as attributes
     */
    public function __construct(?LogsAggregator $logsAggregator = null, $level = Logger::DEBUG, bool $bubble = true, bool $includeMonologData = true)
    {
        parent::__construct($level, $bubble);

        $this->logsAggregator = $logsAggregator ?? new LogsAggregator();
        $this->includeMonologData = $includeMonologData;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    protected function doWrite($record): void
    {
        $level = $this->mapMonologLevelToSentryLevel($record['level']);
        $message = $record['message'];
        $attributes = [];

        // Add Monolog-specific attributes
        if ($this->includeMonologData) {
            $attributes['monolog.channel'] = $record['channel'];
            $attributes['monolog.level_name'] = $record['level_name'];
            
            if (isset($record['datetime'])) {
                $attributes['monolog.datetime'] = $record['datetime']->format(\DateTime::ISO8601);
            }

            // Include context data as attributes
            if (isset($record['context']) && is_array($record['context'])) {
                foreach ($record['context'] as $key => $value) {
                    // Skip exception objects as they should be handled separately
                    if ($value instanceof \Throwable) {
                        $attributes["monolog.context.{$key}.class"] = get_class($value);
                        $attributes["monolog.context.{$key}.message"] = $value->getMessage();
                        $attributes["monolog.context.{$key}.file"] = $value->getFile();
                        $attributes["monolog.context.{$key}.line"] = $value->getLine();
                    } else {
                        $attributes["monolog.context.{$key}"] = $value;
                    }
                }
            }

            // Include extra data as attributes
            if (isset($record['extra']) && is_array($record['extra'])) {
                foreach ($record['extra'] as $key => $value) {
                    $attributes["monolog.extra.{$key}"] = $value;
                }
            }
        }

        // Add the log to the aggregator
        $this->logsAggregator->add($level, $message, [], $attributes);
    }

    /**
     * Map Monolog log levels to Sentry LogLevel instances.
     */
    private function mapMonologLevelToSentryLevel(int $monologLevel): LogLevel
    {
        switch ($monologLevel) {
            case Level::Debug:
                return LogLevel::debug();
            case Level::Info:
            case Level::Notice:
                return LogLevel::info();
            case Level::Warning:
                return LogLevel::warn();
            case Level::Error:
                return LogLevel::error();
            case Level::Critical:
            case Level::Alert:
            case Level::Emergency:
                return LogLevel::fatal();
            default:
                return LogLevel::info();
        }
    }

    /**
     * Get the logs aggregator instance.
     */
    public function getLogsAggregator(): LogsAggregator
    {
        return $this->logsAggregator;
    }

    /**
     * Flush all accumulated logs to Sentry.
     * 
     * This method can be called manually to force sending logs immediately,
     * or it will be called automatically when the aggregator is destructed.
     */
    public function flush(): void
    {
        $this->logsAggregator->flush();
    }
} 