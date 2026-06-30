<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpHeaders;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class HttpHeadersTest extends TestCase
{
    public function testDefaults(): void
    {
        $httpHeaders = new HttpHeaders();

        $this->assertSame('denyList', $httpHeaders->getRequest()->getMode());
        $this->assertSame([], $httpHeaders->getRequest()->getTerms());
        $this->assertSame('denyList', $httpHeaders->getResponse()->getMode());
        $this->assertSame([], $httpHeaders->getResponse()->getTerms());
    }

    public function testConfigurationAppliesToBothRequestAndResponse(): void
    {
        $httpHeaders = new HttpHeaders([
            'mode' => 'allowList',
            'terms' => ['x-request-id'],
        ]);

        $this->assertSame('allowList', $httpHeaders->getRequest()->getMode());
        $this->assertSame(['x-request-id'], $httpHeaders->getRequest()->getTerms());
        $this->assertSame('allowList', $httpHeaders->getResponse()->getMode());
        $this->assertSame(['x-request-id'], $httpHeaders->getResponse()->getTerms());
    }

    public function testRequestAndResponseIsNull(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The option "request" with value null is expected to be of type "array" or "Sentry\DataCollection\KeyValueCollectionBehavior", but is of type "null"');

        new HttpHeaders([
            'request' => null,
            'response' => null,
        ]);
    }
}
