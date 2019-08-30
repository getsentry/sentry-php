<?php

declare(strict_types=1);

namespace Sentry\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Sentry\Exception\MissingPublicKeyCredentialException;

final class MissingPublicKeyCredentialExceptionTest extends TestCase
{
    public function testGetMessage(): void
    {
        $exception = new MissingPublicKeyCredentialException();

        $this->assertSame('The public key of the DSN is required to authenticate with the Sentry server.', $exception->getMessage());
    }
}
