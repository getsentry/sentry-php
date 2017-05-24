<?php

namespace Raven\Tests;

use Raven\PSR3Client;

class Dummy_Stringable_Object
{
    public static $value;

    /**
     * @return string
     */
    public function __toString()
    {
        return strval(self::$value);
    }
}

class PSR3ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers \Raven\PSR3Client::interpolate
     */
    public function testInterpolate()
    {
        Dummy_Stringable_Object::$value = 'Message {username}';
        $object = new Dummy_Stringable_Object();
        $array = [
            ['foo', [], 'foo'],
            ['User {username} created', ['username' => 'bolivar'], 'User bolivar created'],
            ['User {username} created', [], 'User {username} created'],
            [$object, ['username' => 'labarum'], 'Message labarum'],
        ];
        foreach ($array as &$datum) {
            $actual_value = \Raven\PSR3Client::interpolate($datum[0], $datum[1]);
            $this->assertEquals($datum[2], $actual_value);
        }
    }

    public function data__construct_exception()
    {
        return [
            [$this],
            ['DSN'],
        ];
    }

    public function data__construct()
    {
        return [[true], [false],];
    }

    /**
     * @param boolean $u
     *
     * @covers       \Raven\PSR3Client::__construct
     * @dataProvider data__construct
     */
    public function test__construct($u)
    {
        if ($u) {
            $client = new PSR3Client(['name' => 'foobar']);
        } else {
            $client = new PSR3Client([]);
            $client->getClient()->name = 'foobar';
        }
        $client->getClient()->store_errors_for_bulk_send = true;
        $client->log(\Psr\Log\LogLevel::EMERGENCY, 'Cat says nya');
        $event = $client->getClient()->_pending_events[0];
        $this->assertEquals('foobar', $event['server_name']);
        $this->assertEquals('', $event['site']);
        $this->assertEquals('fatal', $event['level']);
        $this->assertEquals('Cat says nya', $event['message']);
    }

    /**
     * @param $param
     *
     * @expectedException \Psr\Log\InvalidArgumentException
     * @dataProvider data__construct_exception
     */
    public function test__construct_exception($param)
    {
        new \Raven\PSR3Client($param);
    }

    /**
     * @covers \Raven\PSR3Client::getSentryLogLevel
     */
    public function testGetSentryLogLevel()
    {
        $array = [
            \Psr\Log\LogLevel::EMERGENCY         => \Raven\Client::FATAL,
            \Psr\Log\LogLevel::CRITICAL          => \Raven\Client::FATAL,
            \Psr\Log\LogLevel::ERROR             => \Raven\Client::ERROR,
            \Psr\Log\LogLevel::WARNING           => \Raven\Client::WARNING,
            \Psr\Log\LogLevel::NOTICE            => \Raven\Client::INFO,
            \Psr\Log\LogLevel::INFO              => \Raven\Client::INFO,
            \Psr\Log\LogLevel::DEBUG             => \Raven\Client::DEBUG,
            \Psr\Log\LogLevel::ALERT             => \Raven\Client::ERROR,
            strtoupper(\Psr\Log\LogLevel::DEBUG) => null,
            'rnd_'.mt_rand(0, 100)               => null,
        ];
        foreach ($array as $key => &$value) {
            $actual_value = \Raven\PSR3Client::getSentryLogLevel($key);
            $this->assertEquals($value, $actual_value);
        }
    }

    /**
     * @covers \Raven\PSR3Client::log
     * @covers \Raven\PSR3Client::__construct
     * @covers \Raven\PSR3Client::getClient
     */
    public function testLog()
    {
        Dummy_Stringable_Object::$value = 'Message {username}';
        $object = new Dummy_Stringable_Object();
        $array = [
            ['foo', [], 'foo'],
            ['User {username} created', ['username' => 'bolivar'], 'User bolivar created'],
            [$object, ['username' => 'bolivar'], 'Message bolivar'],
        ];
        foreach ($array as &$datum) {
            $client = new PSR3Client(new Dummy_Raven_Client());
            $client->log(\Psr\Log\LogLevel::EMERGENCY, $datum[0], $datum[1]);

            $events = $client->getClient()->getSentEvents();
            $event = array_pop($events);
            $input = $client->getClient()->get_http_data();
            $this->assertEquals($input['request'], $event['request']);
            $this->assertArrayNotHasKey('release', $event);
            $this->assertArrayNotHasKey('environment', $event);
            $this->assertEquals($datum[2], $event['message']);
            $this->assertEquals(\Raven\Client::FATAL, $event['level']);
        }
    }

    /**
     * @covers \Raven\PSR3Client::log
     * @covers \Raven\PSR3Client::__construct
     */
    public function testLogWithMalformedMessage()
    {
        $u = false;
        $client = new PSR3Client(new Dummy_Raven_Client());
        try {
            /** @noinspection PhpParamsInspection */
            $client->error([], []);
        } catch (\Psr\Log\InvalidArgumentException $e) {
            $u = true;
        }
        $this->assertTrue($u, '\Raven\Client::log didn\'t throw Exception');
        unset($client);

        $u = false;
        $client = new PSR3Client(new Dummy_Raven_Client());
        try {
            $client->error(new \Raven\CurlHandler([]), []);
        } catch (\Psr\Log\InvalidArgumentException $e) {
            $u = true;
        }
        $this->assertTrue($u, '\Raven\Client::log didn\'t throw Exception');
    }

    /**
     * @covers \Raven\PSR3Client::log
     * @covers \Raven\PSR3Client::__construct
     */
    public function testLogWithMalformedLevel()
    {
        $u = false;
        $client = new PSR3Client(new Dummy_Raven_Client());
        try {
            $client->log('rnd_'.mt_rand(0, 1000), '', []);
        } catch (\Psr\Log\InvalidArgumentException $e) {
            $u = true;
        }
        $this->assertTrue($u, '\Raven\Client::log didn\'t throw Exception');
    }

    /**
     * @covers \Raven\PSR3Client::emergency
     * @covers \Raven\PSR3Client::alert
     * @covers \Raven\PSR3Client::critical
     * @covers \Raven\PSR3Client::error
     * @covers \Raven\PSR3Client::warning
     * @covers \Raven\PSR3Client::notice
     * @covers \Raven\PSR3Client::info
     * @covers \Raven\PSR3Client::debug
     * @covers \Raven\PSR3Client::__construct
     * @covers \Raven\PSR3Client::getClient
     */
    public function testPSR4_methods()
    {
        /**
         * @var string[] $levels
         */
        $levels = [
            \Psr\Log\LogLevel::EMERGENCY,
            \Psr\Log\LogLevel::CRITICAL,
            \Psr\Log\LogLevel::ERROR,
            \Psr\Log\LogLevel::WARNING,
            \Psr\Log\LogLevel::NOTICE,
            \Psr\Log\LogLevel::INFO,
            \Psr\Log\LogLevel::DEBUG,
            \Psr\Log\LogLevel::ALERT,
        ];
        foreach ($levels as &$level) {
            $sentry_level = \Raven\PSR3Client::getSentryLogLevel($level);
            $client = new PSR3Client(new Dummy_Raven_Client());
            $reflection_method = new \ReflectionMethod($client, $level);
            $reflection_method->invoke($client, 'User {username} created', ['username' => 'bolivar']);

            $events = $client->getClient()->getSentEvents();
            $event = array_pop($events);
            $input = $client->getClient()->get_http_data();
            $this->assertEquals($input['request'], $event['request']);
            $this->assertArrayNotHasKey('release', $event);
            $this->assertArrayNotHasKey('environment', $event);
            $this->assertEquals('User bolivar created', $event['message']);
            $this->assertEquals($sentry_level, $event['level']);
        }
    }
}
