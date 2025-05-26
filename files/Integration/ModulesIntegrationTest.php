<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;

use function Sentry\withScope;

final class ModulesIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(bool $isIntegrationEnabled, bool $expectedEmptyModules): void
    {
        $integration = new ModulesIntegration();
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
            }
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
