<?php

namespace Raven\Tests\Fixtures\classes;

class CarelessException extends \Exception
{
    public function __set($var, $value)
    {
        if ($var === 'event_id') {
            throw new \RuntimeException('I am carelessly throwing an exception here!');
        }
    }
}
