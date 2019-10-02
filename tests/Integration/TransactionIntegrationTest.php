<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\TransactionIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use function Sentry\withScope;

final class TransactionIntegrationTest extends TestCase
{
    /**
     * @dataProvider setupOnceDataProvider
     *
     * @backupGlobals
     */
    public function testSetupOnce(Event $event, bool $isIntegrationEnabled, array $payload, array $serverGlobals, ?string $expectedTransaction): void
    {
        $_SERVER = array_merge($_SERVER, $serverGlobals);

        $integration = new TransactionIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($isIntegrationEnabled ? $integration : null);

        SentrySdk::getCurrentHub()->bindClient($client);

        withScope(function (Scope $scope) use ($event, $payload, $expectedTransaction): void {
            $event = $scope->applyToEvent($event, $payload);

            $this->assertNotNull($event);
            $this->assertSame($event->getTransaction(), $expectedTransaction);
        });
    }

    public function setupOnceDataProvider(): \Generator
    {
        yield [
            new Event(),
            true,
            [],
            [],
            null,
        ];

        yield [
            new Event(),
            true,
            ['transaction' => '/foo/bar'],
            [],
            '/foo/bar',
        ];

        yield [
            new Event(),
            true,
            [],
            ['PATH_INFO' => '/foo/bar'],
            '/foo/bar',
        ];

        $event = new Event();
        $event->setTransaction('/foo/bar');

        yield [
            $event,
            true,
            [],
            [],
            '/foo/bar',
        ];

        $event = new Event();
        $event->setTransaction('/foo/bar');

        yield [
            $event,
            true,
            ['/foo/bar/baz'],
            [],
            '/foo/bar',
        ];

        yield [
            new Event(),
            false,
            ['transaction' => '/foo/bar'],
            [],
            null,
        ];
    }
}
