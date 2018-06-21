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
use Raven\ClientBuilder;
use Raven\ClientInterface;
use Raven\Event;
use Raven\Processor\SanitizeHttpHeadersProcessor;

class SanitizeHttpHeadersProcessorTest extends TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var SanitizeHttpHeadersProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->processor = new SanitizeHttpHeadersProcessor([
            'sanitize_http_headers' => ['User-Defined-Header'],
        ]);
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
                    'headers' => [
                        'Authorization' => 'foo',
                        'AnotherHeader' => 'bar',
                        'User-Defined-Header' => 'baz',
                    ],
                ],
                [
                    'headers' => [
                        'Authorization' => 'foo',
                        'AnotherHeader' => 'bar',
                        'User-Defined-Header' => SanitizeHttpHeadersProcessor::STRING_MASK,
                    ],
                ],
            ],
        ];
    }
}
