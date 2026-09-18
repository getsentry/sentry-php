<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpBodyType;

final class HttpBodyTypeTest extends TestCase
{
    /**
     * @dataProvider bodyTypeProvider
     */
    public function testBodyType(HttpBodyType $bodyType, string $value, bool $isRequest): void
    {
        $this->assertSame($value, (string) $bodyType);
        $this->assertSame($isRequest, $bodyType->isRequest());
        $this->assertSame($bodyType, HttpBodyType::from($value));
    }

    public function testInvalidBodyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP body type "invalid".');

        HttpBodyType::from('invalid');
    }

    public function bodyTypeProvider(): \Generator
    {
        yield 'incoming request' => [HttpBodyType::incomingRequest(), 'incomingRequest', true];
        yield 'outgoing request' => [HttpBodyType::outgoingRequest(), 'outgoingRequest', true];
        yield 'incoming response' => [HttpBodyType::incomingResponse(), 'incomingResponse', false];
        yield 'outgoing response' => [HttpBodyType::outgoingResponse(), 'outgoingResponse', false];
    }
}
