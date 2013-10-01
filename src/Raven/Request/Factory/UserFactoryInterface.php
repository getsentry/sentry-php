<?php

namespace Raven\Request\Factory;

use Raven\Request\Interfaces\User;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
interface UserFactoryInterface
{
    /**
     * @return User|null
     */
    public function create();
}
