<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class stores the information about the authenticated user for a request.
 */
final class UserDataBag
{
    /**
     * @var string|int|null The unique ID of the user
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

    /**
     * Creates an instance of this object from a user ID.
     *
     * @param string|int $id The ID of the user
     */
    public static function createFromUserIdentifier($id): self
    {
        $instance = new self();
        $instance->setId($id);

        return $instance;
    }

    /**
     * Creates an instance of this object from an IP address.
     *
     * @param string $ipAddress The IP address of the user
     */
    public static function createFromUserIpAddress(string $ipAddress): self
    {
        $instance = new self();
        $instance->setIpAddress($ipAddress);

        return $instance;
    }

    /**
     * Creates an instance of this object from the given data.
     *
     * @param array<string, mixed> $data The raw data
     */
    public static function createFromArray(array $data): self
    {
        if (!isset($data['id']) && !isset($data['ip_address'])) {
            throw new \InvalidArgumentException('Either the "id" or the "ip_address" field must be set.');
        }

        $instance = new self();

        foreach ($data as $field => $value) {
            switch ($field) {
                case 'id':
                    $instance->setId($data['id']);
                    break;
                case 'ip_address':
                    $instance->setIpAddress($data['ip_address']);
                    break;
                case 'email':
                    $instance->setEmail($data['email']);
                    break;
                case 'username':
                    $instance->setUsername($data['username']);
                    break;
                default:
                    $instance->setMetadata($field, $value);
                    break;
            }
        }

        return $instance;
    }

    /**
     * Gets the ID of the user.
     *
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the ID of the user.
     *
     * @param string|int|null $id The ID
     */
    public function setId($id): void
    {
        if (null === $id && null === $this->ipAddress) {
            throw new \BadMethodCallException('Either the IP address or the ID must be set.');
        }

        if (!\is_string($id) && !\is_int($id)) {
            throw new \UnexpectedValueException(sprintf('Expected an integer or string value for the $id argument. Got: "%s".', get_debug_type($id)));
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
     * Sets the given field in the additional metadata.
     *
     * @param string $name  The name of the field
     * @param mixed  $value The value
     */
    public function setMetadata(string $name, $value): void
    {
        $this->metadata[$name] = $value;
    }

    /**
     * Removes the given field from the additional metadata.
     *
     * @param string $name The name of the field
     */
    public function removeMetadata(string $name): void
    {
        unset($this->metadata[$name]);
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
}
