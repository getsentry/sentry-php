<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use Sentry\Context\Context;
use Sentry\Context\ServerOsContext;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

class ServerOsContextTest extends AbstractContextTest
{
    public function valuesDataProvider(): array
    {
        return [
            [
                [],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'name' => 'foo',
                ],
                [
                    'name' => 'foo',
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'version' => 'bar',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => 'bar',
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'build' => 'baz',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => 'baz',
                    'kernel_version' => php_uname('a'),
                ],
                null,
                null,
            ],
            [
                [
                    'kernel_version' => 'foobarbaz',
                ],
                [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => 'foobarbaz',
                ],
                null,
                null,
            ],
            [
                [
                    'foo' => 'bar',
                ],
                [],
                UndefinedOptionsException::class,
                '/^The option "foo" does not exist\. Defined options are: "build", "kernel_version", "name", "version"\.$/',
            ],
            [
                [
                    'name' => 1,
                ],
                [],
                InvalidOptionsException::class,
                '/^The option "name" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                [
                    'version' => 1,
                ],
                [],
                InvalidOptionsException::class,
                '/^The option "version" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                [
                    'build' => 1,
                ],
                [],
                InvalidOptionsException::class,
                '/^The option "build" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                [
                    'kernel_version' => 1,
                ],
                [],
                InvalidOptionsException::class,
                '/^The option "kernel_version" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
        ];
    }

    public function offsetSetDataProvider(): array
    {
        return [
            [
                'name',
                'foo',
                null,
                null,
            ],
            [
                'name',
                1,
                InvalidOptionsException::class,
                '/^The option "name" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                'version',
                'foo',
                null,
                null,
            ],
            [
                'version',
                1,
                InvalidOptionsException::class,
                '/^The option "version" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                'build',
                'foo',
                null,
                null,
            ],
            [
                'build',
                1,
                InvalidOptionsException::class,
                '/^The option "build" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                'kernel_version',
                'foobarbaz',
                null,
                null,
            ],
            [
                'kernel_version',
                1,
                InvalidOptionsException::class,
                '/^The option "kernel_version" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                'foo',
                'bar',
                UndefinedOptionsException::class,
                '/^The option "foo" does not exist\. Defined options are: "build", "kernel_version", "name", "version"\.$/',
            ],
        ];
    }

    public function gettersAndSettersDataProvider(): array
    {
        return [
            [
                'getName',
                'setName',
                'foo',
            ],
            [
                'getVersion',
                'setVersion',
                'bar',
            ],
            [
                'getBuild',
                'setBuild',
                'baz',
            ],
            [
                'getKernelVersion',
                'setKernelVersion',
                'foobarbaz',
            ],
        ];
    }

    protected function createContext(array $initialData = []): Context
    {
        return new ServerOsContext($initialData);
    }
}
