<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Scope;

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
        $this->assertEquals($scope->setLevel('warning'), $scope);
        $this->assertEquals('warning', $scope->getLevel());
        $scope->setLevel('fatal');
        $this->assertEquals('fatal', $scope->getLevel());
    }

    public function testAddBreadcrumb()
    {
        $scope = new Scope();
        $this->assertEquals([], $scope->getBreadcrumbs());
        $this->assertEquals($scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'first')), $scope);
        $this->assertInstanceOf(Breadcrumb::class, $scope->getBreadcrumbs()[0]);
        for ($i = 0; $i <= 5; ++$i) {
            $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'for'));
        }
        $breadcrumbs = $scope->getBreadcrumbs();
        $this->assertEquals('first', $breadcrumbs[0]->getCategory());
        $this->assertEquals(7, \count($scope->getBreadcrumbs()));
        $scope->addBreadcrumb(new Breadcrumb('warning', Breadcrumb::TYPE_ERROR, 'last'), 7);
        $breadcrumbs = $scope->getBreadcrumbs();
        $this->assertEquals(7, \count($scope->getBreadcrumbs()));
        $this->assertEquals('last', \end($breadcrumbs)->getCategory());
        $this->assertEquals('for', $breadcrumbs[0]->getCategory());
    }
}
