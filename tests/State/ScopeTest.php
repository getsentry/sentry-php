<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\Scope;

final class ScopeTest extends TestCase
{
    public function testSetTag(): void
    {
        $scope = new Scope();

        $this->assertEquals([], $scope->getTags());

        $scope->setTag('foo', 'bar');
        $scope->setTag('bar', 'baz');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $scope->getTags());
    }

    public function setTags(): void
    {
        $scope = new Scope();

        $scope->setTags(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $scope->getTags());

        $scope->setTags(['bar' => 'baz']);

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $scope->getTags());
    }

    public function testSetExtra(): void
    {
        $scope = new Scope();

        $this->assertEquals([], $scope->getExtra());

        $scope->setExtra('foo', 'bar');
        $scope->setExtra('bar', 'baz');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz'], $scope->getExtra());
    }

    public function setExtras(): void
    {
        $scope = new Scope();

        $scope->setExtras(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $scope->getExtra());

        $scope->setExtras(['bar' => 'baz']);

        $this->assertEquals(['bar' => 'baz'], $scope->getExtra());
    }

    public function testSetUser(): void
    {
        $scope = new Scope();

        $this->assertEquals([], $scope->getUser());

        $scope->setUser(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $scope->getUser());

        $scope->setUser(['bar' => 'baz']);

        $this->assertEquals(['bar' => 'baz'], $scope->getUser());
    }

    public function testSetFingerprint(): void
    {
        $scope = new Scope();

        $this->assertEmpty($scope->getFingerprint());

        $scope->setFingerprint(['foo', 'bar']);

        $this->assertEquals(['foo', 'bar'], $scope->getFingerprint());
    }

    public function testSetLevel(): void
    {
        $scope = new Scope();

        $this->assertNull($scope->getLevel());

        $scope->setLevel(Severity::debug());

        $this->assertEquals(Breadcrumb::LEVEL_DEBUG, $scope->getLevel());
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
        $scope->setUser(['foobar' => 'barfoo']);

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::info(), $event->getLevel());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame(['foo' => 'bar'], $event->getExtraContext()->toArray());
        $this->assertSame(['bar' => 'foo'], $event->getTagsContext()->toArray());
        $this->assertSame(['foobar' => 'barfoo'], $event->getUserContext()->toArray());

        $scope->clear();

        $event = $scope->applyToEvent(new Event(), []);

        $this->assertNotNull($event);
        $this->assertEquals(Severity::error(), $event->getLevel());
        $this->assertEmpty($event->getBreadcrumbs());
        $this->assertEmpty($event->getFingerprint());
        $this->assertEmpty($event->getExtraContext()->toArray());
        $this->assertEmpty($event->getTagsContext()->toArray());
        $this->assertEmpty($event->getUserContext()->toArray());
    }

    public function testApplyToEvent(): void
    {
        $event = new Event();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope = new Scope();
        $scope->setLevel(Severity::warning());
        $scope->setFingerprint(['foo']);
        $scope->addBreadcrumb($breadcrumb);
        $scope->setTag('foo', 'bar');
        $scope->setExtra('bar', 'foo');
        $scope->setUser(['foo' => 'baz']);

        $event = $scope->applyToEvent($event, []);

        $this->assertNotNull($event);
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertEquals(['foo' => 'bar'], $event->getTagsContext()->toArray());
        $this->assertEquals(['bar' => 'foo'], $event->getExtraContext()->toArray());
        $this->assertEquals(['foo' => 'baz'], $event->getUserContext()->toArray());

        $scope->setFingerprint(['foo', 'bar']);
        $scope->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_CRITICAL, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $scope->setLevel(Severity::fatal());
        $scope->setTag('bar', 'foo');
        $scope->setExtra('foo', 'bar');
        $scope->setUser(['baz' => 'foo']);

        $event = $scope->applyToEvent($event, []);

        $this->assertNotNull($event);
        $this->assertTrue($event->getLevel()->isEqualTo(Severity::fatal()));
        $this->assertSame(['foo'], $event->getFingerprint());
        $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $event->getTagsContext()->toArray());
        $this->assertEquals(['bar' => 'foo', 'foo' => 'bar'], $event->getExtraContext()->toArray());
        $this->assertEquals(['foo' => 'baz', 'baz' => 'foo'], $event->getUserContext()->toArray());

        $this->assertSame($event, $scope->applyToEvent($event, []));
    }
}
