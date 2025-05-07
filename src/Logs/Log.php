<?php

declare(strict_types=1);

namespace Sentry\Logs;

/**
 * @phpstan-type LogAttribute array{
 *     type: string,
 *     value: mixed
 * }
 * @phpstan-type LogAttributes array<string, LogAttribute>
 * @phpstan-type LogEnvelopeItem array{
 *     timestamp: int|float,
 *     trace_id: string,
 *     level: string,
 *     body: string,
 *     attributes: LogAttributes
 * }
 */
class Log implements \JsonSerializable
{
    /**
     * @var int
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
     * @var LogAttributes
     */
    private $attributes;

    /**
     * @param LogAttributes $attributes
     */
    public function __construct(
        int $timestamp,
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
     * @param mixed $value
     */
    public function setAttribute(string $key, $value, string $type = 'string'): self
    {
        $this->attributes[$key] = [
            'type' => $type,
            'value' => $value,
        ];

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
