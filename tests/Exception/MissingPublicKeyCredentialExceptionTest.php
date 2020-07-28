<?php

declare(strict_types=1);

namespace Sentry\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Sentry\Exception\MissingPublicKeyCredentialException;

final class MissingPublicKeyCredentialExceptionTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecationMessage The Sentry\Exception\MissingPublicKeyCredentialExceptionTest class is deprecated since version 2.4 and will be removed in 3.0.
     */
    public function testGetMessage(): void
    {
        $exception = new MissingPublicKeyCredentialException();

        $this->assertSame('The public key of the DSN is required to authenticate with the Sentry server.', $exception->getMessage());
    }
}
