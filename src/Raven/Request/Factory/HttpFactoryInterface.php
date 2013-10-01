<?php

namespace Raven\Request\Factory;

use Raven\Request\Interfaces\Http;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
interface HttpFactoryInterface
{
    /**
     * @return Http|null
     */
    public function create();
}
