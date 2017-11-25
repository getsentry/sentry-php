<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Breadcrumbs;

use PHPUnit\Framework\TestCase;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Middleware\ExceptionInterfaceMiddleware;

class ExceptionInterfaceMiddlewareTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($exception, $clientConfig, $payload, $expectedResult)
    {
        $client = ClientBuilder::create($clientConfig)->getClient();
        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, $expectedResult, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertArraySubset($expectedResult, $eventArg->toArray());

            ++$invokationCount;
        };

        $middleware = new ExceptionInterfaceMiddleware($client);
        $middleware($event, $callback, null, $exception, $payload);

        $this->assertEquals(1, $invokationCount);
    }

    public function invokeDataProvider()
    {
        return [
            [
                new \RuntimeException('foo'),
                [],
                [],
                [
                    'level' => Client::LEVEL_ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \ErrorException('foo', 0, E_USER_WARNING),
                [],
                [],
                [
                    'level' => Client::LEVEL_WARNING,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \ErrorException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \BadMethodCallException('baz', 0, new \BadFunctionCallException('bar', 0, new \LogicException('foo', 0))),
                [
                    'excluded_exceptions' => [\BadMethodCallException::class],
                ],
                [],
                [
                    'level' => Client::LEVEL_ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \LogicException::class,
                                'value' => 'foo',
                            ],
                            [
                                'type' => \BadFunctionCallException::class,
                                'value' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testInvokeWithExceptionContainingLatin1Characters()
    {
        $client = ClientBuilder::create(['mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8']])
            ->getClient();

        $event = new Event($client->getConfig());
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);
        $invokationCount = 0;

        $callback = function (Event $eventArg) use ($event, &$invokationCount, $utf8String) {
            $this->assertNotSame($event, $eventArg);

            $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => $utf8String,
                ],
            ];

            $this->assertArraySubset($expectedValue, $eventArg->getException());

            ++$invokationCount;
        };

        $middleware = new ExceptionInterfaceMiddleware($client);
        $middleware($event, $callback, null, new \Exception($latin1String));

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithExceptionContainingInvalidUtf8Characters()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());
        $invokationCount = 0;

        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);

            $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => "\xC2\xA2\x3F",
                ],
            ];

            $this->assertArraySubset($expectedValue, $eventArg->getException());

            ++$invokationCount;
        };

        $middleware = new ExceptionInterfaceMiddleware($client);
        $middleware($event, $callback, null, new \Exception("\xC2\xA2\xC2")); // ill-formed 2-byte character U+00A2 (CENT SIGN)

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithExceptionThrownInLatin1File()
    {
        $client = ClientBuilder::create([
            'auto_log_stacks' => true,
            'mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8'],
        ])->getClient();

        $event = new Event($client->getConfig());

        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);

            $result = $eventArg->getException();

            $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => 'foo',
                ],
            ];

            $this->assertArraySubset($expectedValue, $result);

            $latin1StringFound = false;

            foreach ($result[0]['stacktrace']->toArray() as $frame) {
                if (isset($frame['pre_context']) && in_array('// äöü', $frame['pre_context'], true)) {
                    $latin1StringFound = true;

                    break;
                }
            }

            $this->assertTrue($latin1StringFound);

            ++$invokationCount;
        };

        $middleware = new ExceptionInterfaceMiddleware($client);
        $middleware($event, $callback, null, require_once __DIR__ . '/../Fixtures/code/Latin1File.php');

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithAutoLogStacksDisabled()
    {
        $client = ClientBuilder::create(['auto_log_stacks' => false])->getClient();
        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);

            $result = $eventArg->getException();

            $this->assertNotEmpty($result);
            $this->assertInternalType('array', $result[0]);
            $this->assertEquals(\Exception::class, $result[0]['type']);
            $this->assertEquals('foo', $result[0]['value']);
            $this->assertArrayNotHasKey('stacktrace', $result[0]);

            ++$invokationCount;
        };

        $middleware = new ExceptionInterfaceMiddleware($client);
        $middleware($event, $callback, null, new \Exception('foo'));

        $this->assertEquals(1, $invokationCount);
    }
}
