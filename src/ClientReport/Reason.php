<?php

declare(strict_types=1);

namespace Sentry\ClientReport;

class Reason
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var array<self>
     */
    private static $instances = [];

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function queueOverflow(): self
    {
        return self::getInstance('queue_overflow');
    }

    public static function cacheOverflow(): self
    {
        return self::getInstance('cache_overflow');
    }

    public static function bufferOverflow(): self
    {
        return self::getInstance('buffer_overflow');
    }

    public static function ratelimitBackoff(): self
    {
        return self::getInstance('ratelimit_backoff');
    }

    public static function networkError(): self
    {
        return self::getInstance('network_error');
    }

    public static function sampleRate(): self
    {
        return self::getInstance('sample_rate');
    }

    public static function beforeSend(): self
    {
        return self::getInstance('before_send');
    }

    public static function eventProcessor(): self
    {
        return self::getInstance('event_processor');
    }

    public static function sendError(): self
    {
        return self::getInstance('send_error');
    }

    public static function internalSdkError(): self
    {
        return self::getInstance('internal_sdk_error');
    }

    public static function insufficientData(): self
    {
        return self::getInstance('insufficient_data');
    }

    public static function backpressure(): self
    {
        return self::getInstance('backpressure');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value;
    }

    private static function getInstance(string $value): self
    {
        if (!isset(self::$instances[$value])) {
            self::$instances[$value] = new self($value);
        }

        return self::$instances[$value];
    }
}
