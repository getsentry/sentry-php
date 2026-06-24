<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\NoOpClient;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
abstract class Scope
{
    /**
     * Maximum number of flags allowed. We only track the first flags set.
     *
     * @internal
     */
    public const MAX_FLAGS = 100;

    /**
     * @internal
     *
     * @var ScopeData
     */
    protected $scopeData;

    /**
     * @var callable[] List of event processors
     *
     * @phpstan-var array<callable(Event, EventHint): ?Event>
     */
    protected static $globalEventProcessors = [];

    /**
     * @var callable|null
     */
    protected static $externalPropagationContextCallback;

    public function __construct()
    {
        $this->scopeData = new ScopeData();
        $this->scopeData->setClient(new NoOpClient());
    }

    /**
     * Returns the client bound to this scope.
     */
    public function getClient(): ClientInterface
    {
        return $this->scopeData->getClient();
    }

    /**
     * Adds a new event processor that will be called after {@see MergedScope::applyToEvent}
     * finished its work.
     *
     * @param callable $eventProcessor The event processor
     */
    public static function addGlobalEventProcessor(callable $eventProcessor): void
    {
        self::$globalEventProcessors[] = $eventProcessor;
    }

    public static function registerExternalPropagationContext(callable $callback): void
    {
        self::$externalPropagationContextCallback = $callback;
    }

    public static function clearExternalPropagationContext(): void
    {
        self::$externalPropagationContextCallback = null;
    }

    /**
     * @return array{trace_id: string, span_id: string}|null
     */
    public static function getExternalPropagationContext(): ?array
    {
        $callback = self::$externalPropagationContextCallback;
        if (!\is_callable($callback)) {
            return null;
        }

        try {
            $context = $callback();
        } catch (\Throwable $exception) {
            return null;
        }

        if (!\is_array($context)) {
            return null;
        }

        $traceId = $context['trace_id'] ?? null;
        $spanId = $context['span_id'] ?? null;

        if (!\is_string($traceId) || preg_match('/^[0-9a-f]{32}$/i', $traceId) !== 1) {
            return null;
        }

        if (!\is_string($spanId) || preg_match('/^[0-9a-f]{16}$/i', $spanId) !== 1) {
            return null;
        }

        return [
            'trace_id' => $traceId,
            'span_id' => $spanId,
        ];
    }
}
