<?php

namespace Raven;

/**
 * Storage for additional client context.
 */
class Context
{
    /**
     * @var array
     */
    private $tags;

    /**
     * @var array
     */
    private $userData;

    /**
     * @var array
     */
    private $extraData;

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Clean up existing context.
     */
    public function clear()
    {
        $this->tags = [];
        $this->extraData = [];
        $this->userData = [];
    }

    public function setTag($name, $value)
    {
        if (
            ! is_string($name)
            || '' === $name
        ) {
            throw new \InvalidArgumentException('Invalid tag name');
        }

        $this->tags[$name] = $value;
    }

    public function setUserId($userId)
    {
        $this->userData['id'] = $userId;
    }

    public function setUserEmail($userEmail)
    {
        $this->userData['email'] = $userEmail;
    }

    /**
     * @param array $data
     */
    public function setUserData(array $data)
    {
        $this->userData = $data;
    }

    public function mergeUserData(array $data = [])
    {
        $this->userData = array_merge($this->userData, $data);
    }

    public function mergeExtraData(array $data = [])
    {
        $this->extraData = array_merge($this->extraData, $data);
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return array
     */
    public function getUserData()
    {
        return $this->userData;
    }

    /**
     * @return array
     */
    public function getExtraData()
    {
        return $this->extraData;
    }
}
