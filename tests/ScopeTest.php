<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Hub\Scope;
use Sentry\Interfaces\Severity;

class ScopeTest extends TestCase
{
    public function testSetTag()
    {
        $scope = new Scope();
        $this->assertEquals([], $scope->getTags());
        $this->assertEquals($scope->setTag('foo', 'bar'), $scope);
        $this->assertEquals(['foo' => 'bar'], $scope->getTags());
        $scope->setTag('foo', 'bar1');
        $this->assertEquals(['foo' => 'bar1'], $scope->getTags());
    }

    public function testSetExtra()
    {
        $scope = new Scope();
        $this->assertEquals([], $scope->getExtra());
        $this->assertEquals($scope->setExtra('foo', 'bar'), $scope);
        $this->assertEquals(['foo' => 'bar'], $scope->getExtra());
        $scope->setExtra('foo', 'bar1');
        $this->assertEquals(['foo' => 'bar1'], $scope->getExtra());
    }

    public function testSetFingerprint()
    {
        $scope = new Scope();
        $this->assertEquals([], $scope->getFingerprint());
        $this->assertEquals($scope->setFingerprint(['a']), $scope);
        $this->assertEquals(['a'], $scope->getFingerprint());
        $scope->setFingerprint(['b']);
        $this->assertEquals(['b'], $scope->getFingerprint());
    }

    public function testSetLevel()
    {
        $scope = new Scope();
        $this->assertNull($scope->getLevel());
        $this->assertEquals($scope->setLevel(new Severity(Severity::WARNING)), $scope);
        $this->assertEquals('warning', $scope->getLevel());
        $scope->setLevel(new Severity(Severity::FATAL));
        $this->assertEquals('fatal', $scope->getLevel());
    }

    public function testAddBreadcrumb()
    {
        $scope = new Scope();
        $this->assertEquals([], $scope->getBreadcrumbs());
        $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'first'));
        $this->assertInstanceOf(Breadcrumb::class, $scope->getBreadcrumbs()[0]);
        for ($i = 0; $i <= 5; ++$i) {
            $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'for'));
        }
        $breadcrumbs = $scope->getBreadcrumbs();
        $this->assertEquals('first', $breadcrumbs[0]->getCategory());
        $this->assertCount(7, $scope->getBreadcrumbs());
        $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'last'), 7);
        $breadcrumbs = $scope->getBreadcrumbs();
        $this->assertCount(7, $scope->getBreadcrumbs());
        $this->assertEquals('last', \end($breadcrumbs)->getCategory());
        $this->assertEquals('for', $breadcrumbs[0]->getCategory());
    }

    public function testClear()
    {
        $scope = new Scope();
        $this->assertEquals($scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'first')), $scope);
        $scope->clear();
        $this->assertCount(0, $scope->getBreadcrumbs());
    }
}
