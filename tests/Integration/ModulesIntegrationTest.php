<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;

class ModulesIntegrationTest extends TestCase
{
    public function testInvoke()
    {
        $event = new Event();
        $integration = new ModulesIntegration();

        ModulesIntegration::applyToEvent($integration, $event);

        $this->assertNotNull($event);
        $modules = $event->getModules();
        $this->assertNotEmpty($modules);
        $this->assertArrayHasKey('sentry/sentry', $modules, 'Root project missing');
        $this->assertArrayHasKey('ocramius/package-versions', $modules, 'Indirect dependency missing');
        $this->assertEquals(PrettyVersions::getVersion('sentry/sentry'), $modules['sentry/sentry']);
    }
}
