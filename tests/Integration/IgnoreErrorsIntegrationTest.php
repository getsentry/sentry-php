<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\IgnoreErrorsIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use function Sentry\withScope;

final class IgnoreErrorsIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(Event $event, bool $isIntegrationEnabled, array $integrationOptions, bool $expectedEventToBeDropped): void
    {
        $integration = new IgnoreErrorsIntegration($integrationOptions);
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($isIntegrationEnabled ? $integration : null);

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($event, $expectedEventToBeDropped): void {
            $event = $scope->applyToEvent($event, []);

            if ($expectedEventToBeDropped) {
                $this->assertNull($event);
            } else {
                $this->assertNotNull($event);
            }
        });
    }

    public function invokeDataProvider(): \Generator
    {
        $event = new Event();
        $event->setExceptions([
            ['type' => \RuntimeException::class],
        ]);

        yield 'Integration disabled' => [
            new Event(),
            false,
            [
                'ignore_exceptions' => [],
            ],
            false,
        ];

        $event = new Event();
        $event->setExceptions([
            ['type' => \RuntimeException::class],
        ]);

        yield 'No exceptions to check' => [
            new Event(),
            true,
            [
                'ignore_exceptions' => [],
            ],
            false,
        ];

        $event = new Event();
        $event->setExceptions([
            ['type' => \RuntimeException::class],
        ]);

        yield 'The exception is matching exactly the "ignore_exceptions" option' => [
            $event,
            true,
            [
                'ignore_exceptions' => [
                    \RuntimeException::class,
                ],
            ],
            true,
        ];

        $event = new Event();
        $event->setExceptions([
            ['type' => \RuntimeException::class],
        ]);

        yield 'The exception is matching the "ignore_exceptions" option' => [
            $event,
            true,
            [
                'ignore_exceptions' => [
                    \Exception::class,
                ],
            ],
            true,
        ];
    }
}
