<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Hub\Hub;

class HubTest extends TestCase
{
    public function testWithScope()
    {
        $hub = new Hub();
        $hub->withScope(function ($scope) use ($hub) {
            $scope->setTag('foo', 'bar');
            $this->assertEquals(['foo' => 'bar'], $hub->getScope()->getTags());
        });
        $this->assertEquals([], $hub->getStackTop()->getScope()->getTags());
    }

    public function testConfigureScope()
    {
        $hub = new Hub();
        $hub->configureScope(function ($scope) {
            // This should never be called since there is no client on the hub
            $scope->setTag('foo', 'bar');
        });
        $this->assertEquals([], $hub->getScope()->getTags());

        $client = ClientBuilder::create()->getClient();
        $hub = new Hub($client);
        $hub->configureScope(function ($scope) {
            $scope->setTag('foo', 'bar');
        });
        $this->assertEquals(['foo' => 'bar'], $hub->getScope()->getTags());
    }

    public function testPush()
    {
        $hub = new Hub();
        $scope = $hub->pushScope();
        $this->assertEquals($scope, $hub->getScope());
        $this->assertEquals(2, \count($hub->getStack()));
    }

    public function testPop()
    {
        $hub = new Hub();
        $hub->pushScope();
        $this->assertEquals(2, \count($hub->getStack()));
        $hub->popScope();
        $this->assertEquals(1, \count($hub->getStack()));
        $hub->popScope();
        $this->assertEquals(1, \count($hub->getStack()));
    }

    public function testBindClient()
    {
        $hub = new Hub();
        $this->assertNull($hub->getClient());
        $client = ClientBuilder::create()->getClient();
        $hub->bindClient($client);
        $this->assertEquals($client, $hub->getClient());
    }

    public function testApplyToEventWithEventProcessor()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());
        $hub = new Hub($client);
        $hub->configureScope(function ($scope) use ($event) {
            $scope->setTag('foo', 'bar');
            $scope->addEventProcessor(function ($event) {
                $event->setMessage('test');

                return $event;
            });
            $finalEvent = $scope->applyToEvent($event);
            $this->assertEquals($event, $finalEvent);
            $this->assertEquals('test', $event->getMessage());
        });
        // TODO set everything on the scope
    }

    public function testApplyToEventWithEventProcessorReturningNull()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());
        $hub = new Hub($client);
        $hub->configureScope(function ($scope) use ($event) {
            $scope->setTag('foo', 'bar');
            $scope->addEventProcessor(function ($event) {
                return null;
            });
            $finalEvent = $scope->applyToEvent($event);
            $this->assertNull($finalEvent);
        });
    }
}
