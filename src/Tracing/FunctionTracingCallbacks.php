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

        /**
         * @var float $endTime
         */
        $endTime = $data['end_time'];

        $callbackState->getCurrentSpan()->finish($endTime);
        SentrySdk::getCurrentHub()->setSpan($callbackState->getParentSpan());
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createSpanContext(array $data): SpanContext
    {
        $metadata = $data['metadata'];
        $description = self::extractAndUnset($metadata, 'description');
        if ($description === null) {
            /** @var string $name */
            $name = $data['name'];
            $description = $name;
        }
        $op = self::extractAndUnset($metadata, 'op') ?? 'function';
        $origin = self::extractAndUnset($metadata, 'origin') ?? 'auto.php.tracer';
        /** @var float $startTime */
        $startTime = $data['start_time'];

        return SpanContext::make()
            ->setDescription($description)
            ->setOp($op)
            ->setOrigin($origin)
            ->setData($metadata)
            ->setStartTimestamp($startTime);
    }

    /**
     * Attempts to extract the value using the specified key and unsets it from
     * the source array.
     * It will only perform extraction if the value is a string or can be converted
     * to a string (using __toString).
     *
     * @param array<string, mixed> $data
     *
     * @return string|null Returns null on failed extraction
     */
    private static function extractAndUnset(array &$data, string $key): ?string
    {
        if (isset($data[$key])) {
            /** @var object|string $value */
            $value = $data[$key];
            if (\is_string($value)) {
                unset($data[$key]);

                return $value;
            } elseif (method_exists($value, '__toString')) {
                unset($data[$key]);

                return (string) $value;
            }
        }

        return null;
    }
}
