<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\State\Scope;
use Sentry\State\ScopeManager;
use Sentry\State\ScopeType;

final class ScopeManagerTest extends TestCase
{
    public function testDefaultScopesHaveTypes(): void
    {
        $manager = new ScopeManager();

        $this->assertSame(ScopeType::global(), $manager->getGlobalScope()->getType());
        $this->assertSame(ScopeType::isolation(), $manager->getIsolationScope()->getType());
        $this->assertSame(ScopeType::current(), $manager->getCurrentScope()->getType());
    }

    public function testWithScopeForksCurrentOnly(): void
    {
        $manager = new ScopeManager();
        $originalCurrent = $manager->getCurrentScope();

        $callbackScope = null;

        $manager->withScope(function (Scope $scope) use ($manager, $originalCurrent, &$callbackScope): void {
            $callbackScope = $scope;

            $this->assertNotSame($originalCurrent, $scope);
            $this->assertSame($scope, $manager->getCurrentScope());
            $this->assertSame(ScopeType::current(), $scope->getType());
        });

        $this->assertSame($originalCurrent, $manager->getCurrentScope());
        $this->assertNotNull($callbackScope);
    }

    public function testWithIsolationScopeForksIsolationAndCurrent(): void
    {
        $manager = new ScopeManager();
        $originalIsolation = $manager->getIsolationScope();
        $originalCurrent = $manager->getCurrentScope();

        $manager->withIsolationScope(function (Scope $scope) use ($manager, $originalIsolation, $originalCurrent): void {
            $this->assertNotSame($originalIsolation, $scope);
            $this->assertSame($scope, $manager->getIsolationScope());
            $this->assertSame(ScopeType::isolation(), $scope->getType());

            $this->assertNotSame($originalCurrent, $manager->getCurrentScope());
        });

        $this->assertSame($originalIsolation, $manager->getIsolationScope());
        $this->assertSame($originalCurrent, $manager->getCurrentScope());
    }

    public function testResetScopesCreatesFreshIsolationAndCurrent(): void
    {
        $manager = new ScopeManager();
        $originalIsolation = $manager->getIsolationScope();
        $originalCurrent = $manager->getCurrentScope();

        $manager->resetScopes();

        $this->assertNotSame($originalIsolation, $manager->getIsolationScope());
        $this->assertNotSame($originalCurrent, $manager->getCurrentScope());
        $this->assertSame(ScopeType::global(), $manager->getGlobalScope()->getType());
    }
}
