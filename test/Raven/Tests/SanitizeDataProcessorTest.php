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

        $client = new Raven_Client();
        $processor = new Raven_SanitizeDataProcessor($client);
        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['password']);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['the_secret']);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['a_password_here']);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['mypasswd']);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['authorization']);

        $this->markTestIncomplete('Array scrubbing has not been implemented yet.');

        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['card_number']['0']);
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

        $client = new Raven_Client();
        $processor = new Raven_SanitizeDataProcessor($client);
        $processor->process($data);

        $cookies = $data['request']['cookies'];
        $this->assertEquals($cookies[ini_get('session.name')], Raven_SanitizeDataProcessor::MASK);
    }

    public function testDoesFilterCreditCard()
    {
        $data = array(
            'ccnumba'     => '4242424242424242',
            'log_message' => 'log message containing PAN 6759649826438453 more log',
        );

        $client = new Raven_Client();
        $processor = new Raven_SanitizeDataProcessor($client);
        $processor->process($data);

        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $data['ccnumba']);
        $this->assertEquals('log message containing PAN '.Raven_SanitizeDataProcessor::MASK.' more log', $data['log_message']);
    }

    /**
     * @covers setProcessorOptions
     *
     */
    public function testSettingProcessorOptions()
    {
        $client     = new Raven_Client();
        $processor  = new Raven_SanitizeDataProcessor($client);

        $this->assertEquals($processor->getFieldsRe(), Raven_SanitizeDataProcessor::FIELDS_RE, 'got default fields');
        $this->assertEquals($processor->getValuesRe(), Raven_SanitizeDataProcessor::VALUES_RE, 'got default values');

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
        $client = new Raven_Client($dsn, $client_options);
        $processor = $client->processors[0];

        $this->assertInstanceOf('Raven_SanitizeDataProcessor', $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions['Raven_SanitizeDataProcessor']['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions['Raven_SanitizeDataProcessor']['values_re'], 'overwrote values');
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
                ),
            )
        );

        $client = new Raven_Client($dsn, $client_options);
        $processor = $client->processors[0];

        $this->assertInstanceOf('Raven_SanitizeDataProcessor', $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions['Raven_SanitizeDataProcessor']['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions['Raven_SanitizeDataProcessor']['values_re'], 'overwrote values');

        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar', 'did not alter foo');
        $this->assertEquals($vars['password'], 'hello', 'did not alter password');
        $this->assertEquals($vars['the_secret'], 'hello', 'did not alter the_secret');
        $this->assertEquals($vars['a_password_here'], 'hello', 'did not alter a_password_here');
        $this->assertEquals($vars['mypasswd'], 'hello', 'did not alter mypasswd');
        $this->assertEquals($vars['authorization'], 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=', 'did not alter authorization');
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $vars['api_token'], 'masked api_token');
    }

    /**
     * Provides data for testing overriding the processor options
     *
     * @return array
     */
    public static function overrideDataProvider()
    {
        $processorOptions = array(
            'Raven_SanitizeDataProcessor' => array(
                'fields_re' => '/(api_token)/i',
                'values_re' => '/^(?:\d[ -]*?){15,16}$/'
            )
        );

        $client_options = array(
            'processors' => array('Raven_SanitizeDataProcessor'),
            'processorOptions' => $processorOptions
        );

        $dsn = 'http://9aaa31f9a05b4e72aaa06aa8157a827a:9aa7aa82a9694a08a1a7589a2a035a9a@sentry.domain.tld/1';

        return array(
            array($processorOptions, $client_options, $dsn)
        );
    }

    public function testPrivateKeysAreSanitizedByDefault()
    {
        $data = array(
            'public_key'  => '-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA6A6TQjlPyMurLh/igZY4
izA9sJgeZ7s5+nGydO4AI9k33gcy2DObZuadWRMnDwc3uH/qoAPw/mo3KOcgEtxU
xdwiQeATa3HVPcQDCQiKm8xIG2Ny0oUbR0IFNvClvx7RWnPEMk05CuvsL0AA3eH5
xn02Yg0JTLgZEtUT3whwFm8CAwEAAQ==
-----END PUBLIC KEY-----',
            'private_key' => '-----BEGIN PRIVATE KEY-----
MIIJRAIBADANBgkqhkiG9w0BAQEFAASCCS4wggkqAgEAAoICAQCoNFY4P+EeIXl0
mLpO+i8uFqAaEFQ8ZX2VVpA13kNEHuiWXC3HPlQ+7G+O3XmAsO+Wf/xY6pCSeQ8h
mLpO+i8uFqAaEFQ8ZX2VVpA13kNEHuiWXC3HPlQ+7G+O3XmAsO+Wf/xY6pCSeQ8h
-----END PRIVATE KEY-----',
            'encrypted_private_key' => '-----BEGIN ENCRYPTED PRIVATE KEY-----
MIIJjjBABgkqhkiG9w0BBQ0wMzAbBgkqhkiG9w0BBQwwDgQIWVhErdQOFVoCAggA
IrlYQUV1ig4U3viYh1Y8viVvRlANKICvgj4faYNH36UterkfDjzMonb/cXNeJEOS
YgorM2Pfuec5vtPRPKd88+Ds/ktIlZhjJwnJjHQMX+lSw5t0/juna2sLH2dpuAbi
PSk=
-----END ENCRYPTED PRIVATE KEY-----',
            'rsa_private_key' => '-----BEGIN RSA PRIVATE KEY-----
+wn9Iu+zgamKDUu22xc45F2gdwM04rTITlZgjAs6U1zcvOzGxk8mWJD5MqFWwAtF
zN87YGV0VMTG6ehxnkI4Fg6i0JPU3QIDAQABAoICAQCoCPjlYrODRU+vd2YeU/gM
THd+9FBxiHLGXNKhG/FRSyREXEt+NyYIf/0cyByc9tNksat794ddUqnLOg0vwSkv
-----END RSA PRIVATE KEY-----',
         'not_a_key' => 'decafbad'
        );

        $processor = new Raven_SanitizeDataProcessor(new Raven_Client());
        $processor->process($data);

        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $data["private_key"]);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $data["encrypted_private_key"]);
        $this->assertEquals(Raven_SanitizeDataProcessor::MASK, $data["rsa_private_key"]);
        $this->assertEquals('decafbad', $data["not_a_key"]);
    }
}
