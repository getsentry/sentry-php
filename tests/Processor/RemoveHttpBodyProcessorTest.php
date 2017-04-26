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
use Raven\Processor\RemoveHttpBodyProcessor;

class RemoveHttpBodyProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RemoveHttpBodyProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new RemoveHttpBodyProcessor($client);
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
        return array(
            array(
                array(
                    'request' => array(
                        'method' => 'POST',
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'method' => 'PUT',
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'method' => 'PATCH',
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'method' => 'DELETE',
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'data' => RemoveHttpBodyProcessor::STRING_MASK,
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'method' => 'GET',
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'data' => array(
                            'foo' => 'bar',
                        ),
                    ),
                ),
            ),
        );
    }
}
