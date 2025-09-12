<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Sentry\Logs\LogLevel;
use Sentry\Logs\Logs;

class LogsHandler implements HandlerInterface
{
    use CompatibilityLogLevelTrait;

    /**
     * The minimum logging level at which this handler will be triggered.
     *
     * @var LogLevel
     */
    private $logLevel;

    /**
     * Whether the messages that are handled can bubble up the stack or not.
     *
     * @var bool
     */
    private $bubble;

    /**
     * Creates a new Monolog handler that converts Monolog logs to Sentry logs.
     *
     * @param LogLevel|null $logLevel the minimum logging level at which this handler will be triggered and collects the logs
     * @param bool          $bubble   whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(?LogLevel $logLevel = null, bool $bubble = true)
    {
        $this->logLevel = $logLevel ?? LogLevel::debug();
        $this->bubble = $bubble;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function isHandling($record): bool
    {
        if ($record instanceof LogRecord) {
            $level = $record->level;
        } else {
            /** @var int|Level $level */
            $level = $record['level'];
        }
        return self::getSentryLogLevelFromMonologLevel($level instanceof Level ? $level->value : $level)->getPriority() >= $this->logLevel->getPriority();
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function handle($record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($record instanceof LogRecord) {
            $level = $record->level;
            $message = $record->message;
            $context = $record->context;
            $extra = $record->extra;
        } else {
            /** @var int|Level $level */
            $level = $record['level'];
            /** @var string $message */
            $message = $record['message'];
            /** @var array<string, mixed> $context */
            $context = $record['context'] ?? [];
            /** @var array<string, mixed> $extra */
            $extra = $record['extra'] ?? [];
        }

        // Do not collect logs for exceptions, they should be handled seperately by the `Handler` or `captureException`
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            return false;
        }

        Logs::getInstance()->aggregator()->add(
            self::getSentryLogLevelFromMonologLevel($level instanceof Level ? $level->value : $level),
            $message,
            [],
            array_merge($context, $extra)
        );

        return $this->bubble === false;
    }

    /**
     * @param array<array<string, mixed>|LogRecord> $records
     */
    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void
    {
        Logs::getInstance()->flush();
    }

    /**
     * @param callable $callback
     */
    public function pushProcessor($callback): void
    {
        // noop, this handler does not support processors
    }

    /**
     * @return callable
     */
    public function popProcessor()
    {
        // Since we do not support processors, we throw an exception if this method is called
        throw new \LogicException('You tried to pop from an empty processor stack.');
    }

    public function setFormatter(FormatterInterface $formatter): void
    {
        // noop, this handler does not support formatters
    }

    public function getFormatter(): FormatterInterface
    {
        // To adhere to the interface we need to return a formatter so we return a default one
        return new LineFormatter();
    }
}
