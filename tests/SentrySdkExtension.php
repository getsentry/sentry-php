<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Runner\BeforeTestHook as BeforeTestHookInterface;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class SentrySdkExtension implements BeforeTestHookInterface
{
    public function executeBeforeTest(string $test): void
    {
        Hub::setCurrent(new Hub());

        $reflectionProperty = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, []);
        $reflectionProperty->setAccessible(false);
    }
}
