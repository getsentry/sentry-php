<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_SanitizeHttpHeadersProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Raven_Processor_SanitizeHttpHeadersProcessor|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $processor;

    protected function setUp()
    {
        /** @var \Raven_Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder('\Raven_Client')
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new Raven_Processor_SanitizeHttpHeadersProcessor($client);
        $this->processor->setProcessorOptions(array(
            'sanitize_http_headers' => array('User-Defined-Header'),
        ));
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
                        'headers' => array(
                            'Authorization' => 'foo',
                            'AnotherHeader' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'headers' => array(
                            'Authorization' => Raven_Processor::STRING_MASK,
                            'AnotherHeader' => 'bar',
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'request' => array(
                        'headers' => array(
                            'User-Defined-Header' => 'foo',
                            'AnotherHeader' => 'bar',
                        ),
                    ),
                ),
                array(
                    'request' => array(
                        'headers' => array(
                            'User-Defined-Header' => Raven_Processor::STRING_MASK,
                            'AnotherHeader' => 'bar',
                        ),
                    ),
                ),
            ),
        );
    }
}
