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
     * @var array<string, string>
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
     * @param array<string, string> $tags
     */
    public function __construct(string $key, MetricsUnit $unit, array $tags, int $timestamp)
    {
        $this->key = $key;
        $this->unit = $unit;
        $this->tags = $tags;
        $this->timestamp = $timestamp;
    }

    abstract public function add($value): void;

    /**
     * @return array<array-key, int|float|string>
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
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @phpstan-assert-if-true !null $this->getCodeLocation()
     */
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
        if ($client === null) {
            return;
        }

        $options = $client->getOptions();

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3 + $stackLevel);
        $frame = end($backtrace);

        // If we don't have a valid frame there is no code location to resolve
        if ($frame === false || empty($frame['file']) || empty($frame['line'])) {
            return;
        }

        $frameBuilder = new FrameBuilder($options, new RepresentationSerializer($options));
        $this->codeLocation = $frameBuilder->buildFromBacktraceFrame($frame['file'], $frame['line'], $frame);
    }

    public function getMri(): string
    {
        return sprintf(
            '%s:%s@%s',
            $this->getType(),
            $this->getKey(),
            (string) $this->getUnit()
        );
    }
}
