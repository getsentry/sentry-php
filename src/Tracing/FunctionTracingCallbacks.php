<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Integration\FunctionTracingIntegration;
use Sentry\SentrySdk;

/**
 * @internal
 */
final class FunctionTracingCallbacks
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{Span, Span}|null
     */
    public static function handleStart(array $data): ?array
    {
        $hub = SentrySdk::getCurrentHub();

        if (!$hub->getIntegration(FunctionTracingIntegration::class) instanceof FunctionTracingIntegration) {
            return null;
        }

        $parentSpan = $hub->getSpan();
        if ($parentSpan === null || $parentSpan->getSampled() !== true) {
            return null;
        }

        if (!isset($data['name']) || !\is_string($data['name'])) {
            return null;
        }

        $context = self::createSpanContext($data['name'], $data);
        $childSpan = $parentSpan->startChild($context);

        $hub->setSpan($childSpan);

        return [$childSpan, $parentSpan];
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed                $callbackState
     */
    public static function handleEnd(array $data, $callbackState = null): void
    {
        if (!\is_array($callbackState)
            || !\array_key_exists(0, $callbackState)
            || !\array_key_exists(1, $callbackState)
            || !$callbackState[0] instanceof Span
            || !$callbackState[1] instanceof Span
        ) {
            return;
        }

        if (!isset($data['end_time']) || (!\is_int($data['end_time']) && !\is_float($data['end_time']))) {
            return;
        }

        $callbackState[0]->finish((float) $data['end_time']);
        SentrySdk::getCurrentHub()->setSpan($callbackState[1]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createSpanContext(string $name, array $data): SpanContext
    {
        $metadata = [];
        if (isset($data['metadata']) && \is_array($data['metadata'])) {
            foreach (array_keys($data['metadata']) as $key) {
                if (\is_string($key)) {
                    $metadata[$key] = $data['metadata'][$key];
                }
            }
        }

        $description = $name;
        $op = 'function';
        $origin = 'auto.function.sentry_php_tracer';

        if (isset($metadata['sentry.description']) && \is_string($metadata['sentry.description'])) {
            $description = $metadata['sentry.description'];
            unset($metadata['sentry.description']);
        }

        if (isset($metadata['sentry.op']) && \is_string($metadata['sentry.op'])) {
            $op = $metadata['sentry.op'];
            unset($metadata['sentry.op']);
        }

        if (isset($metadata['sentry.origin']) && \is_string($metadata['sentry.origin'])) {
            $origin = $metadata['sentry.origin'];
            unset($metadata['sentry.origin']);
        }

        $context = SpanContext::make()
            ->setDescription($description)
            ->setOp($op)
            ->setOrigin($origin)
            ->setData($metadata);

        if (isset($data['start_time']) && (\is_int($data['start_time']) || \is_float($data['start_time']))) {
            $context->setStartTimestamp((float) $data['start_time']);
        }

        return $context;
    }
}
