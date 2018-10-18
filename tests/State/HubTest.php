<?php

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Context\TagsContext;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\Scope;

class HubTest extends TestCase
{
    public function testWithScope()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'tags');
        $reflectionProperty->setAccessible(true);
        $hub = new Hub();
        $hub->withScope(function (Scope $scope) use ($hub, $reflectionProperty) {
            $scope->setTag('foo', 'bar');
            /* @var $tags TagsContext */
            $tags = $reflectionProperty->getValue($hub->getScope());
            $this->assertInstanceOf(TagsContext::class, $tags);
            $this->assertEquals(['foo' => 'bar'], $tags->toArray());
        });
        /* @var $tags TagsContext */
        $tags = $reflectionProperty->getValue($hub->getScope());
        $this->assertTrue($tags->isEmpty());
        $reflectionProperty->setAccessible(false);
    }

    public function testConfigureScope()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'tags');
        $reflectionProperty->setAccessible(true);
        $hub = new Hub();
        $hub->configureScope(function ($scope) {
            // This should never be called since there is no client on the hub
            $scope->setTag('foo', 'bar');
        });
        /* @var $tags TagsContext */
        $tags = $reflectionProperty->getValue($hub->getScope());
        $this->assertTrue($tags->isEmpty());

        $client = ClientBuilder::create()->getClient();
        $hub = new Hub($client);
        $hub->configureScope(function ($scope) {
            $scope->setTag('foo', 'bar');
        });
        /* @var $tags TagsContext */
        $tags = $reflectionProperty->getValue($hub->getScope());
        $this->assertInstanceOf(TagsContext::class, $tags);
        $this->assertEquals(['foo' => 'bar'], $tags->toArray());
        $reflectionProperty->setAccessible(false);
    }

    public function testPush()
    {
        $reflectionProperty = new \ReflectionProperty(Hub::class, 'stack');
        $reflectionProperty->setAccessible(true);
        $hub = new Hub();
        $scope = $hub->pushScope();
        $this->assertEquals($scope, $hub->getScope());
        $this->assertCount(2, $reflectionProperty->getValue($hub));
        $reflectionProperty->setAccessible(false);
    }

    public function testPop()
    {
        $reflectionProperty = new \ReflectionProperty(Hub::class, 'stack');
        $reflectionProperty->setAccessible(true);
        $hub = new Hub();
        $hub->pushScope();
        $this->assertCount(2, $reflectionProperty->getValue($hub));
        $hub->popScope();
        $this->assertCount(1, $reflectionProperty->getValue($hub));
        $hub->popScope();
        $this->assertCount(1, $reflectionProperty->getValue($hub));
        $reflectionProperty->setAccessible(false);
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
            $scope->addEventProcessor(function () {
                return null;
            });
            $finalEvent = $scope->applyToEvent($event);
            $this->assertNull($finalEvent);
        });
    }
}
