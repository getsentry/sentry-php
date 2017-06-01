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

use Raven\Processor\SanitizeDataProcessor;

class SanitizeDataProcessorTest extends \PHPUnit_Framework_TestCase
{
    public function testDoesFilterHttpData()
    {
        $data = [
            'request' => [
                'data' => [
                    'foo' => 'bar',
                    'password' => 'hello',
                    'the_secret' => 'hello',
                    'a_password_here' => 'hello',
                    'mypasswd' => 'hello',
                    'authorization' => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'card_number' => [
                        '1111',
                        '2222',
                        '3333',
                        '4444'
                    ]
                ],
            ]
        ];

        $client = new Dummy_Raven_Client();
        $processor = new SanitizeDataProcessor($client);
        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar');
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['password']);
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['the_secret']);
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['a_password_here']);
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['mypasswd']);
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['authorization']);

        $this->markTestIncomplete('Array scrubbing has not been implemented yet.');

        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['card_number']['0']);
    }

    public function testDoesFilterSessionId()
    {
        $data = [
            'request' => [
                'cookies' => [
                    ini_get('session.name') => 'abc',
                ],
            ]
        ];

        $client = new Dummy_Raven_Client();
        $processor = new SanitizeDataProcessor($client);
        $processor->process($data);

        $cookies = $data['request']['cookies'];
        $this->assertEquals($cookies[ini_get('session.name')], SanitizeDataProcessor::STRING_MASK);
    }

    public function testDoesFilterCreditCard()
    {
        $data = [
            'extra' => [
                'ccnumba' => '4242424242424242',
            ],
        ];

        $client = new Dummy_Raven_Client();
        $processor = new SanitizeDataProcessor($client);
        $processor->process($data);

        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $data['extra']['ccnumba']);
    }

    public function testSettingProcessorOptions()
    {
        $client     = new Dummy_Raven_Client();
        $processor  = new SanitizeDataProcessor($client);

        $this->assertEquals($processor->getFieldsRe(), '/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i', 'got default fields');
        $this->assertEquals($processor->getValuesRe(), '/^(?:\d[ -]*?){13,16}$/', 'got default values');

        $options = [
            'fields_re' => '/(api_token)/i',
            'values_re' => '/^(?:\d[ -]*?){15,16}$/'
        ];

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
         * @var SanitizeDataProcessor $processor
         */
        $processors = new \ReflectionProperty($client, 'processors');
        $processors->setAccessible(true);
        $processor = $processors->getValue($client)[0];

        $this->assertInstanceOf(SanitizeDataProcessor::class, $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions[SanitizeDataProcessor::class]['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions[SanitizeDataProcessor::class]['values_re'], 'overwrote values');
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
        $data = [
            'request' => [
                'data' => [
                    'foo'               => 'bar',
                    'password'          => 'hello',
                    'the_secret'        => 'hello',
                    'a_password_here'   => 'hello',
                    'mypasswd'          => 'hello',
                    'api_token'         => 'nioenio3nrio3jfny89nby9bhr#RML#R',
                    'authorization'     => 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'card_number'   => [
                        '1111111111111111',
                        '2222',
                    ]
                ],
            ]
        ];

        $client = new Dummy_Raven_Client($dsn, $client_options);
        /**
         * @var SanitizeDataProcessor $processor
         */
        $processors = new \ReflectionProperty($client, 'processors');
        $processors->setAccessible(true);
        $processor = $processors->getValue($client)[0];

        $this->assertInstanceOf(SanitizeDataProcessor::class, $processor);
        $this->assertEquals($processor->getFieldsRe(), $processorOptions[SanitizeDataProcessor::class]['fields_re'], 'overwrote fields');
        $this->assertEquals($processor->getValuesRe(), $processorOptions[SanitizeDataProcessor::class]['values_re'], 'overwrote values');

        $processor->process($data);

        $vars = $data['request']['data'];
        $this->assertEquals($vars['foo'], 'bar', 'did not alter foo');
        $this->assertEquals($vars['password'], 'hello', 'did not alter password');
        $this->assertEquals($vars['the_secret'], 'hello', 'did not alter the_secret');
        $this->assertEquals($vars['a_password_here'], 'hello', 'did not alter a_password_here');
        $this->assertEquals($vars['mypasswd'], 'hello', 'did not alter mypasswd');
        $this->assertEquals($vars['authorization'], 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=', 'did not alter authorization');
        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['api_token'], 'masked api_token');

        $this->assertEquals(SanitizeDataProcessor::STRING_MASK, $vars['card_number']['0'], 'masked card_number[0]');
        $this->assertEquals($vars['card_number']['1'], $vars['card_number']['1'], 'did not alter card_number[1]');
    }

    /**
     * Provides data for testing overriding the processor options
     *
     * @return array
     */
    public static function overrideDataProvider()
    {
        $processorOptions = [
            SanitizeDataProcessor::class => [
                'fields_re' => '/(api_token)/i',
                'values_re' => '/^(?:\d[ -]*?){15,16}$/',
            ],
        ];

        $client_options = [
            'processors' => [SanitizeDataProcessor::class],
            'processorOptions' => $processorOptions
        ];

        $dsn = 'http://9aaa31f9a05b4e72aaa06aa8157a827a:9aa7aa82a9694a08a1a7589a2a035a9a@sentry.domain.tld/1';

        return [
            [$processorOptions, $client_options, $dsn]
        ];
    }
}
