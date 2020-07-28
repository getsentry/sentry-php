<?php

declare(strict_types=1);

namespace Sentry\Tests\Util\Fixtures;

class SimpleClass
{
    private $keyPrivate = 'private';

    public $keyPublic = 'public';

    protected $keyProtected = 'protected';
}
