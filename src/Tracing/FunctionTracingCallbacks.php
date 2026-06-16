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
     */
    public static function handleStart(array $data): ?FunctionTracingState
    {
        $hub = SentrySdk::getCurrentHub();

        if (!$hub->getIntegration(FunctionTracingIntegration::class) instanceof FunctionTracingIntegration) {
            return null;
        }

        $parentSpan = $hub->getSpan();
        if ($parentSpan === null || $parentSpan->getSampled() !== true) {
            return null;
        }

        $context = self::createSpanContext($data);
        $childSpan = $parentSpan->startChild($context);

        $hub->setSpan($childSpan);

        return new FunctionTracingState($parentSpan, $childSpan);
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed                $callbackState
     */
    public static function handleEnd(array $data, $callbackState = null): void
    {
        if (!$callbackState instanceof FunctionTracingState) {
            return;
        }

        $callbackState->getCurrentSpan()->finish($data['end_time']);
        SentrySdk::getCurrentHub()->setSpan($callbackState->getParentSpan());
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createSpanContext(array $data): SpanContext
    {
        $description = self::extractAndUnset($data, 'description') ?? $data['name'] ?? null;
        $op = self::extractAndUnset($data, 'op') ?? 'function';
        $origin = self::extractAndUnset($data, 'origin') ?? 'auto.php.tracer';

        return SpanContext::make()
            ->setDescription($description)
            ->setOp($op)
            ->setOrigin($origin)
            ->setData($data)
            ->setStartTimestamp($data['start_time']);
    }

    /**
     * Attempts to extract the value using the specified key and unsets it from
     * the source array.
     * It will only perform extraction if the value is a string or can be converted
     * to a string (using __toString).
     *
     * @return null on failed extraction
     */
    private static function extractAndUnset(&$data, string $key): ?string {
        if (isset($data[$key])) {
            $value = $data[$key];
            if (\is_string($value)) {
                unset($data[$key]);
                return $value;
            } else if (method_exists($value, '__toString')) {
                unset($data[$key]);
                return (string) $value;
            }
        }
        return null;
    }
}
