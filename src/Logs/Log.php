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
    private $attributes;

    /**
     * @param array<string, LogAttribute> $attributes
     */
    public function __construct(
        float $timestamp,
        string $traceId,
        LogLevel $level,
        string $body,
        array $attributes = []
    ) {
        $this->timestamp = $timestamp;
        $this->traceId = $traceId;
        $this->level = $level;
        $this->body = $body;
        $this->attributes = $attributes;
    }

    /**
     * @param AttributeValue $value
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = LogAttribute::fromValue($value);

        return $this;
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
