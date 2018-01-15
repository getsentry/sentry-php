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
use Raven\Processor\RemoveHttpBodyProcessor;

class RemoveHttpBodyProcessorTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var RemoveHttpBodyProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->processor = new RemoveHttpBodyProcessor();
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess($inputData, $expectedData)
    {
        $event = new Event($this->client->getConfig());
        $event = $event->withRequest($inputData);

        $event = $this->processor->process($event);

        $this->assertArraySubset($expectedData, $event->getRequest());
    }

    public function processDataProvider()
    {
        return [
            [
                [
                    'method' => 'POST',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyProcessor::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'PUT',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyProcessor::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'PATCH',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyProcessor::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'DELETE',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyProcessor::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'GET',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
    }
}
