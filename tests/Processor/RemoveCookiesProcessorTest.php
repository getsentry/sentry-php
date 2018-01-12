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
use Raven\Processor\RemoveCookiesProcessor;

class RemoveCookiesProcessorTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var RemoveCookiesProcessor
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->processor = new RemoveCookiesProcessor();
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
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => 'bar',
                    ],
                    'headers' => [
                        'cookie' => 'bar',
                        'another-header' => 'foo',
                    ],
                ],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => RemoveCookiesProcessor::STRING_MASK,
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
        ];
    }
}
