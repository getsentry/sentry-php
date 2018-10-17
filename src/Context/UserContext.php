<?php

namespace Sentry\Context;

use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserContext extends Context
{
    use DefaultContextTrait;

    /**
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->data['id'];
    }

    /**
     * @param null|string $id
     */
    public function setId(?string $id): void
    {
        $this->offsetSet('id', $id);
    }

    /**
     * @return null|string
     */
    public function getUsername(): ?string
    {
        return $this->data['username'];
    }

    /**
     * @param null|string $username
     */
    public function setUsername(?string $username): void
    {
        $this->offsetSet('username', $username);
    }

    /**
     * @return null|string
     */
    public function getEmail(): ?string
    {
        return $this->data['email'];
    }

    /**
     * @param null|string $email
     */
    public function setEmail(?string $email): void
    {
        $this->offsetSet('email', $email);
    }

    /**
     * @return null|array
     */
    public function getData(): ?array
    {
        return $this->data['data'];
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->offsetSet('data', $data);
    }

    /**
     * Configures the options of the context.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined(['id', 'username', 'email', 'data']);
        $resolver->setAllowedTypes('id', 'string');
        $resolver->setAllowedTypes('username', 'string');
        $resolver->setAllowedTypes('email', 'string');
    }
}