<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\NoOpClient;
use Sentry\SentrySdk;
use Sentry\State\ScopeType;

final class SentrySdkTest extends TestCase
{
    public function testInitBindsClientToGlobalScope(): void
    {
        $client = $this->createMock(\Sentry\ClientInterface::class);

        SentrySdk::init($client);

        $this->assertSame($client, SentrySdk::getGlobalScope()->getClient());
    }

    public function testInitResetsIsolationAndCurrentScopes(): void
    {
        SentrySdk::init(new NoOpClient());

        $oldIsolationScope = SentrySdk::getIsolationScope();
        $oldCurrentScope = SentrySdk::getCurrentScope();

        SentrySdk::init(new NoOpClient());

        $this->assertNotSame($oldIsolationScope, SentrySdk::getIsolationScope());
        $this->assertNotSame($oldCurrentScope, SentrySdk::getCurrentScope());
    }

    public function testScopeAccessorsReturnTypedScopes(): void
    {
        SentrySdk::init();

        $this->assertSame(ScopeType::global(), SentrySdk::getGlobalScope()->getType());
        $this->assertSame(ScopeType::isolation(), SentrySdk::getIsolationScope()->getType());
        $this->assertSame(ScopeType::current(), SentrySdk::getCurrentScope()->getType());
    }

    public function testGetClientPrefersCurrentThenIsolationThenGlobal(): void
    {
        $globalClient = $this->createMock(\Sentry\ClientInterface::class);
        $isolationClient = $this->createMock(\Sentry\ClientInterface::class);
        $currentClient = $this->createMock(\Sentry\ClientInterface::class);

        SentrySdk::init(new NoOpClient());
        SentrySdk::getGlobalScope()->bindClient($globalClient);
        SentrySdk::getIsolationScope()->bindClient($isolationClient);
        SentrySdk::getCurrentScope()->bindClient($currentClient);

        $this->assertSame($currentClient, SentrySdk::getClient());

        SentrySdk::getCurrentScope()->bindClient(new NoOpClient());
        $this->assertSame($isolationClient, SentrySdk::getClient());

        SentrySdk::getIsolationScope()->bindClient(new NoOpClient());
        $this->assertSame($globalClient, SentrySdk::getClient());
    }

    public function testGetMergedScopeCombinesScopeData(): void
    {
        SentrySdk::init(new NoOpClient());

        SentrySdk::getGlobalScope()->setTag('global', 'yes');
        SentrySdk::getGlobalScope()->setTag('shared', 'global');
        SentrySdk::getIsolationScope()->setTag('isolation', 'yes');
        SentrySdk::getIsolationScope()->setTag('shared', 'isolation');
        SentrySdk::getCurrentScope()->setTag('current', 'yes');
        SentrySdk::getCurrentScope()->setTag('shared', 'current');

        $event = \Sentry\Event::createEvent();
        $event = SentrySdk::getMergedScope()->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertEquals([
            'global' => 'yes',
            'isolation' => 'yes',
            'current' => 'yes',
            'shared' => 'current',
        ], $event->getTags());
    }
}
