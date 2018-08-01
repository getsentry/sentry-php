<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_SanitizeDataProcessorTest extends \PHPUnit\Framework\TestCase
{
    public function testDoesFilterHttpData()
    {
        $data = array(
            'request' => array(
                'data' => array(
                    'foo' => 'bar',
                    'password' => 'hello',
                    'the_secret' => 'hello',
                    'a_password_here' => 'hello',
                    'mypasswd' => 'hello',
                    'authorization' => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'card_number' => array(
                        '1111',
                        '2222',
                        '3333',
                        '4444'
                    )
                ),
            )
        );

        $client = new Dummy_Raven_Client();
        $processor = new Raven_Processor_SanitizeDataProcessor($client);
        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['password']);
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['the_secret']);
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['a_password_here']);
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['mypasswd']);
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['authorization']);

        $this->markTestIncomplete('Array scrubbing has not been implemented yet.');

        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['card_number']['0']);
    }

    public function testDoesFilterSessionId()
    {
        $data = array(
            'request' => array(
                'cookies' => array(
                    ini_get('session.name') => 'abc',
                ),
            )
        );

        $client = new Dummy_Raven_Client();
        $processor = new Raven_Processor_SanitizeDataProcessor($client);
        $processor->process($data);

        $cookies = $data['request']['cookies'];
        $this->assertEquals($cookies[ini_get('session.name')], Raven_Processor_SanitizeDataProcessor::STRING_MASK);
    }

    public function testDoesFilterCreditCard()
    {
        $data = array(
            'extra' => array(
                'ccnumba' => '4242424242424242',
            ),
        );

        $client = new Dummy_Raven_Client();
        $processor = new Raven_Processor_SanitizeDataProcessor($client);
        $processor->process($data);

        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $data['extra']['ccnumba']);
    }

    public function testSettingProcessorOptions()
    {
        $client     = new Dummy_Raven_Client();
        $processor  = new Raven_Processor_SanitizeDataProcessor($client);

        $this->assertEquals($processor->getFieldsRe(), '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i', 'got default fields');
        $this->assertEquals($processor->getValuesRe(), '/^(?:\d[ -]*?){13,19}$/', 'got default values');

        $options = array(
            'fields_re' => '/(api_token)/i',
            'values_re' => '/^(?:\d[ -]*?){15,16}$/'
        );

        $processor->setProcessorOptions($options);

        $this->assertEquals($processor->getFieldsRe(), '/(api_token)/i', 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), '/^(?:\d[ -]*?){15,16}$/', 'overwrote values');
    }

    /**
     * @dataProvider overrideDataProvider
     *
     * @param $processorOptions
     * @param $client_options
     * @param $dsn
     */
    public function testOverrideOptions($processorOptions, $client_options, $dsn)
    {
        $client = new Dummy_Raven_Client($dsn, $client_options);
        /**
         * @var Raven_Processor_SanitizeDataProcessor $processor
         */
        $processor = $client->processors[0];

        $this->assertInstanceOf('Raven_Processor_SanitizeDataProcessor', $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions['Raven_Processor_SanitizeDataProcessor']['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions['Raven_Processor_SanitizeDataProcessor']['values_re'], 'overwrote values');
    }

    /**
     * @depends testOverrideOptions
     * @dataProvider overrideDataProvider
     *
     * @param $processorOptions
     * @param $client_options
     * @param $dsn
     */
    public function testOverridenSanitize($processorOptions, $client_options, $dsn)
    {
        $data = array(
            'request' => array(
                'data' => array(
                    'foo'               => 'bar',
                    'password'          => 'hello',
                    'the_secret'        => 'hello',
                    'a_password_here'   => 'hello',
                    'mypasswd'          => 'hello',
                    'api_token'         => 'nioenio3nrio3jfny89nby9bhr#RML#R',
                    'authorization'     => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'card_number'   => array(
                        '1111111111111111',
                        '2222',
                    )
                ),
            )
        );

        $client = new Dummy_Raven_Client($dsn, $client_options);
        /**
         * @var Raven_Processor_SanitizeDataProcessor $processor
         */
        $processor = $client->processors[0];

        $this->assertInstanceOf('Raven_Processor_SanitizeDataProcessor', $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions['Raven_Processor_SanitizeDataProcessor']['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions['Raven_Processor_SanitizeDataProcessor']['values_re'], 'overwrote values');

        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar', 'did not alter foo');
        $this->assertEquals($vars['password'], 'hello', 'did not alter password');
        $this->assertEquals($vars['the_secret'], 'hello', 'did not alter the_secret');
        $this->assertEquals($vars['a_password_here'], 'hello', 'did not alter a_password_here');
        $this->assertEquals($vars['mypasswd'], 'hello', 'did not alter mypasswd');
        $this->assertEquals($vars['authorization'], 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=', 'did not alter authorization');
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['api_token'], 'masked api_token');

        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $vars['card_number']['0'], 'masked card_number[0]');
        $this->assertEquals($vars['card_number']['1'], $vars['card_number']['1'], 'did not alter card_number[1]');
    }

    /**
     * Provides data for testing overriding the processor options
     *
     * @return array
     */
    public static function overrideDataProvider()
    {
        $processorOptions = array(
            'Raven_Processor_SanitizeDataProcessor' => array(
                'fields_re' => '/(api_token)/i',
                'values_re' => '/^(?:\d[ -]*?){15,16}$/'
            )
        );

        $client_options = array(
            'processors' => array('Raven_Processor_SanitizeDataProcessor'),
            'processorOptions' => $processorOptions
        );

        $dsn = 'http://9aaa31f9a05b4e72aaa06aa8157a827a:9aa7aa82a9694a08a1a7589a2a035a9a@sentry.domain.tld/1';

        return array(
            array($processorOptions, $client_options, $dsn)
        );
    }

    public function testDoesFilterExceptionDataWithMultipleValues()
    {
        // Prerequisite: create an array with an 'exception' that contains 2 entry for 'values' key both containing at
        // least 1 key that must be masked (i.e. 'password') in one of their 'vars' array in 'frames'.
        $data = array(
            'exception' => array(
                'values' => array(
                    array(
                        'stacktrace' => array(
                            'frames' => array(
                                array(
                                    'vars' => array(
                                        'credentials' => array(
                                            'password' => 'secretPassword'
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'stacktrace' => array(
                            'frames' => array(
                                array(
                                    'vars' => array(
                                        'credentials' => array(
                                            'password' => 'anotherSecretPassword'
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $client = new Dummy_Raven_Client();
        $processor = new Raven_Processor_SanitizeDataProcessor($client);
        // Action
        $processor->process($data);

        // Expectation: make sure we mask password in both the values array
        $passwordValue0 = $data['exception']['values'][0]['stacktrace']['frames'][0]['vars']['credentials']['password'];
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $passwordValue0);
        $passwordValue1 = $data['exception']['values'][1]['stacktrace']['frames'][0]['vars']['credentials']['password'];
        $this->assertEquals(Raven_Processor_SanitizeDataProcessor::STRING_MASK, $passwordValue1);
    }
}
