<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpMessageType;

final class HttpMessageTypeTest extends TestCase
{
    /**
     * @dataProvider messageTypeProvider
     */
    public function testMessageType(HttpMessageType $messageType, string $value, bool $isRequest): void
    {
        $this->assertSame($value, (string) $messageType);
        $this->assertSame($isRequest, $messageType->isRequest());
        $this->assertSame($messageType, HttpMessageType::from($value));
    }

    public function testInvalidMessageType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP message type "invalid".');

        HttpMessageType::from('invalid');
    }

    public function messageTypeProvider(): \Generator
    {
        yield 'incoming request' => [HttpMessageType::incomingRequest(), 'incomingRequest', true];
        yield 'outgoing request' => [HttpMessageType::outgoingRequest(), 'outgoingRequest', true];
        yield 'incoming response' => [HttpMessageType::incomingResponse(), 'incomingResponse', false];
        yield 'outgoing response' => [HttpMessageType::outgoingResponse(), 'outgoingResponse', false];
    }
}
