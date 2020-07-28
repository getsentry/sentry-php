<?php

declare(strict_types=1);

namespace Sentry\Exception;

use Throwable;

@trigger_error(sprintf('The %s class is deprecated since version 2.4 and will be removed in 3.0.', MissingProjectIdCredentialException::class), E_USER_DEPRECATED);

/**
 * This exception is thrown during the sending of an event when the project ID
 * is not provided in the DSN.
 *
 * @deprecated since version 2.4, to be removed in 3.0
 */
final class MissingProjectIdCredentialException extends \RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The project ID of the DSN is required to authenticate with the Sentry server.', 0, $previous);
    }
}
