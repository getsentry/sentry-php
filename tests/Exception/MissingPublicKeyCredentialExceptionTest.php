<?php

declare(strict_types=1);

namespace Sentry\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Sentry\Exception\MissingPublicKeyCredentialException;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class MissingPublicKeyCredentialExceptionTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testGetMessage(): void
    {
        $this->expectDeprecation('The Sentry\\Exception\\MissingPublicKeyCredentialException class is deprecated since version 2.4 and will be removed in 3.0.');

        $exception = new MissingPublicKeyCredentialException();

        $this->assertSame('The public key of the DSN is required to authenticate with the Sentry server.', $exception->getMessage());
    }
}
