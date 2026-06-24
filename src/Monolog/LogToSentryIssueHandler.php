<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\EventCapturer;
use Sentry\State\IsolationScope;

use function Sentry\withIsolationScope;

/**
 * This Monolog handler captures log messages as Sentry issues.
 */
class LogToSentryIssueHandler extends AbstractProcessingHandler
{
    use CompatibilityProcessingHandlerTrait;

    private const CONTEXT_EXCEPTION_KEY = 'exception';

    /**
     * @var bool
     */
    private $fillExtraContext;

    /**
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function __construct($level = Logger::DEBUG, bool $bubble = true, bool $fillExtraContext = false)
    {
        $this->fillExtraContext = $fillExtraContext;

        parent::__construct($level, $bubble);
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function handle($record): bool
    {
        /** @phpstan-ignore-next-line */
        if (!$this->isHandling($record) || $this->hasThrowable($record)) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return parent::handle($record);
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    protected function doWrite($record): void
    {
        $event = Event::createEvent();
        $event->setLevel(self::getSeverityFromLevel($record['level']));
        $event->setMessage($record['message']);
        $event->setLogger(\sprintf('monolog.%s', $record['channel']));

        $hint = new EventHint();

        withIsolationScope(function (IsolationScope $scope) use ($record, $event, $hint): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            if ($this->fillExtraContext) {
                $monologContextData = $this->getArrayFieldFromRecord($record, 'context');

                if ($monologContextData !== []) {
                    $scope->setExtra('monolog.context', $monologContextData);
                }

                $monologExtraData = $this->getArrayFieldFromRecord($record, 'extra');

                if ($monologExtraData !== []) {
                    $scope->setExtra('monolog.extra', $monologExtraData);
                }
            }

            EventCapturer::captureEvent($event, $hint);
        });
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    private function hasThrowable($record): bool
    {
        $exception = $this->getArrayFieldFromRecord($record, 'context')[self::CONTEXT_EXCEPTION_KEY] ?? null;

        return $exception instanceof \Throwable;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     *
     * @return array<string, mixed>
     */
    private function getArrayFieldFromRecord($record, string $field): array
    {
        if (isset($record[$field]) && \is_array($record[$field])) {
            return $record[$field];
        }

        return [];
    }
}
