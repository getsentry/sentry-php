<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_SanitizeDataProcessorTest extends PHPUnit_Framework_TestCase
{
    public function testDoesFilterHttpData()
    {
        $data = array(
            'sentry.interfaces.Http' => array(
                'data' => array(
                    'foo' => 'bar',
                    'password' => 'hello',
                    'the_secret' => 'hello',
                    'a_password_here' => 'hello',
                    'mypasswd' => 'hello',
                ),
            )
        );

        $client = $this->getMock('Client');
        $processor = new Raven_SanitizeDataProcessor($client);
        $result = $processor->process($data);

        $vars = $result['sentry.interfaces.Http']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals($vars['password'], Raven_SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['the_secret'], Raven_SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['a_password_here'], Raven_SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['mypasswd'], Raven_SanitizeDataProcessor::MASK);
    }

    public function testDoesFilterCreditCard()
    {
        $data = array(
            'ccnumba' => '4242424242424242'
        );

        $client = $this->getMock('Client');
        $processor = new Raven_SanitizeDataProcessor($client);
        $result = $processor->process($data);

        $this->assertEquals($result['ccnumba'], Raven_SanitizeDataProcessor::MASK);
    }
}
