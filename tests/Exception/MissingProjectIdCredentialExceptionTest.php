<?php

declare(strict_types=1);

namespace Sentry\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Sentry\Exception\MissingProjectIdCredentialException;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class MissingProjectIdCredentialExceptionTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testGetMessage(): void
    {
        $this->expectDeprecation('The Sentry\\Exception\\MissingProjectIdCredentialException class is deprecated since version 2.4 and will be removed in 3.0.');

        $exception = new MissingProjectIdCredentialException();

        $this->assertSame('The project ID of the DSN is required to authenticate with the Sentry server.', $exception->getMessage());
    }
}
