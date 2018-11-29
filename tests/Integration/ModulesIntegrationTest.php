<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;

final class ModulesIntegrationTest extends TestCase
{
    public function testInvoke(): void
    {
        $event = new Event('sentry.sdk.identifier');
        $integration = new ModulesIntegration();

        ModulesIntegration::applyToEvent($integration, $event);

        $modules = $event->getModules();

        $this->assertArrayHasKey('sentry/sentry', $modules, 'Root project missing');
        $this->assertArrayHasKey('ocramius/package-versions', $modules, 'Indirect dependency missing');
        $this->assertEquals(PrettyVersions::getVersion('sentry/sentry'), $modules['sentry/sentry']);
    }
}
