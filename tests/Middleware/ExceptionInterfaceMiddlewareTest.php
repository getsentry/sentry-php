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

use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\ExceptionIntegration;
use Sentry\Stacktrace;

class ExceptionInterfaceMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(\Exception $exception, array $clientConfig, array $payload, array $expectedResult)
    {
        $client = ClientBuilder::create($clientConfig)->getClient();
        $assertHasStacktrace = $client->getOptions()->getAutoLogStacks();

        $event = new Event($client->getOptions());
        $middleware = new ExceptionIntegration($client);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, null, $exception, $payload);

        $this->assertArraySubset($expectedResult, $returnedEvent->toArray());

        foreach ($returnedEvent->getException()['values'] as $exceptionData) {
            if ($assertHasStacktrace) {
                $this->assertArrayHasKey('stacktrace', $exceptionData);
                $this->assertInstanceOf(Stacktrace::class, $exceptionData['stacktrace']);
            } else {
                $this->assertArrayNotHasKey('stacktrace', $exceptionData);
            }
        }
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
                new \RuntimeException('foo'),
                [
                    'auto_log_stacks' => false,
                ],
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

        $event = new Event($client->getOptions());
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);

        $middleware = new ExceptionIntegration($client);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, null, new \Exception($latin1String));

        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => $utf8String,
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $returnedEvent->getException());
    }

    public function testInvokeWithExceptionContainingInvalidUtf8Characters()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getOptions());

        $middleware = new ExceptionIntegration($client);

        $malformedString = "\xC2\xA2\xC2"; // ill-formed 2-byte character U+00A2 (CENT SIGN)
        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, null, new \Exception($malformedString));

        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => "\xC2\xA2\x3F",
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $returnedEvent->getException());
    }

    public function testInvokeWithExceptionThrownInLatin1File()
    {
        $client = ClientBuilder::create([
            'auto_log_stacks' => true,
            'mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8'],
        ])->getClient();

        $event = new Event($client->getOptions());

        $middleware = new ExceptionIntegration($client);

        $returnedEvent = $this->assertMiddlewareInvokesNext(
            $middleware,
            $event,
            null,
            require_once __DIR__ . '/../Fixtures/code/Latin1File.php'
        );

        $result = $returnedEvent->getException();
        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => 'foo',
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $result);

        $latin1StringFound = false;

        /** @var \Sentry\Frame $frame */
        foreach ($result['values'][0]['stacktrace']->getFrames() as $frame) {
            if (null !== $frame->getPreContext() && \in_array('// äöü', $frame->getPreContext(), true)) {
                $latin1StringFound = true;

                break;
            }
        }

        $this->assertTrue($latin1StringFound);
    }

    public function testInvokeWithAutoLogStacksDisabled()
    {
        $client = ClientBuilder::create(['auto_log_stacks' => false])->getClient();
        $event = new Event($client->getOptions());

        $middleware = new ExceptionIntegration($client);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, null, new \Exception('foo'));

        $result = $returnedEvent->getException();
        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result['values'][0]);
        $this->assertEquals(\Exception::class, $result['values'][0]['type']);
        $this->assertEquals('foo', $result['values'][0]['value']);
        $this->assertArrayNotHasKey('stacktrace', $result['values'][0]);
    }
}
