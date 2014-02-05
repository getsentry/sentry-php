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
                    'authorization' => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'cvv' => '1234',
                    'cardNumber' => '232323',
                    'expirationDate' => '2013-12',
                ),
            )
        );

        $client = new Raven_Client();
        $processor = new Raven_SanitizeDataProcessor($client);
        $processor->process($data);

        $vars = $data['sentry.interfaces.Http']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals($vars['password'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['the_secret'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['a_password_here'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['mypasswd'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['authorization'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['cvv'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['cardNumber'], Raven_SanitizeDataProcessor::$mask);
        $this->assertEquals($vars['expirationDate'], Raven_SanitizeDataProcessor::$mask);
    }

    public function testDoesFilterCreditCard()
    {
        $data = array(
            'ccnumba' => '4242424242424242'
        );

        $client = new Raven_Client();
        $processor = new Raven_SanitizeDataProcessor($client);
        $processor->process($data);

        $this->assertEquals($data['ccnumba'], Raven_SanitizeDataProcessor::$mask);
    }
}
