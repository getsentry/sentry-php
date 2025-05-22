<?php

declare(strict_types=1);

namespace Sentry\Logs;

/**
 * @see: https://develop.sentry.dev/sdk/telemetry/logs/#log-severity-level
 */
class LogLevel
{
    /**
     * @var string The value of the enum instance
     */
    private $value;

    /**
     * @var array<string, self> A list of cached enum instances
     */
    private static $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function trace(): self
    {
        return self::getInstance('trace');
    }

    public static function debug(): self
    {
        return self::getInstance('debug');
    }

    public static function info(): self
    {
        return self::getInstance('info');
    }

    public static function warn(): self
    {
        return self::getInstance('warn');
    }

    public static function error(): self
    {
        return self::getInstance('error');
    }

    public static function fatal(): self
    {
        return self::getInstance('fatal');
    }

    public function __toString(): string
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
