<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Breadcrumbs;

use Raven\Client;
use Raven\Exception\InvalidArgumentException;

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
    const TYPE_HTTP = 'http';

    /**
     * This constant defines the user breadcrumb type.
     */
    const TYPE_USER = 'user';

    /**
     * This constant defines the navigation breadcrumb type.
     */
    const TYPE_NAVIGATION = 'navigation';

    /**
     * This constant defines the error breadcrumb type.
     */
    const TYPE_ERROR = 'error';

    /**
     * @var string The category of the breadcrumb
     */
    private $category;

    /**
     * @var string The type of breadcrumb
     */
    private $type;

    /**
     * @var string The message of the breadcrumb
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
     * @param array       $metaData Additional information about the breadcrumb
     */
    public function __construct($level, $type, $category, $message = null, array $metaData = [])
    {
        if (!in_array($level, self::getLevels(), true)) {
            throw new InvalidArgumentException('The value of the $level argument must be one of the Raven\Client::LEVEL_* constants.');
        }

        $this->type = $type;
        $this->level = $level;
        $this->category = $category;
        $this->message = $message;
        $this->metadata = $metaData;
        $this->timestamp = microtime(true);
    }

    /**
     * Creates a new instance of this class configured with the given params.
     *
     * @param string      $level    The error level of the breadcrumb
     * @param string      $type     The type of the breadcrumb
     * @param string      $category The category of the breadcrumb
     * @param string|null $message  Optional text message
     * @param array       $metaData Additional information about the breadcrumb
     *
     * @return static
     */
    public static function create($level, $type, $category, $message = null, array $metaData = [])
    {
        return new static($level, $type, $category, $message, $metaData);
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
     * Sets the type of the breadcrumb
     *
     * @param string $type The type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
     * @return $this
     */
    public function setLevel($level)
    {
        if (!in_array($level, self::getLevels(), true)) {
            throw new InvalidArgumentException('The value of the $level argument must be one of the Raven\Client::LEVEL_* constants.');
        }

        $this->level = $level;

        return $this;
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
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Gets the breadcrumb message.
     *
     * @return string
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
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
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
     * Sets the breadcrumb meta data.
     *
     * @param array $metadata The meta data
     *
     * @return $this
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;

        return $this;
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
     * @param float $timestamp The timestamp.
     *
     * @return $this
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
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

    /**
     * Gets the list of allowed breadcrumb error levels.
     *
     * @return string[]
     */
    private static function getLevels()
    {
        return [
            Client::LEVEL_DEBUG,
            Client::LEVEL_INFO,
            Client::LEVEL_WARNING,
            Client::LEVEL_ERROR,
            Client::LEVEL_FATAL,
        ];
    }
}
