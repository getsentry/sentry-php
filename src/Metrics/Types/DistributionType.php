<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Metrics\MetricsUnit;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

/**
 * @internal
 */
final class DistributionType extends AbstractType
{
    /**
     * @var string
     */
    public const TYPE = 'distribution';

    /**
     * @var int|float
     */
    private $value;

    /**
     * @param int|float            $value
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        string $name,
        $value,
        MetricsUnit $unit,
        TraceId $traceId,
        SpanId $spanId,
        array $attributes,
        float $timestamp
    ) {
        parent::__construct($name, $unit, $traceId, $spanId, $attributes, $timestamp);

        $this->value = (float) $value;
    }

    /**
     * @param int|float $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
