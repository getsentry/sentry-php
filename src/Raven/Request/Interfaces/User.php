<?php

namespace Raven\Request\Interfaces;

use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class User implements ToArrayInterface
{
    /**
     * @var string|null
     */
    private $id;

    /**
     * @var string|null
     */
    private $ipAddress;

    /**
     * @var string|null
     */
    private $username;

    /**
     * @var string|null
     */
    private $email;

    public function __construct($id = null, $ipAddress = null)
    {
        if (null === $id && null === $ipAddress) {
            throw new \InvalidArgumentException('At least one of the id or ipAddress arguments is required.');
        }

        $this->id = $id;
        $this->ipAddress = $ipAddress;
    }

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * @param null|string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return null|string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param null|string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return null|string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'id' => $this->getId(),
            'username' => $this->getUsername(),
            'email' => $this->getEmail(),
            'ip_address' => $this->getIpAddress(),
        ));
    }
}
