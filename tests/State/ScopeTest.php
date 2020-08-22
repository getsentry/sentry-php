<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\UserDataBag;

final class ScopeTest extends TestCase
{
    public function testSetTag(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getTags());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testSetTags(): void
    {
        $scope = new Scope();
        $scope->setTags(['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getTags());

        $scope->setTags(['bar' => 'baz']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getTags());
    }

    public function testSetAndRemoveContext(): void
    {
        $scope = new Scope();
        $scope->setContext('foo', ['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => ['foo' => 'bar']], $event->getContexts());

        $scope->removeContext('foo');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([], $event->getContexts());
    }

    public function testSetExtra(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getExtra());

        $scope->setExtra('foo', 'bar');
        $scope->setExtra('bar', 'baz');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetExtras(): void
    {
        $scope = new Scope();
        $scope->setExtras(['foo' => 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getExtra());

        $scope->setExtras(['bar' => 'baz']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $event->getExtra());
    }

    public function testSetUser(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertNull($event->getUser());

        $user = UserDataBag::createFromUserIdentifier('unique_id');

        $scope->setUser($user);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame($user, $event->getUser());

        $user = UserDataBag::createFromUserIpAddress('127.0.0.1');

        $scope->setUser($user);

        $event = $scope->applyToEvent($event, []);

        $this->assertNotNull($event);
        $this->assertEquals($user, $event->getUser());
    }

    public function testSetFingerprint(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getFingerprint());

        $scope->setFingerprint(['foo', 'bar']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame(['foo', 'bar'], $event->getFingerprint());
    }

    public function testSetLevel(): void
    {
        $scope = new Scope();
        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::error(), $event->getLevel());

        $scope->setLevel(Severity::debug());

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::debug(), $event->getLevel());
    }

    public function testAddBreadcrumb(): void
    {
        $scope = new Scope();
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb1);
        $scope->addBreadcrumb($breadcrumb2);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());

        $scope->addBreadcrumb($breadcrumb3, 2);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertSame([$breadcrumb2, $breadcrumb3], $event->getBreadcrumbs());
    }

    public function testClearBreadcrumbs(): void
    {
        $scope = new Scope();

        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertNotEmpty($event->getBreadcrumbs());

        $scope->clearBreadcrumbs();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEmpty($event->getBreadcrumbs());
    }

    public function testAddEventProcessor(): void
    {
        $callback1Called = false;
        $callback2Called = false;
        $callback3Called = false;

        $event = new Event();
        $scope = new Scope();

        $scope->addEventProcessor(function (Event $eventArg) use (&$callback1Called, $callback2Called, $callback3Called): ?Event {
            $this->assertFalse($callback2Called);
            $this->assertFalse($callback3Called);

            $callback1Called = true;

            return $eventArg;
        });

        $this->assertSame($event, $scope->applyToEvent($event, []));
        $this->assertTrue($callback1Called);

        $scope->addEventProcessor(function () use ($callback1Called, &$callback2Called, $callback3Called) {
            $this->assertTrue($callback1Called);
            $this->assertFalse($callback3Called);

            $callback2Called = true;

            return null;
        });

        $scope->addEventProcessor(function () use (&$callback3Called) {
            $callback3Called = true;

            return null;
        });

        $this->assertNull($scope->applyToEvent($event, []));
        $this->assertTrue($callback2Called);
        $this->assertFalse($callback3Called);
    }

    public function testClear(): void
    {
        $scope = new Scope();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope->setLevel(Severity::info());
        $scope->addBreadcrumb($breadcrumb);
        $scope->setFingerprint(['foo']);
        $scope->setExtras(['foo' => 'bar']);
        $scope->setTags(['bar' => 'foo']);
        $scope->setUser(UserDataBag::createFromUserIdentifier('unique_id'));
        $scope->clear();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::error(), $event->getLevel());
        $this->assertEmpty($event->getBreadcrumbs());
        $this->assertEmpty($event->getFingerprint());
        $this->assertEmpty($event->getExtra());
        $this->assertEmpty($event->getTags());
        $this->assertEmpty($event->getUser());
    }

    public function testApplyToEvent(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $user = UserDataBag::createFromUserIdentifier('unique_id');

        $event = new Event();
        $event->setContext('foocontext', ['foo' => 'foo', 'bar' => 'bar']);

        $scope = new Scope();
        $scope->setLevel(Severity::warning());
        $scope->setFingerprint(['foo']);
        $scope->addBreadcrumb($breadcrumb);
        $scope->setTag('foo', 'bar');
        $scope->setExtra('bar', 'foo');
        $scope->setUser($user);
        $scope->setContext('foocontext', ['foo' => 'bar']);
        $scope->setContext('barcontext', ['bar' => 'foo']);

        $this->assertSame($event, $scope->applyToEvent($event, []));
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo' => 'bar'], $event->getTags());
        $this->assertSame(['bar' => 'foo'], $event->getExtra());
        $this->assertSame($user, $event->getUser());
        $this->assertSame(['foocontext' => ['foo' => 'foo', 'bar' => 'bar'], 'barcontext' => ['bar' => 'foo']], $event->getContexts());
    }
}
