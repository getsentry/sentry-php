<?php

declare(strict_types=1);

namespace Sentry\Logs;

/**
 * @phpstan-import-type AttributeValue from LogAttribute
 * @phpstan-import-type AttributeSerialized from LogAttribute
 *
 * @phpstan-type LogEnvelopeItem array{
 *     timestamp: int|float,
 *     trace_id: string,
 *     level: string,
 *     body: string,
 *     attributes: array<string, LogAttribute>
 * }
 */
class Log implements \JsonSerializable
{
    /**
     * @var float
     */
    private $timestamp;

    /**
     * @var string
     */
    private $traceId;

    /**
     * @var LogLevel
     */
    private $level;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array<string, LogAttribute>
     */
    private $attributes = [];

    public function __construct(
        float $timestamp,
        string $traceId,
        LogLevel $level,
        string $body
    ) {
        $this->timestamp = $timestamp;
        $this->traceId = $traceId;
        $this->level = $level;
        $this->body = $body;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(string $key, $value): self
    {
        $attribute = $value instanceof LogAttribute
            ? $value
            : LogAttribute::tryFromValue($value);

        if ($attribute !== null) {
            $this->attributes[$key] = $attribute;
        }

        return $this;
    }

    /**
     * @return array<string, LogAttribute>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return LogEnvelopeItem
     */
    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'trace_id' => $this->traceId,
            'level' => (string) $this->level,
            'body' => $this->body,
            'attributes' => $this->attributes,
        ];
    }
}
