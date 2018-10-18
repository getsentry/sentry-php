<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\State\Layer;
use Sentry\State\Scope;

final class LayerTest extends TestCase
{
    public function testConstructor(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = new Scope();

        $layer = new Layer($client, $scope);

        $this->assertSame($client, $layer->getClient());
        $this->assertSame($scope, $layer->getScope());
    }

    public function testGettersAndSetters(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = null;
        $scope = new Scope();

        $layer = new Layer(null, new Scope());
        $layer->setClient($client);
        $layer->setScope($scope);

        $this->assertSame($client, $layer->getClient());
        $this->assertSame($scope, $layer->getScope());
    }
}
