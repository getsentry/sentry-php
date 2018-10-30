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

use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\SanitizeDataMiddleware;
use Sentry\Stacktrace;

class SanitizeDataMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $inputData, array $expectedData)
    {
        $event = new Event($this->client->getOptions());

        if (isset($inputData['request'])) {
            $event->setRequest($inputData['request']);
        }

        if (isset($inputData['extra_context'])) {
            $event->getExtraContext()->replaceData($inputData['extra_context']);
        }

        if (isset($inputData['exception'])) {
            // We must convert the backtrace to a Stacktrace instance here because
            // PHPUnit executes the data provider before the setUp method and so
            // the client instance cannot be accessed from there
            $event->setException($this->convertExceptionValuesToStacktrace($expectedData['exception']));
        }

        $middleware = new SanitizeDataMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        if (isset($expectedData['request'])) {
            $this->assertArraySubset($expectedData['request'], $returnedEvent->getRequest());
        }

        if (isset($expectedData['extra_context'])) {
            $this->assertArraySubset($expectedData['extra_context'], $returnedEvent->getExtraContext());
        }

        if (isset($expectedData['exception'])) {
            // We must convert the backtrace to a Stacktrace instance here because
            // PHPUnit executes the data provider before the setUp method and so
            // the client instance cannot be accessed from there
            $this->assertArraySubset($this->convertExceptionValuesToStacktrace($expectedData['exception']), $returnedEvent->getException());
        }
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'request' => [
                        'data' => [
                            'foo' => 'bar',
                            'password' => 'hello',
                            'the_secret' => 'hello',
                            'a_password_here' => 'hello',
                            'mypasswd' => 'hello',
                            'authorization' => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                        ],
                    ],
                ],
                [
                    'request' => [
                        'data' => [
                            'foo' => 'bar',
                            'password' => SanitizeDataMiddleware::STRING_MASK,
                            'the_secret' => SanitizeDataMiddleware::STRING_MASK,
                            'a_password_here' => SanitizeDataMiddleware::STRING_MASK,
                            'mypasswd' => SanitizeDataMiddleware::STRING_MASK,
                            'authorization' => SanitizeDataMiddleware::STRING_MASK,
                        ],
                    ],
                ],
            ],
            [
                [
                    'request' => [
                        'cookies' => [
                            ini_get('session.name') => 'abc',
                        ],
                    ],
                ],
                [
                    'request' => [
                        'cookies' => [
                            ini_get('session.name') => SanitizeDataMiddleware::STRING_MASK,
                        ],
                    ],
                ],
            ],
            [
                [
                    'extra_context' => [
                        'ccnumba' => str_repeat('9', 13),
                    ],
                ],
                [
                    'extra_context' => [
                        'ccnumba' => SanitizeDataMiddleware::STRING_MASK,
                    ],
                ],
            ],
            [
                [
                    'extra_context' => [
                        'ccnumba' => str_repeat('9', 19),
                    ],
                ],
                [
                    'extra_context' => [
                        'ccnumba' => SanitizeDataMiddleware::STRING_MASK,
                    ],
                ],
            ],
            [
                [
                    'exception' => [
                        'values' => [
                            [
                                'stacktrace' => [
                                    [
                                        'args' => [
                                            [
                                                'password' => 'foo',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'stacktrace' => [
                                    [
                                        'args' => [
                                            [
                                                'password' => 'foo',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'exception' => [
                        'values' => [
                            [
                                'stacktrace' => [
                                    [
                                        'args' => [
                                            [
                                                'password' => SanitizeDataMiddleware::STRING_MASK,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'stacktrace' => [
                                    [
                                        'args' => [
                                            [
                                                'password' => SanitizeDataMiddleware::STRING_MASK,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'extra_context' => [
                        'foobar' => 'some-data',
                        'authorization' => [
                            'foo' => 'secret1',
                            'bar' => 'secret2',
                            'baz' => [
                                'nested1' => 'nestedSecret1',
                                'nested2' => 'nestedSecret2',
                                'nested3' => [
                                    'deep' => 'nestedSecret2',
                                ],
                            ],
                        ],
                        'foobaz' => 'some-data',
                    ],
                ],
                [
                    'extra_context' => [
                        'foobar' => 'some-data',
                        'authorization' => [
                            'foo' => SanitizeDataMiddleware::STRING_MASK,
                            'bar' => SanitizeDataMiddleware::STRING_MASK,
                            'baz' => [
                                'nested1' => SanitizeDataMiddleware::STRING_MASK,
                                'nested2' => SanitizeDataMiddleware::STRING_MASK,
                                'nested3' => [
                                    'deep' => SanitizeDataMiddleware::STRING_MASK,
                                ],
                            ],
                        ],
                        'foobaz' => 'some-data',
                    ],
                ],
            ],
        ];
    }

    private function convertExceptionValuesToStacktrace($exceptionValues)
    {
        foreach ($exceptionValues['values'] as &$exceptionValue) {
            $exceptionValue['stacktrace'] = Stacktrace::createFromBacktrace($this->client, $exceptionValue['stacktrace'], 'foo', 1);
        }

        // Free the memory from the reference
        unset($exceptionValue);

        return $exceptionValues;
    }
}
