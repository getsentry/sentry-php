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

use Sentry\Event;
use Sentry\Integration\ModulesIntegration;
use Sentry\Options;

class ModulesIntegrationTest extends MiddlewareTestCase
{
    public function testInvoke()
    {
        $options = new Options(['project_root' => __DIR__ . '/../Fixtures']);
        $event = new Event($options);

        $integration = new ModulesIntegration($options);

        $returnedEvent = ModulesIntegration::applyToEvent($integration, $event);

        $this->assertEquals(['foo/bar' => '1.2.3.0', 'foo/baz' => '4.5.6.0'], $returnedEvent->getModules());
    }
}
