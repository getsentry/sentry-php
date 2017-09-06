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
use Raven\Processor\RemoveCookiesProcessor;

class RemoveCookiesProcessorTest extends TestCase
{
    /**
     * @var RemoveCookiesProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new RemoveCookiesProcessor($client);
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
                        'foo' => 'bar',
                    ],
                ], [
                    'request' => [
                        'foo' => 'bar',
                    ],
                ],
            ], [
                [
                    'request' => [
                        'foo' => 'bar',
                        'cookies' => 'baz',
                        'headers' => [
                            'Cookie' => 'bar',
                            'AnotherHeader' => 'foo',
                        ],
                    ],
                ], [
                    'request' => [
                        'foo' => 'bar',
                        'cookies' => RemoveCookiesProcessor::STRING_MASK,
                        'headers' => [
                            'Cookie' => RemoveCookiesProcessor::STRING_MASK,
                            'AnotherHeader' => 'foo',
                        ],
                    ],
                ],
            ],
        ];
    }
}
