<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use Raven\Client;
use Raven\Processor\SanitizeHttpHeadersProcessor;

class SanitizeHttpHeadersProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SanitizeHttpHeadersProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new SanitizeHttpHeadersProcessor($client);
        $this->processor->setProcessorOptions([
            'sanitize_http_headers' => ['User-Defined-Header'],
        ]);
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
                        'headers' => [
                            'Authorization' => 'foo',
                            'AnotherHeader' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'headers' => [
                            'Authorization' => SanitizeHttpHeadersProcessor::STRING_MASK,
                            'AnotherHeader' => 'bar',
                        ],
                    ],
                ],
            ], [
                [
                    'request' => [
                        'headers' => [
                            'User-Defined-Header' => 'foo',
                            'AnotherHeader' => 'bar',
                        ],
                    ],
                ], [
                    'request' => [
                        'headers' => [
                            'User-Defined-Header' => SanitizeHttpHeadersProcessor::STRING_MASK,
                            'AnotherHeader' => 'bar',
                        ],
                    ],
                ],
            ],
        ];
    }
}
