<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Breadcrumbs;

use Sentry\Exception\InvalidArgumentException;

/**
 * This class stores all the informations about a breadcrumb.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Breadcrumb implements \JsonSerializable
{
    /**
     * This constant defines the http breadcrumb type.
     */
    public const TYPE_HTTP = 'http';

    /**
     * This constant defines the user breadcrumb type.
     */
    public const TYPE_USER = 'user';

    /**
     * This constant defines the navigation breadcrumb type.
     */
    public const TYPE_NAVIGATION = 'navigation';

    /**
     * This constant defines the error breadcrumb type.
     */
    public const TYPE_ERROR = 'error';

    /**
     * This constant defines the debug level for a breadcrumb.
     */
    public const LEVEL_DEBUG = 'debug';

    /**
     * This constant defines the info level for a breadcrumb.
     */
    public const LEVEL_INFO = 'info';

    /**
     * This constant defines the warning level for a breadcrumb.
     */
    public const LEVEL_WARNING = 'warning';

    /**
     * This constant defines the error level for a breadcrumb.
     */
    public const LEVEL_ERROR = 'error';

    /**
     * This constant defines the critical level for a breadcrumb.
     */
    public const LEVEL_CRITICAL = 'critical';

    /**
     * This constant defines the list of values allowed to be set as severity
     * level of the breadcrumb.
     */
    private const ALLOWED_LEVELS = [
        self::LEVEL_DEBUG,
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
    ];

    /**
     * @var string The category of the breadcrumb
     */
    private $category;

    /**
     * @var string The type of breadcrumb
     */
    private $type;

    /**
     * @var string|null The message of the breadcrumb
     */
    private $message;

    /**
     * @var string The level of the breadcrumb
     */
    private $level;

    /**
     * @var array The meta data of the breadcrumb
     */
    private $metadata;

    /**
     * @var float The timestamp of the breadcrumb
     */
    private $timestamp;

    /**
     * Constructor.
     *
     * @param string      $level    The error level of the breadcrumb
     * @param string      $type     The type of the breadcrumb
     * @param string      $category The category of the breadcrumb
     * @param string|null $message  Optional text message
     * @param array       $metadata Additional information about the breadcrumb
     */
    public function __construct($level, $type, $category, $message = null, array $metadata = [])
    {
        if (!\in_array($level, self::ALLOWED_LEVELS, true)) {
            throw new InvalidArgumentException('The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.');
        }

        $this->type = $type;
        $this->level = $level;
        $this->category = $category;
        $this->message = $message;
        $this->metadata = $metadata;
        $this->timestamp = microtime(true);
    }

    /**
     * Creates a new instance of this class configured with the given params.
     *
     * @param string      $level    The error level of the breadcrumb
     * @param string      $type     The type of the breadcrumb
     * @param string      $category The category of the breadcrumb
     * @param string|null $message  Optional text message
     * @param array       $metadata Additional information about the breadcrumb
     *
     * @return static
     */
    public static function create($level, $type, $category, $message = null, array $metadata = [])
    {
        return new static($level, $type, $category, $message, $metadata);
    }

    /**
     * Maps the severity of the error to one of the levels supported by the
     * breadcrumbs.
     *
     * @param \ErrorException $exception The exception
     *
     * @return string
     */
    public static function levelFromErrorException(\ErrorException $exception): string
    {
        switch ($exception->getSeverity()) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return self::LEVEL_WARNING;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
                return self::LEVEL_CRITICAL;
            case E_USER_ERROR:
                return self::LEVEL_ERROR;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return self::LEVEL_INFO;
            default:
                return self::LEVEL_ERROR;
        }
    }

    /**
     * Gets the breadcrumb type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type of the breadcrumb.
     *
     * @param string $type The type
     *
     * @return static
     */
    public function withType($type)
    {
        if ($type === $this->type) {
            return $this;
        }

        $new = clone $this;
        $new->type = $type;

        return $new;
    }

    /**
     * Gets the breadcrumb level.
     *
     * @return string
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets the error level of the breadcrumb.
     *
     * @param string $level The level
     *
     * @return static
     */
    public function withLevel($level)
    {
        if (!\in_array($level, self::ALLOWED_LEVELS, true)) {
            throw new InvalidArgumentException('The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.');
        }

        if ($level === $this->level) {
            return $this;
        }

        $new = clone $this;
        $new->level = $level;

        return $new;
    }

    /**
     * Gets the breadcrumb category.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Sets the breadcrumb category.
     *
     * @param string $category The category
     *
     * @return static
     */
    public function withCategory($category)
    {
        if ($category === $this->category) {
            return $this;
        }

        $new = clone $this;
        $new->category = $category;

        return $new;
    }

    /**
     * Gets the breadcrumb message.
     *
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Sets the breadcrumb message.
     *
     * @param string $message The message
     *
     * @return static
     */
    public function withMessage($message)
    {
        if ($message === $this->message) {
            return $this;
        }

        $new = clone $this;
        $new->message = $message;

        return $new;
    }

    /**
     * Gets the breadcrumb meta data.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Returns an instance of this class with the provided metadata, replacing
     * any existing values of any metadata with the same name.
     *
     * @param string $name  The name of the metadata
     * @param mixed  $value The value
     *
     * @return static
     */
    public function withMetadata($name, $value)
    {
        if (isset($this->metadata[$name]) && $value === $this->metadata[$name]) {
            return $this;
        }

        $new = clone $this;
        $new->metadata[$name] = $value;

        return $new;
    }

    /**
     * Returns an instance of this class without the specified metadata
     * information.
     *
     * @param string $name The name of the metadata
     *
     * @return static|Breadcrumb
     */
    public function withoutMetadata($name)
    {
        if (!isset($this->metadata[$name])) {
            return $this;
        }

        $new = clone $this;

        unset($new->metadata[$name]);

        return $new;
    }

    /**
     * Gets the breadcrumb timestamp.
     *
     * @return float
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Sets the breadcrumb timestamp.
     *
     * @param float $timestamp the timestamp
     *
     * @return static
     */
    public function withTimestamp($timestamp)
    {
        if ($timestamp === $this->timestamp) {
            return $this;
        }

        $new = clone $this;
        $new->timestamp = $timestamp;

        return $new;
    }

    /**
     * Gets the breadcrumb as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'type' => $this->type,
            'category' => $this->category,
            'level' => $this->level,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'data' => $this->metadata,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
