<?php

declare(strict_types=1);

namespace Sentry\Exception;

use Throwable;

class EventCreationException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Unable to instantiate an event', 0, $previous);
    }
}
