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
     * @var string[]
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

    /**
     * @param string[] $tags
     */
    public function __construct(string $key, MetricsUnit $unit, array $tags, int $timestamp)
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

    /**
     * @return array<int, float|int>
     */
    abstract public function serialize(): array;

    abstract public function getType(): string;

    public function getKey(): string
    {
        return $this->key;
    }

    public function getUnit(): MetricsUnit
    {
        return $this->unit;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
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

    public function getCodeLocation(): ?Frame
    {
        return $this->codeLocation;
    }

    public function addCodeLocation(int $stackLevel): void
    {
        $client = SentrySdk::getCurrentHub()->getClient();
        if ($client !== null) {
            $options = $client->getOptions();

            $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3 + $stackLevel);
            $frame = end($backtrace);

            $frameBuilder = new FrameBuilder($options, new RepresentationSerializer($options));
            $this->codeLocation = $frameBuilder->buildFromBacktraceFrame($frame['file'], $frame['line'], $frame);
        }
    }

    public function getMri(): string
    {
        return sprintf(
            '%s:%s@%s',
            $this->getType(),
            $this->getKey(),
            $this->getUnit()
        );
    }
}
