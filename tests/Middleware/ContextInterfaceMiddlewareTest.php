<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Middleware;

use Sentry\Context\Context;
use Sentry\Integration\ContextInterfaceMiddleware;

class ContextInterfaceMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($contextName, $initialData, $payloadData, $expectedData, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $middleware = new ContextInterfaceMiddleware(new Context($initialData), $contextName);
        $payload = [
            $contextName . '_context' => $payloadData,
        ];

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, null, null, null, $payload);

        $method = preg_replace_callback('/_[a-zA-Z]/', function ($matches) {
            return strtoupper($matches[0][1]);
        }, 'get_' . $contextName . '_context');

        $this->assertEquals($expectedData, $returnedEvent->$method()->toArray());
    }

    public function invokeDataProvider()
    {
        return [
            [
                Context::CONTEXT_USER,
                [
                    'foo' => 'bar',
                    'foobaz' => 'bazfoo',
                ],
                [
                    'foobaz' => 'bazfoo',
                ],
                [
                    'foo' => 'bar',
                    'foobaz' => 'bazfoo',
                ],
                null,
            ],
            [
                Context::CONTEXT_RUNTIME,
                [
                    'name' => 'foo',
                ],
                [
                    'name' => 'foobar',
                ],
                [
                    'name' => 'foobar',
                    'version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
                ],
                null,
            ],
            [
                Context::CONTEXT_TAGS,
                ['foo', 'bar'],
                ['foobar'],
                ['foo', 'bar', 'foobar'],
                null,
            ],
            [
                Context::CONTEXT_EXTRA,
                [
                    'bar' => 'foo',
                ],
                [
                    'barbaz' => 'bazbar',
                ],
                [
                    'bar' => 'foo',
                    'barbaz' => 'bazbar',
                ],
                null,
            ],
            [
                Context::CONTEXT_SERVER_OS,
                [
                    'name' => 'baz',
                ],
                [
                    'name' => 'foobaz',
                ],
                [
                    'name' => 'foobaz',
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                null,
            ],
            [
                'foo',
                [],
                [],
                [],
                'The "foo" context is not supported.',
            ],
        ];
    }
}
