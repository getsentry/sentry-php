<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_RemoveHttpBodyProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Raven_Processor_RemoveHttpBodyProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var \Raven_Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder('\Raven_Client')
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new Raven_Processor_RemoveHttpBodyProcessor($client);
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
                        'data' => Raven_Processor::STRING_MASK,
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
                        'data' => Raven_Processor::STRING_MASK,
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
                        'data' => Raven_Processor::STRING_MASK,
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
                        'data' => Raven_Processor::STRING_MASK,
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
