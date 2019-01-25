<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Integration\IntegrationFactory;
use Sentry\Integration\IntegrationInterface;

class IntegrationFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new IntegrationFactory();
        $client = $this->prophesize(ClientInterface::class)->reveal();

        $result = $factory->create($client, DummyIntegration::class);

        $this->assertInstanceOf(DummyIntegration::class, $result);
        $this->assertSame($client, $result->getClient());
    }

    public function testCreateWithWrongFQCN(): void
    {
        $factory = new IntegrationFactory();
        $client = $this->prophesize(ClientInterface::class)->reveal();

        $this->expectException(\InvalidArgumentException::class);

        $factory->create($client, 'Bad string');
    }
}

class DummyIntegration implements IntegrationInterface
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }

    public static function setup(ClientInterface $client): IntegrationInterface
    {
        throw new \RuntimeException();
    }
}
