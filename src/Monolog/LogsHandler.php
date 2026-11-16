<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Sentry\Logs\LogLevel;
use Sentry\Logs\Logs;

class LogsHandler implements HandlerInterface
{
    use CompatibilityLogLevelTrait;

    /**
     * The minimum logging level at which this handler will be triggered.
     *
     * @var LogLevel|\Monolog\Level|int
     */
    private $logLevel;

    /**
     * Whether the messages that are handled can bubble up the stack or not.
     *
     * @var bool
     */
    private $bubble;

    /**
     * Whether to include the channel name as an attribute in the Sentry logs.
     *
     * @var bool
     */
    private $includeChannel;

    /**
     * Creates a new Monolog handler that converts Monolog logs to Sentry logs.
     *
     * @param LogLevel|\Monolog\Level|int|null $logLevel       the minimum logging level at which this handler will be triggered and collects the logs
     * @param bool                             $bubble         whether the messages that are handled can bubble up the stack or not
     * @param bool                             $includeChannel whether to include the channel name as an attribute in the Sentry logs
     */
    public function __construct($logLevel = null, bool $bubble = true, bool $includeChannel = false)
    {
        $this->logLevel = $logLevel ?? LogLevel::debug();
        $this->bubble = $bubble;
        $this->includeChannel = $includeChannel;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function isHandling($record): bool
    {
        if ($this->logLevel instanceof LogLevel) {
            return self::getSentryLogLevelFromMonologLevel($record['level'])->getPriority() >= $this->logLevel->getPriority();
        } elseif ($this->logLevel instanceof \Monolog\Level) {
            return $record['level'] >= $this->logLevel->value;
        }

        return $record['level'] >= $this->logLevel;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function handle($record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }
        // Do not collect logs for exceptions, they should be handled separately by `ExceptionToSentryIssueHandler` or `captureException`
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            return false;
        }

        /** @mago-ignore analysis:mixed-assignment */
        [$body, $templateAttributes] = self::replacePlaceholders($record['message'], $record['context']);

        Logs::getInstance()->aggregator()->add(
            self::getSentryLogLevelFromMonologLevel($record['level']),
            $body,
            [],
            array_merge($this->compileAttributes($record), $templateAttributes)
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

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            // Just in case so that the destructor can never fail.
        }
    }

    /**
     * Replaces the PSR-3 placeholders of a message, such as `{name}`, with the values of the context.
     *
     * @param mixed $message
     * @param mixed $context
     *
     * @return array{0: mixed, 1: array<string, bool|float|int|string>} The message and, if a placeholder was replaced,
     *                                                                  the attributes for the template and its parameters
     */
    private static function replacePlaceholders($message, $context): array
    {
        if (!\is_string($message) || !\is_array($context) || strpos($message, '{') === false) {
            return [$message, []];
        }

        $body = $message;
        $attributes = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($context as $name => $value) {
            $placeholder = '{' . $name . '}';

            if (strpos($message, $placeholder) === false) {
                continue;
            }

            if ($value instanceof \Throwable) {
                // As a string an exception is its whole stack trace, so only its class name and message are used
                $value = rtrim(\get_class($value) . ': ' . $value->getMessage(), ': ');
            } elseif ($value === null || (\is_object($value) && method_exists($value, '__toString'))) {
                $value = (string) $value;
            }

            if (!\is_scalar($value)) {
                continue;
            }

            $attributes["sentry.message.parameter.{$name}"] = $value;
            $body = str_replace($placeholder, (string) $value, $body);
        }

        if ($attributes !== []) {
            $attributes['sentry.message.template'] = $message;
        }

        return [$body, $attributes];
    }

    /**
     * @param array<string,mixed>|LogRecord $record
     *
     * @return array<string,mixed>
     */
    protected function compileAttributes($record): array
    {
        $attributes = array_merge($record['context'], $record['extra'], ['sentry.origin' => 'auto.log.monolog']);

        // Context and extra take precedence over the channel, matching the previous merge order
        if ($this->includeChannel && !\array_key_exists('channel', $attributes)) {
            $attributes['channel'] = $record['channel'];
        }

        return $attributes;
    }
}
