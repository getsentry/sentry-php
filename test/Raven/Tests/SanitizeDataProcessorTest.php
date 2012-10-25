<?php

use Raven\Client;
use Raven\SanitizeDataProcessor;

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
                    'authorization' => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ='
                ),
            )
        );
		
        $client = new Client();
        $processor = new SanitizeDataProcessor($client);
        $processor->process($data);

        $vars = $data['sentry.interfaces.Http']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals($vars['password'], SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['the_secret'], SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['a_password_here'], SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['mypasswd'], SanitizeDataProcessor::MASK);
        $this->assertEquals($vars['authorization'], SanitizeDataProcessor::MASK);
    }

    public function testDoesFilterCreditCard()
    {
        $data = array(
            'ccnumba' => '4242424242424242'
        );

		
        $client = new Client();
        $processor = new SanitizeDataProcessor($client);
        $processor->process($data);

        $this->assertEquals($data['ccnumba'], SanitizeDataProcessor::MASK);
    }
}
