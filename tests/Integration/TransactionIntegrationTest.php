<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\IsolationScope;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

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

        SentrySdk::init($client);

        withScope(function (IsolationScope $scope) use ($event, $hint, $expectedTransaction): void {
            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event, $hint);

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
            null,
            ['PATH_INFO' => ''],
            null,
        ];

        yield [
            Event::createEvent(),
            true,
            null,
            ['REQUEST_URI' => '/foo/bar?baz=qux'],
            '/foo/bar',
        ];

        yield [
            Event::createEvent(),
            true,
            null,
            ['PATH_INFO' => '', 'REQUEST_URI' => '/foo/bar'],
            '/foo/bar',
        ];

        yield [
            Event::createEvent(),
            true,
            null,
            ['PATH_INFO' => '/foo/bar', 'REQUEST_URI' => '/index.php/foo/bar'],
            '/foo/bar',
        ];

        yield [
            Event::createEvent(),
            true,
            null,
            ['REQUEST_URI' => '?baz=qux'],
            null,
        ];

        yield [
            Event::createEvent(),
            false,
            null,
            ['REQUEST_URI' => '/foo/bar'],
            null,
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

    /**
     * @dataProvider setupOnceWithActiveTransactionDataProvider
     *
     * @backupGlobals
     */
    public function testSetupOnceWithActiveTransaction(Event $event, ?EventHint $hint, string $expectedTransaction): void
    {
        $_SERVER = array_merge($_SERVER, ['PATH_INFO' => '/foo/bar', 'REQUEST_URI' => '/foo/bar']);

        $integration = new TransactionIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->willReturn($integration);

        $client->method('getOptions')
            ->willReturn(new Options());

        SentrySdk::init($client);

        withScope(function (IsolationScope $scope) use ($event, $hint, $expectedTransaction): void {
            $scope->setSpan(new Transaction(new TransactionContext('GET /users/{id}')));

            $event = SentrySdk::getGlobalScope()->merge($scope)->applyToEvent($event, $hint);

            $this->assertNotNull($event);
            $this->assertSame($expectedTransaction, $event->getTransaction());
        });
    }

    public static function setupOnceWithActiveTransactionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            null,
            'GET /users/{id}',
        ];

        yield [
            Event::createEvent(),
            EventHint::fromArray([
                'extra' => [
                    'transaction' => '/some/other',
                ],
            ]),
            '/some/other',
        ];

        $event = Event::createEvent();
        $event->setTransaction('/some/other');

        yield [
            $event,
            null,
            '/some/other',
        ];
    }
}
