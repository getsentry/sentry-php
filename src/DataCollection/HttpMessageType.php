<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class HttpMessageType
{
    public const INCOMING_REQUEST = 'incomingRequest';
    public const OUTGOING_REQUEST = 'outgoingRequest';
    public const INCOMING_RESPONSE = 'incomingResponse';
    public const OUTGOING_RESPONSE = 'outgoingResponse';

    public const TYPES = [
        self::INCOMING_REQUEST,
        self::OUTGOING_REQUEST,
        self::INCOMING_RESPONSE,
        self::OUTGOING_RESPONSE,
    ];

    /**
     * @var string
     */
    private $value;

    /**
     * @var array<string, self>
     */
    private static $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function incomingRequest(): self
    {
        return self::from(self::INCOMING_REQUEST);
    }

    public static function outgoingRequest(): self
    {
        return self::from(self::OUTGOING_REQUEST);
    }

    public static function incomingResponse(): self
    {
        return self::from(self::INCOMING_RESPONSE);
    }

    public static function outgoingResponse(): self
    {
        return self::from(self::OUTGOING_RESPONSE);
    }

    public static function from(string $value): self
    {
        if (!isset(self::$instances[$value])) {
            if (!\in_array($value, self::TYPES, true)) {
                throw new \InvalidArgumentException(\sprintf('Invalid HTTP message type "%s".', $value));
            }

            self::$instances[$value] = new self($value);
        }

        return self::$instances[$value];
    }

    public function isRequest(): bool
    {
        return $this === self::incomingRequest() || $this === self::outgoingRequest();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
