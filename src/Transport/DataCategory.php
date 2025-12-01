<?php

namespace Sentry\Transport;

class DataCategory
{

    /**
     * @var string
     */
    private $value;

    private static $instances = [];

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function error(): self
    {
        return self::getInstance('error');
    }

    public static function transaction(): self
    {
        return self::getInstance('transaction');
    }

    // TODO: not sure if this should be called monitor or checkIn.
    public static function checkIn(): self
    {
        return self::getInstance('monitor');
    }

    public static function logItem(): self
    {
        return self::getInstance('log_item');
    }

    public static function logBytes(): self
    {
        return self::getInstance('log_bytes');
    }

    public static function profile(): self
    {
        return self::getInstance('profile');
    }

    public static function metric(): self
    {
        return self::getInstance('trace_metric');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value;
    }

    private static function getInstance(string $value)
    {
        if (!isset(self::$instances[$value])) {
            self::$instances[$value] = new self($value);
        }

        return self::$instances[$value];
    }
}
