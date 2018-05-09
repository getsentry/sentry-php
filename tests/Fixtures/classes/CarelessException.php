<?php

namespace Raven\Tests\Fixtures\classes;

class CarelessException extends \Exception
{
    public function __set($var, $value)
    {
        if ('event_id' === $var) {
            throw new \RuntimeException('I am carelessly throwing an exception here!');
        }
    }
}
