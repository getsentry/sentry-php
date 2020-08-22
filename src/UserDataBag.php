<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class stores the information about the authenticated user for a request.
 *
 * @phpstan-implements \ArrayAccess<string, mixed>
 */
final class UserDataBag implements \ArrayAccess
{
    /**
     * @var string|null The unique ID of the user
     */
    private $id;

    /**
     * @var string|null The email address of the user
     */
    private $email;

    /**
     * @var string|null The IP of the user
     */
    private $ipAddress;

    /**
     * @var string|null The username of the user
     */
    private $username;

    /**
     * @var array<string, mixed> Additional data
     */
    private $metadata = [];

    private function __construct()
    {
    }

    public static function createFromUserIdentifier(string $id): self
    {
        $instance = new self();
        $instance->setId($id);

        return $instance;
    }

    public static function createFromUserIpAddress(string $ipAddress): self
    {
        $instance = new self();
        $instance->setIpAddress($ipAddress);

        return $instance;
    }

    /**
     * Gets the ID of the user.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Sets the ID of the user.
     *
     * @param string|null $id The ID
     */
    public function setId(?string $id): void
    {
        if (null === $id && null === $this->ipAddress) {
            throw new \BadMethodCallException('Either the IP address or the ID must be set.');
        }

        $this->id = $id;
    }

    /**
     * Gets the username of the user.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Sets the username of the user.
     *
     * @param string|null $username The username
     */
    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    /**
     * Gets the email of the user.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the email of the user.
     *
     * @param string|null $email The email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * Gets the ip address of the user.
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Sets the ip address of the user.
     *
     * @param string|null $ipAddress The ip address
     */
    public function setIpAddress(?string $ipAddress): void
    {
        if (null !== $ipAddress && false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('The "%s" value is not a valid IP address.', $ipAddress));
        }

        if (null === $ipAddress && null === $this->id) {
            throw new \BadMethodCallException('Either the IP address or the ID must be set.');
        }

        $this->ipAddress = $ipAddress;
    }

    /**
     * Gets additional metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Merges the given context with this one.
     *
     * @param UserDataBag $other The context to merge the data with
     *
     * @return $this
     */
    public function merge(self $other): self
    {
        $this->id = $other->id;
        $this->email = $other->email;
        $this->ipAddress = $other->ipAddress;
        $this->username = $other->username;
        $this->metadata = array_merge($this->metadata, $other->metadata);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->metadata[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->metadata[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        unset($this->metadata[$offset]);
    }
}
