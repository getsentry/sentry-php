<?php

declare(strict_types=1);

namespace Sentry\Exception;

use Throwable;

class MissingProjectIdCredentialException extends \RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The project ID of the DSN is required to authenticate with the Sentry server.', 0, $previous);
    }
}
