<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\State\Layer;
use Sentry\State\Scope;

final class LayerTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        /** @var ClientInterface|MockObject $client1 */
        $client1 = $this->createMock(ClientInterface::class);

        /** @var ClientInterface|MockObject $client2 */
        $client2 = $this->createMock(ClientInterface::class);

        $scope1 = new Scope();
        $scope2 = new Scope();

        $layer = new Layer($client1, $scope1);

        $this->assertSame($client1, $layer->getClient());
        $this->assertSame($scope1, $layer->getScope());

        $layer->setClient($client2);
        $layer->setScope($scope2);

        $this->assertSame($client2, $layer->getClient());
        $this->assertSame($scope2, $layer->getScope());
    }
}
