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
use Raven\Processor\RemoveHttpBodyProcessor;

class RemoveHttpBodyProcessorTest extends TestCase
{
    /**
     * @var RemoveHttpBodyProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new RemoveHttpBodyProcessor($client);
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess($inputData, $expectedData)
    {
        $this->processor->process($inputData);

        $this->assertArraySubset($expectedData, $inputData);
    }

    public function processDataProvider()
    {
        return [
            [
                [
                    'request' => [
                        'method' => 'POST',
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ],
                ],
            ], [
                [
                    'request' => [
                        'method' => 'PUT',
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ],
                ],
            ], [
                [
                    'request' => [
                        'method' => 'PATCH',
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
                [
                    'request' => [
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ],
                ],
            ], [
                [
                    'request' => [
                        'method' => 'DELETE',
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ],
                ],
            ], [
                [
                    'request' => [
                        'method' => 'GET',
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'data' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ];
    }
}
