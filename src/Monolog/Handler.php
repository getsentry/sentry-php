<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Level;

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
        if ($record instanceof LogRecord) {
            $level = $record->level;
            $message = $record->message;
            $channel = $record->channel;
            $context = $record->context;
            $extra = $record->extra;
            $levelName = $record->level->getName();
        } else {
            /** @var int|Level $level */
            $level = $record['level'];
            /** @var string $message */
            $message = $record['message'];
            /** @var string $channel */
            $channel = $record['channel'];
            /** @var array<string, mixed> $context */
            $context = $record['context'] ?? [];
            /** @var array<string, mixed> $extra */
            $extra = $record['extra'] ?? [];
            /** @var string $levelName */
            $levelName = $record['level_name'] ?? '';
        }

        $event = Event::createEvent();
        $event->setLevel(self::getSeverityFromLevel($level instanceof Level ? $level->value : $level));
        $event->setMessage($message);
        $event->setLogger(\sprintf('monolog.%s', $channel));

        $hint = new EventHint();

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $hint->exception = $context['exception'];
        }

        $this->hub->withScope(function (Scope $scope) use ($channel, $levelName, $context, $extra, $event, $hint): void {
            $scope->setExtra('monolog.channel', $channel);
            $scope->setExtra('monolog.level', $levelName);

            $monologContextData = $this->getMonologContextData($context);

            if ($monologContextData !== []) {
                $scope->setExtra('monolog.context', $monologContextData);
            }

            $monologExtraData = $this->getMonologExtraData($extra);

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

        $contextData = [];

        foreach ($context as $key => $value) {
            // We skip the `exception` field because it goes in its own context
            if ($key === self::CONTEXT_EXCEPTION_KEY) {
                continue;
            }

            $contextData[$key] = $value;
        }

        return $contextData;
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
}
