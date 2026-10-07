<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\ModulesIntegration;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\GlobalScope;
use Sentry\State\IsolationScope;

final class IntegrationEventProcessorTest extends TestCase
{
    /**
     * @dataProvider integrationDataProvider
     */
    public function testEventProcessorIsRegisteredOnGlobalScope(IntegrationInterface $integration): void
    {
        $getIntegrationCalls = 0;

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options());
        $client->method('getIntegration')
            ->willReturnCallback(static function () use (&$getIntegrationCalls): ?IntegrationInterface {
                ++$getIntegrationCalls;

                return null;
            });

        SentrySdk::init($client);

        $integration->setupOnce();

        $event = (new GlobalScope())->merge(new IsolationScope())->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(0, $getIntegrationCalls);

        $event = SentrySdk::getGlobalScope()->merge(new IsolationScope())->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(1, $getIntegrationCalls);
    }

    public static function integrationDataProvider(): \Generator
    {
        yield [new EnvironmentIntegration()];

        yield [new FrameContextifierIntegration()];

        yield [new ModulesIntegration()];

        yield [new RequestIntegration()];

        yield [new TransactionIntegration()];
    }
}
