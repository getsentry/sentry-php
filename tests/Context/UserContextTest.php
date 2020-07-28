<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use Sentry\Context\Context;
use Sentry\Context\UserContext;

class UserContextTest extends AbstractContextTest
{
    public function valuesDataProvider(): array
    {
        return [
            [
                [
                    'id' => 'foo',
                    'username' => 'bar',
                    'email' => 'foo@bar.baz',
                ],
                [
                    'id' => 'foo',
                    'username' => 'bar',
                    'email' => 'foo@bar.baz',
                ],
                null,
                null,
            ],
        ];
    }

    public function offsetSetDataProvider(): array
    {
        return [
            [
                'id',
                'foo',
                null,
                null,
            ],
            [
                'username',
                'bar',
                null,
                null,
            ],
            [
                'email',
                'foo@bar.baz',
                null,
                null,
            ],
            [
                'ip_address',
                '127.0.0.1',
                null,
                null,
            ],
        ];
    }

    public function gettersAndSettersDataProvider(): array
    {
        return [
            [
                'getId',
                'setId',
                'foo',
            ],
            [
                'getUsername',
                'setUsername',
                'bar',
            ],
            [
                'getEmail',
                'setEmail',
                'foo@bar.baz',
            ],
            [
                'getIpAddress',
                'setIpAddress',
                '127.0.0.1',
            ],
        ];
    }

    protected function createContext(array $initialData = []): Context
    {
        return new UserContext($initialData);
    }
}
