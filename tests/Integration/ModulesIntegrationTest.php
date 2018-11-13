<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ModulesIntegration;
use Sentry\Options;

class ModulesIntegrationTest extends TestCase
{
    public function testInvoke()
    {
        $options = new Options(['project_root' => __DIR__ . '/../Fixtures']);
        $event = new Event();

        $integration = new ModulesIntegration($options);

        ModulesIntegration::applyToEvent($integration, $event);

        $this->assertNotNull($event);
        $this->assertEquals(['foo/bar' => '1.2.3.0', 'foo/baz' => '4.5.6.0'], $event->getModules());
    }
}
