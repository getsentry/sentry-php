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

namespace Sentry\Context;

/**
 * This class is a specialized version of the base `Context` adapted to work
 * for the user context.
 */
final class UserContext extends Context
{
    /**
     * Gets the ID of the user.
     *
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->data['id'] ?? null;
    }

    /**
     * Sets the ID of the user.
     *
     * @param null|string $id The ID
     */
    public function setId(?string $id): void
    {
        $this->data['id'] = $id;
    }

    /**
     * Gets the username of the user.
     *
     * @return null|string
     */
    public function getUsername(): ?string
    {
        return $this->data['username'] ?? null;
    }

    /**
     * Sets the username of the user.
     *
     * @param null|string $username The username
     */
    public function setUsername(?string $username): void
    {
        $this->data['username'] = $username;
    }

    /**
     * Gets the email of the user.
     *
     * @return null|string
     */
    public function getEmail(): ?string
    {
        return $this->data['email'] ?? null;
    }

    /**
     * Sets the email of the user.
     *
     * @param null|string $email The email
     */
    public function setEmail(?string $email): void
    {
        $this->data['email'] = $email;
    }

    /**
     * Gets the ip address of the user.
     *
     * @return null|string
     */
    public function getIpAddress(): ?string
    {
        return $this->data['email'] ?? null;
    }

    /**
     * Sets the ip address of the user.
     *
     * @param null|string $ip The ip address
     */
    public function setIpAddress(?string $ip): void
    {
        $this->data['ip_address'] = $ip;
    }
}
