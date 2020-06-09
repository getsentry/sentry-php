<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Util\PHPVersion;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

class RuntimeContextTest extends AbstractContextTest
{
    public function valuesDataProvider(): array
    {
        return [
            [
                [],
                [
                    'name' => 'php',
                    'version' => PHPVersion::parseVersion(),
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
                    'version' => PHPVersion::parseVersion(),
                ],
                null,
                null,
            ],
            [
                [
                    'name' => 'foo',
                    'version' => 'bar',
                ],
                [
                    'name' => 'foo',
                    'version' => 'bar',
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
                '/^The option "foo" does not exist\. Defined options are: "name", "version"\.$/',
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
                1,
                InvalidOptionsException::class,
                '/^The option "version" with value 1 is expected to be of type "string", but is of type "(integer|int)"\.$/',
            ],
            [
                'foo',
                'bar',
                UndefinedOptionsException::class,
                '/^The option "foo" does not exist\. Defined options are: "name", "version"\.$/',
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
        ];
    }

    protected function createContext(array $initialData = []): Context
    {
        return new RuntimeContext($initialData);
    }
}
