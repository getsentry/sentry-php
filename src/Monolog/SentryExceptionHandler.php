<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This Monolog handler will collect monolog events and send them to sentry.
 */
class SentryExceptionHandler extends AbstractHandler
{
    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->hub = $hub;

        parent::__construct($level, $bubble);
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function isHandling($record): bool
    {
        /** @var LogRecord $record */
        return parent::isHandling($record);
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    public function handle($record): bool
    {
        $exception = $this->getExceptionFromRecord($record);

        if ($exception === null || !$this->isHandling($record)) {
            return false;
        }

        $this->hub->withScope(function (Scope $scope) use ($record, $exception): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);
            $scope->setExtra('monolog.message', $record['message']);

            $monologContextData = $this->getMonologContextData($this->getContextFromRecord($record));

            if ($monologContextData !== []) {
                $scope->setExtra('monolog.context', $monologContextData);
            }

            $monologExtraData = $this->getExtraFromRecord($record);

            if ($monologExtraData !== []) {
                $scope->setExtra('monolog.extra', $monologExtraData);
            }

            $this->hub->captureException($exception);
        });

        return $this->bubble === false;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    private function getExceptionFromRecord($record): ?\Throwable
    {
        $exception = $this->getContextFromRecord($record)['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            return $exception;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     *
     * @return array<string, mixed>
     */
    private function getContextFromRecord($record): array
    {
        return $this->getArrayFieldFromRecord($record, 'context');
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     *
     * @return array<string, mixed>
     */
    private function getExtraFromRecord($record): array
    {
        return $this->getArrayFieldFromRecord($record, 'extra');
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

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function getMonologContextData(array $context): array
    {
        unset($context['exception']);

        return $context;
    }
}
