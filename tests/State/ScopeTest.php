<?php

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Interfaces\Severity;
use Sentry\State\Scope;

class ScopeTest extends TestCase
{
    public function testSetTag()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'tags');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertTrue($reflectionProperty->getValue($scope)->isEmpty());
        $this->assertEquals($scope->setTag('foo', 'bar'), $scope);
        $this->assertEquals(['foo' => 'bar'], $reflectionProperty->getValue($scope)->toArray());
        $scope->setTag('foo', 'bar1');
        $this->assertEquals(['foo' => 'bar1'], $reflectionProperty->getValue($scope)->toArray());
        $reflectionProperty->setAccessible(false);
    }

    public function testSetExtra()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'extra');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertTrue($reflectionProperty->getValue($scope)->isEmpty());
        $this->assertEquals($scope->setExtra('foo', 'bar'), $scope);
        $this->assertEquals(['foo' => 'bar'], $reflectionProperty->getValue($scope)->toArray());
        $scope->setExtra('foo', 'bar1');
        $this->assertEquals(['foo' => 'bar1'], $reflectionProperty->getValue($scope)->toArray());
        $scope->setExtra('bar', 'baz');
        $this->assertEquals(['foo' => 'bar1', 'bar' => 'baz'], $reflectionProperty->getValue($scope)->toArray());
        $reflectionProperty->setAccessible(false);
    }

    public function testSetFingerprint()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'fingerprint');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertEquals([], $reflectionProperty->getValue($scope));
        $this->assertEquals($scope->setFingerprint(['a']), $scope);
        $this->assertEquals(['a'], $reflectionProperty->getValue($scope));
        $scope->setFingerprint(['b']);
        $this->assertEquals(['b'], $reflectionProperty->getValue($scope));
        $reflectionProperty->setAccessible(false);
    }

    public function testSetLevel()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'level');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertNull($reflectionProperty->getValue($scope));
        $this->assertEquals($scope->setLevel(new Severity(Severity::WARNING)), $scope);
        $this->assertEquals('warning', $reflectionProperty->getValue($scope));
        $scope->setLevel(new Severity(Severity::FATAL));
        $this->assertEquals('fatal', $reflectionProperty->getValue($scope));
        $reflectionProperty->setAccessible(false);
    }

    public function testAddBreadcrumb()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'breadcrumbs');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertEquals([], $reflectionProperty->getValue($scope));
        $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'first'));
        $this->assertInstanceOf(Breadcrumb::class, $reflectionProperty->getValue($scope)[0]);
        for ($i = 0; $i <= 5; ++$i) {
            $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'for'));
        }
        $breadcrumbs = $reflectionProperty->getValue($scope);
        $this->assertEquals('first', $breadcrumbs[0]->getCategory());
        $this->assertCount(7, $reflectionProperty->getValue($scope));
        $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'last'), 7);
        $breadcrumbs = $reflectionProperty->getValue($scope);
        $this->assertCount(7, $reflectionProperty->getValue($scope));
        $this->assertEquals('last', \end($breadcrumbs)->getCategory());
        $this->assertEquals('for', $breadcrumbs[0]->getCategory());
        $reflectionProperty->setAccessible(false);
    }

    public function testClear()
    {
        $reflectionProperty = new \ReflectionProperty(Scope::class, 'breadcrumbs');
        $reflectionProperty->setAccessible(true);
        $scope = new Scope();
        $this->assertEquals($scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'first')), $scope);
        $scope->clear();
        $this->assertCount(0, $reflectionProperty->getValue($scope));
        $reflectionProperty->setAccessible(false);
    }
}
