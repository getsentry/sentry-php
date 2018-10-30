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
use Sentry\Integration\SanitizeHttpHeadersMiddleware;
use Sentry\Options;

class SanitizeHttpHeadersMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($inputData, $expectedData)
    {
        $event = new Event(new Options());
        $event->setRequest($inputData);

        $middleware = new SanitizeHttpHeadersMiddleware([
            'sanitize_http_headers' => ['User-Defined-Header'],
        ]);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertArraySubset($expectedData, $returnedEvent->getRequest());
    }

    public function invokeDataProvider()
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
                        'User-Defined-Header' => SanitizeHttpHeadersMiddleware::STRING_MASK,
                    ],
                ],
            ],
        ];
    }
}
