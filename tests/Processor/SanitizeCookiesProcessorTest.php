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
use Raven\Processor\SanitizeCookiesProcessor;

class SanitizeCookiesProcessorTest extends TestCase
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
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage You can configure only one of "only" and "except" options.
     */
    public function testConstructorThrowsIfBothOnlyAndExceptOptionsAreSet()
    {
        new SanitizeCookiesProcessor([
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
        $event->setRequest([
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

        $processor = new SanitizeCookiesProcessor($options);
        $event = $processor->process($event);

        $requestData = $event->getRequest();

        $this->assertArraySubset($expectedData, $requestData);
        $this->assertArrayNotHasKey('cookie', $requestData['headers']);
    }

    public function processDataProvider()
    {
        return [
            [
                [],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => SanitizeCookiesProcessor::STRING_MASK,
                        'bar' => SanitizeCookiesProcessor::STRING_MASK,
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
                        'foo' => SanitizeCookiesProcessor::STRING_MASK,
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
                        'bar' => SanitizeCookiesProcessor::STRING_MASK,
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
        ];
    }
}
