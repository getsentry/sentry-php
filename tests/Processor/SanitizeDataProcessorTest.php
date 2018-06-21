<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Processor\SanitizeDataProcessor;
use Raven\Stacktrace;

class SanitizeDataProcessorTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var SanitizeDataProcessor
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->processor = new SanitizeDataProcessor();
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess($inputData, $expectedData)
    {
        $event = new Event($this->client->getConfig());

        if (isset($inputData['request'])) {
            $event = $event->withRequest($inputData['request']);
        }

        if (isset($inputData['extra_context'])) {
            $event = $event->withExtraContext($inputData['extra_context']);
        }

        if (isset($inputData['exception'])) {
            // We must convert the backtrace to a Stacktrace instance here because
            // PHPUnit executes the data provider before the setUp method and so
            // the client instance cannot be accessed from there
            $event = $event->withException($this->convertExceptionValuesToStacktrace($expectedData['exception']));
        }

        $event = $this->processor->process($event);

        if (isset($expectedData['request'])) {
            $this->assertArraySubset($expectedData['request'], $event->getRequest());
        }

        if (isset($expectedData['extra_context'])) {
            $this->assertArraySubset($expectedData['extra_context'], $event->getExtraContext());
        }

        if (isset($expectedData['exception'])) {
            // We must convert the backtrace to a Stacktrace instance here because
            // PHPUnit executes the data provider before the setUp method and so
            // the client instance cannot be accessed from there
            $this->assertArraySubset($this->convertExceptionValuesToStacktrace($expectedData['exception']),
                $event->getException());
        }
    }

    public function processDataProvider()
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
                            'password' => SanitizeDataProcessor::STRING_MASK,
                            'the_secret' => SanitizeDataProcessor::STRING_MASK,
                            'a_password_here' => SanitizeDataProcessor::STRING_MASK,
                            'mypasswd' => SanitizeDataProcessor::STRING_MASK,
                            'authorization' => SanitizeDataProcessor::STRING_MASK,
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
                            ini_get('session.name') => SanitizeDataProcessor::STRING_MASK,
                        ],
                    ],
                ],
            ],
            [
                [
                    'extra_context' => [
                        'ccnumba' => '4242424242424242',
                    ],
                ],
                [
                    'extra_context' => [
                        'ccnumba' => SanitizeDataProcessor::STRING_MASK,
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
                                                'password' => SanitizeDataProcessor::STRING_MASK,
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
                                                'password' => SanitizeDataProcessor::STRING_MASK,
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
                            ],
                        ],
                    ],
                ],
                [
                    'extra_context' => [
                        'foobar' => 'some-data',
                        'authorization' => [
                            'foo' => SanitizeDataProcessor::STRING_MASK,
                            'bar' => SanitizeDataProcessor::STRING_MASK,
                            'baz' => [
                                'nested1' => SanitizeDataProcessor::STRING_MASK,
                                'nested2' => SanitizeDataProcessor::STRING_MASK,
                            ],
                        ],
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
