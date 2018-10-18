<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public const SEVERITY_DEBUG = 'debug';

    /**
     * This constant represents the "info" severity level.
     */
    public const SEVERITY_INFO = 'info';

    /**
     * This constant represents the "warning" severity level.
     */
    public const SEVERITY_WARNING = 'warning';

    /**
     * This constant represents the "error" severity level.
     */
    public const SEVERITY_ERROR = 'error';

    /**
     * This constant represents the "fatal" severity level.
     */
    public const SEVERITY_FATAL = 'fatal';

    /**
     * This constant contains the list of allowed enum values.
     */
    public const ALLOWED_SEVERITIES = [
        self::SEVERITY_DEBUG,
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_ERROR,
        self::SEVERITY_FATAL,
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
    private function __construct(string $value = self::SEVERITY_INFO)
    {
        if (!\in_array($value, self::ALLOWED_SEVERITIES, true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" is not a valid enum value.', $value));
        }

        $this->value = $value;
    }

    /**
     * Creates a new instance of this class with the given severity value.
     *
     * @param string $severity The severity
     *
     * @return self
     */
    public static function fromString(string $severity): self
    {
        return new self($severity);
    }

    /**
     * Creates a new instance of this enum for the "debug" value.
     *
     * @return self
     */
    public static function debug(): self
    {
        return new self(self::SEVERITY_DEBUG);
    }

    /**
     * Creates a new instance of this enum for the "info" value.
     *
     * @return self
     */
    public static function info(): self
    {
        return new self(self::SEVERITY_INFO);
    }

    /**
     * Creates a new instance of this enum for the "warning" value.
     *
     * @return self
     */
    public static function warning(): self
    {
        return new self(self::SEVERITY_WARNING);
    }

    /**
     * Creates a new instance of this enum for the "error" value.
     *
     * @return self
     */
    public static function error(): self
    {
        return new self(self::SEVERITY_ERROR);
    }

    /**
     * Creates a new instance of this enum for the "fatal" value.
     *
     * @return self
     */
    public static function fatal(): self
    {
        return new self(self::SEVERITY_FATAL);
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

    public function __toString(): string
    {
        return $this->value;
    }
}
