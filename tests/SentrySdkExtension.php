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
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(SentrySdk::class, 'runtimeContextManager');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, null);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(null, []);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $reflectionProperty = new \ReflectionProperty(IntegrationRegistry::class, 'integrations');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue(IntegrationRegistry::getInstance(), []);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }
    }
}
