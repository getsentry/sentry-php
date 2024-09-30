<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This Monolog handler logs every message to a Sentry's server using the given
 * hub instance.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Handler extends AbstractProcessingHandler
{
    use CompatibilityProcessingHandlerTrait;

    private const CONTEXT_EXCEPTION_KEY = 'exception';

    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @var bool
     */
    private $fillExtraContext;

    /**
     * {@inheritdoc}
     *
     * @param HubInterface $hub The hub to which errors are reported
     */
    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true, bool $fillExtraContext = false)
    {
        parent::__construct($level, $bubble);

        $this->hub = $hub;
        $this->fillExtraContext = $fillExtraContext;
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

        if ($this->hasExceptionContext($record['context'])) {
            $hint->exception = $record['context']['exception'];
        }

        $this->hub->withScope(function (Scope $scope) use ($record, $event, $hint): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            $monologContextData = $this->getMonologContextData($record['context']);

            if ($monologContextData !== []) {
                $scope->setExtra('monolog.context', $monologContextData);
            }

            $monologExtraData = $this->getMonologExtraData($record['extra']);

            if ($monologExtraData !== []) {
                $scope->setExtra('monolog.extra', $monologExtraData);
            }

            $this->hub->captureEvent($event, $hint);
        });
    }

    /**
     * @param mixed[] $context
     *
     * @return mixed[]
     */
    private function getMonologContextData(array $context): array
    {
        if (!$this->fillExtraContext) {
            return [];
        }

        if ($this->hasExceptionContext($context)) {
            // remove the exception from the context, as it's set on the hint
            unset($context[self::CONTEXT_EXCEPTION_KEY]);
        }

        return $context;
    }

    /**
     * @param mixed[] $context
     *
     * @return mixed[]
     */
    private function getMonologExtraData(array $context): array
    {
        if (!$this->fillExtraContext) {
            return [];
        }

        $extraData = [];

        foreach ($context as $key => $value) {
            $extraData[$key] = $value;
        }

        return $extraData;
    }

    /**
     * @param mixed[] $context
     */
    private function hasExceptionContext(array $context): bool
    {
        return isset($context[self::CONTEXT_EXCEPTION_KEY]) && $context[self::CONTEXT_EXCEPTION_KEY] instanceof \Throwable;
    }
}
