<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;

final class ModulesIntegrationTest extends TestCase
{
    public function testInvoke(): void
    {
        $event = new Event();
        $integration = new ModulesIntegration();

        ModulesIntegration::applyToEvent($integration, $event);

        $modules = $event->getModules();

        $this->assertNotEmpty($modules);
    }
}
