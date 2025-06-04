<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Attributes\Attribute;
use Sentry\Attributes\AttributeBag;

/**
 * @phpstan-import-type AttributeSerialized from Attribute
 *
 * @phpstan-type LogEnvelopeItem array{
 *     timestamp: int|float,
 *     trace_id: string,
 *     level: string,
 *     body: string,
 *     attributes: array<string, AttributeSerialized>
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
     * @var AttributeBag
     */
    private $attributes;

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
        $this->attributes = new AttributeBag();
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function setTimestamp(float $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function setTraceId(string $traceId): self
    {
        $this->traceId = $traceId;

        return $this;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function setLevel(LogLevel $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function attributes(): AttributeBag
    {
        return $this->attributes;
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes->set($key, $value);

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
            'attributes' => $this->attributes->toArray(),
        ];
    }
}
