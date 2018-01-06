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

        $event = $this->processor->process($event);

        if (isset($inputData['request'])) {
            $this->assertArraySubset($expectedData['request'], $event->getRequest());
        }

        if (isset($inputData['extra_context'])) {
            $this->assertArraySubset($expectedData['extra_context'], $event->getExtraContext());
        }

        $this->markTestIncomplete('Array scrubbing has not been implemented yet.');
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
        ];
    }
}
