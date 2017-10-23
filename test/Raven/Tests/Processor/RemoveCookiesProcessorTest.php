<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_RemoveCookiesProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Raven_Processor_RemoveCookiesProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var \Raven_Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder('\Raven_Client')
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new Raven_Processor_RemoveCookiesProcessor($client);
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
                        'foo' => 'bar',
                    ),
                ),
                array(
                    'request' => array(
                        'foo' => 'bar',
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'foo' => 'bar',
                        'cookies' => 'baz',
                        'headers' => array(
                            'Cookie' => 'bar',
                            'AnotherHeader' => 'foo',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'foo' => 'bar',
                        'cookies' => Raven_Processor::STRING_MASK,
                        'headers' => array(
                            'Cookie' => Raven_Processor::STRING_MASK,
                            'AnotherHeader' => 'foo',
                        ),
                    ),
                ),
            ),
        );
    }
}
