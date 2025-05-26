<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
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
    public function testSetupOnce(Event $event, bool $isIntegrationEnabled, ?EventHint $hint, array $serverGlobals, ?string $expectedTransaction): void
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

        withScope(function (Scope $scope) use ($event, $hint, $expectedTransaction): void {
            $event = $scope->applyToEvent($event, $hint);

            $this->assertNotNull($event);
            $this->assertSame($event->getTransaction(), $expectedTransaction);
        });
    }

    public static function setupOnceDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            true,
            null,
            [],
            null,
        ];

        yield [
            Event::createEvent(),
            false,
            null,
            [],
            null,
        ];

        yield [
            Event::createEvent(),
            true,
            null,
            ['PATH_INFO' => '/foo/bar'],
            '/foo/bar',
        ];

        yield [
            Event::createEvent(),
            true,
            EventHint::fromArray([
                'extra' => [
                    'transaction' => '/foo/bar',
                ],
            ]),
            [],
            '/foo/bar',
        ];

        $event = Event::createEvent();
        $event->setTransaction('/foo/bar');

        yield [
            $event,
            true,
            null,
            [],
            '/foo/bar',
        ];

        $event = Event::createEvent();
        $event->setTransaction('/foo/bar');

        yield [
            $event,
            false,
            null,
            [],
            '/foo/bar',
        ];

        yield [
            Event::createEvent(),
            true,
            EventHint::fromArray([
                'extra' => [
                    'transaction' => '/some/other',
                ],
            ]),
            ['PATH_INFO' => '/foo/bar'],
            '/some/other',
        ];

        yield [
            Event::createEvent(),
            false,
            EventHint::fromArray([
                'extra' => [
                    'transaction' => '/some/other',
                ],
            ]),
            ['PATH_INFO' => '/foo/bar'],
            null,
        ];
    }
}
