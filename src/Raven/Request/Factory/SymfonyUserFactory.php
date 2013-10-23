<?php

namespace Raven\Request\Factory;

use Raven\Request\Interfaces\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class SymfonyUserFactory implements UserFactoryInterface
{
    /**
     * @var SecurityContextInterface
     */
    private $securityContext;

    public function __construct(SecurityContextInterface $securityContext)
    {
        $this->securityContext = $securityContext;
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        $token = $this->securityContext->getToken();

        if (null === $token) {
            return null;
        }

        return new User($token->getUsername());
    }
}
