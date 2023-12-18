<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Frame;
use Sentry\FrameBuilder;
use Sentry\Metrics\MetricsUnit;
use Sentry\SentrySdk;
use Sentry\Serializer\RepresentationSerializer;

/**
 * @internal
 */
abstract class AbstractType
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var MetricsUnit
     */
    private $unit;

    /**
     * @var string
     */
    private $tags;

    /**
     * @var int
     */
    private $timestamp;

    /**
     * @var Frame
     */
    private $codeLocation;

    public function __construct(string $key, MetricsUnit $unit, string $tags, int $timestamp)
    {
        $this->key = $key;
        $this->unit = $unit;
        $this->tags = $tags;
        $this->timestamp = $timestamp;
    }

    /**
     * @param mixed $value
     */
    abstract public function add($value): void;

    abstract public function serialize(): array;

    public function getKey(): string
    {
        return $this->key;
    }

    public function getUnit(): MetricsUnit
    {
        return $this->unit;
    }

    public function getTags(): string
    {
        return $this->tags;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function hasCodeLocation(): bool
    {
        return $this->codeLocation !== null;
    }

    public function addCodeLocation(int $stackLevel): void
    {
        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3 + $stackLevel);
        $frame = end($backtrace);

        $hub = SentrySdk::getCurrentHub();
        $options = $hub->getClient()->getOptions();

        $frameBuilder = new FrameBuilder($options, new RepresentationSerializer($options));
        $this->codeLocation = $frameBuilder->buildFromBacktraceFrame($frame['file'], $frame['line'], $frame);
    }
}
