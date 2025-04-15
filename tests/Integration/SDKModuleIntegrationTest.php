<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;
use Sentry\Integration\SDKModuleIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;

use function Sentry\withScope;

final class SDKModuleIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(bool $isIntegrationEnabled, bool $expectedEmptyModules): void
    {
        $integration = new SDKModuleIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($isIntegrationEnabled ? $integration : null);

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($expectedEmptyModules): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);

            if ($expectedEmptyModules) {
                $this->assertEmpty($event->getModules());
            } else {
                $this->assertNotEmpty($event->getModules());
                $this->assertEquals(
                    [
                        'sentry/sentry' => Client::SDK_VERSION,
                    ],
                    $event->getModules()
                );
            }
        });
    }

    public function testEnsureModulesIntegrationTakesPrecedence(): void
    {
        $integration1 = new ModulesIntegration();
        $integration1->setupOnce();

        $integration2 = new SDKModuleIntegration();
        $integration2->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
               ->method('getIntegration')
               ->willReturn($integration1, $integration2);

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);

            $this->assertNotEmpty($event->getModules());
            $this->assertNotEquals(
                [
                    'sentry/sentry' => Client::SDK_VERSION,
                ],
                $event->getModules()
            );
        });
    }

    public static function invokeDataProvider(): \Generator
    {
        yield [
            false,
            true,
        ];

        yield [
            true,
            false,
        ];
    }
}
