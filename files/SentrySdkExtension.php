<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Runner\BeforeTestHook as BeforeTestHookInterface;
use Sentry\Integration\IntegrationRegistry;
use Sentry\SentrySdk;
use Sentry\State\Scope;

final class SentrySdkExtension implements BeforeTestHookInterface
{
    public function executeBeforeTest(string $test): void
    {
        $reflectionProperty = new \ReflectionProperty(SentrySdk::class, 'currentHub');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, null);
        $reflectionProperty->setAccessible(false);

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, []);
        $reflectionProperty->setAccessible(false);

        $reflectionProperty = new \ReflectionProperty(IntegrationRegistry::class, 'integrations');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(IntegrationRegistry::getInstance(), []);
        $reflectionProperty->setAccessible(false);
    }
}
