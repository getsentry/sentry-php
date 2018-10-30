<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Middleware;

use Sentry\Event;
use Sentry\Integration\SanitizeCookiesMiddleware;
use Sentry\Options;

class SanitizeCookiesMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage You can configure only one of "only" and "except" options.
     */
    public function testConstructorThrowsIfBothOnlyAndExceptOptionsAreSet()
    {
        new SanitizeCookiesMiddleware([
            'only' => ['foo'],
            'except' => ['bar'],
        ]);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $options, array $expectedData)
    {
        $event = new Event(new Options());
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

        $middleware = new SanitizeCookiesMiddleware($options);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $request = $returnedEvent->getRequest();

        $this->assertArraySubset($expectedData, $request);
        $this->assertArrayNotHasKey('cookie', $request['headers']);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [],
                [
                    'foo' => 'bar',
                    'cookies' => [
                        'foo' => SanitizeCookiesMiddleware::STRING_MASK,
                        'bar' => SanitizeCookiesMiddleware::STRING_MASK,
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
                        'foo' => SanitizeCookiesMiddleware::STRING_MASK,
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
                        'bar' => SanitizeCookiesMiddleware::STRING_MASK,
                    ],
                    'headers' => [
                        'another-header' => 'foo',
                    ],
                ],
            ],
        ];
    }
}
