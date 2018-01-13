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

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage You can configure only one of "only" and "except" options.
     */
    public function testConstructorThrowsIfBothOnlyAndExceptOptionsAreSet()
    {
        new RemoveCookiesProcessor([
            'only' => ['foo'],
            'except' => ['bar'],
        ]);
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess($options, $expectedData)
    {
        $event = new Event($this->client->getConfig());
        $event = $event->withRequest([
            'foo' => 'bar',
            'cookies' => [
                'foo' => 'bar',
                'bar' => 'foo',
            ],
            'headers' => [
                'cookie' => 'bar',
                'another-header' => 'foo',
            ],
        ]);

        $processor = new RemoveCookiesProcessor($options);
        $event = $processor->process($event);

        $this->assertArraySubset($expectedData, $event->getRequest());
    }

    public function processDataProvider()
    {
        return [
            [
                [],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => RemoveCookiesProcessor::STRING_MASK,
                        'bar' => RemoveCookiesProcessor::STRING_MASK,
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
            [
                [
                    'only' => ['foo'],
                ],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => RemoveCookiesProcessor::STRING_MASK,
                        'bar' => 'foo',
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
            [
                [
                    'except' => ['foo'],
                ],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => 'bar',
                        'bar' => RemoveCookiesProcessor::STRING_MASK,
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
        ];
    }
}
