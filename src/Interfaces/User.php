<?php

namespace Sentry\Interfaces;

final class User implements \JsonSerializable
{
    /**
     * @var null|string
     */
    private $id;
    /**
     * @var null|string
     */
    private $username;
    /**
     * @var null|string
     */
    private $email;

    /**
     * @var null|array
     */
    private $data;

    /**
     * User constructor.
     *
     * @param null|string $id
     * @param null|string $username
     * @param null|string $email
     * @param array|null  $data
     */
    public function __construct(?string $id = null, ?string $username = null, ?string $email = null, ?array $data = null)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->data = $data;
    }

    /**
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param null|string $id
     *
     * @return User
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     *
     * @return User
     */
    public function setUsername($username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     *
     * @return User
     */
    public function setEmail($email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return null|array
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return User
     */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Gets the User as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        if (null !== $this->getId()) {
            $data['id'] = $this->getId();
        }

        if (null !== $this->getUsername()) {
            $data['username'] = $this->getUsername();
        }

        if (null !== $this->getEmail()) {
            $data['email'] = $this->getEmail();
        }

        if (null !== $this->getData()) {
            $data['data'] = $this->getData();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
