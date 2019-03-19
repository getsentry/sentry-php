<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class represents an enum of severity levels an event can be associated
 * to.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Severity
{
    /**
     * This constant represents the "debug" severity level.
     */
    public const DEBUG = 'debug';

    /**
     * This constant represents the "info" severity level.
     */
    public const INFO = 'info';

    /**
     * This constant represents the "warning" severity level.
     */
    public const WARNING = 'warning';

    /**
     * This constant represents the "error" severity level.
     */
    public const ERROR = 'error';

    /**
     * This constant represents the "fatal" severity level.
     */
    public const FATAL = 'fatal';

    /**
     * This constant contains the list of allowed enum values.
     */
    public const ALLOWED_SEVERITIES = [
        self::DEBUG,
        self::INFO,
        self::WARNING,
        self::ERROR,
        self::FATAL,
    ];

    /**
     * @var string The value of this enum instance
     */
    private $value;

    /**
     * Constructor.
     *
     * @param string $value The value this instance represents
     */
    public function __construct(string $value = self::INFO)
    {
        if (!\in_array($value, self::ALLOWED_SEVERITIES, true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" is not a valid enum value.', $value));
        }

        $this->value = $value;
    }

    /**
     * Translate a PHP Error constant into a Sentry log level group.
     *
     * @param int $severity PHP E_* error constant
     *
     * @return Severity
     */
    public static function fromError(int $severity): self
    {
        switch ($severity) {
             case E_DEPRECATED:
             case E_USER_DEPRECATED:
             case E_WARNING:
             case E_USER_WARNING:
                 return self::warning();
             case E_ERROR:
             case E_PARSE:
             case E_CORE_ERROR:
             case E_CORE_WARNING:
             case E_COMPILE_ERROR:
             case E_COMPILE_WARNING:
                 return self::fatal();
             case E_RECOVERABLE_ERROR:
             case E_USER_ERROR:
                 return self::error();
             case E_NOTICE:
             case E_USER_NOTICE:
             case E_STRICT:
                 return self::info();
             default:
                 return self::error();
         }
    }

    /**
     * Creates a new instance of this enum for the "debug" value.
     *
     * @return self
     */
    public static function debug(): self
    {
        return new self(self::DEBUG);
    }

    /**
     * Creates a new instance of this enum for the "info" value.
     *
     * @return self
     */
    public static function info(): self
    {
        return new self(self::INFO);
    }

    /**
     * Creates a new instance of this enum for the "warning" value.
     *
     * @return self
     */
    public static function warning(): self
    {
        return new self(self::WARNING);
    }

    /**
     * Creates a new instance of this enum for the "error" value.
     *
     * @return self
     */
    public static function error(): self
    {
        return new self(self::ERROR);
    }

    /**
     * Creates a new instance of this enum for the "fatal" value.
     *
     * @return self
     */
    public static function fatal(): self
    {
        return new self(self::FATAL);
    }

    /**
     * Returns whether two object instances of this class are equal.
     *
     * @param self $other The object to compare
     *
     * @return bool
     */
    public function isEqualTo(self $other): bool
    {
        return $this->value === (string) $other;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
