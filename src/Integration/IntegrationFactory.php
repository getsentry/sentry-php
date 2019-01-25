<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ClientInterface;

class IntegrationFactory implements IntegrationFactoryInterface
{
    public function create(ClientInterface $client, string $fqcn): IntegrationInterface
    {
        if (!$this->isValidFQCN($fqcn)) {
            throw new \InvalidArgumentException('Bad FQCN for an integration: ' . $fqcn);
        }

        return new $fqcn($client);
    }

    private function isValidFQCN(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            return false;
        }

        $implements = class_implements($fqcn);

        return array_key_exists(IntegrationInterface::class, $implements);
    }
}
