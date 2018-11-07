<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ModulesIntegrationInterface;
use Sentry\Options;

class ModulesIntegrationTest extends TestCase
{
    public function testInvoke()
    {
        $options = new Options(['project_root' => __DIR__ . '/../Fixtures']);
        $event = new Event();

        $integration = new ModulesIntegrationInterface($options);

        $returnedEvent = ModulesIntegrationInterface::applyToEvent($integration, $event);
        $this->assertNotNull($returnedEvent);

        $this->assertEquals(['foo/bar' => '1.2.3.0', 'foo/baz' => '4.5.6.0'], $returnedEvent->getModules());
    }
}
