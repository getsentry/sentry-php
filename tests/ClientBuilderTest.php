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

use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;

class ClientBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $clientBuilder = ClientBuilder::create();

        $this->assertInstanceOf(ClientBuilder::class, $clientBuilder);
    }

    public function testGetClient()
    {
        $clientBuilder = new ClientBuilder();

        $this->assertInstanceOf(Client::class, $clientBuilder->getClient());
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage The method named "methodThatDoesNotExists" does not exists.
     */
    public function testCallInvalidMethodThrowsException()
    {
        $clientBuilder = new ClientBuilder();
        $clientBuilder->methodThatDoesNotExists();
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testCallExistingMethodForwardsCallToConfiguration($setterMethod, $value)
    {
        $configuration = $this->getMockBuilder(Configuration::class)
            ->getMock();

        $configuration->expects($this->once())
            ->method($setterMethod)
            ->with($this->equalTo($value));

        $clientBuilder = new ClientBuilder();

        $reflectionProperty = new \ReflectionProperty(ClientBuilder::class, 'configuration');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($clientBuilder, $configuration);
        $reflectionProperty->setAccessible(false);

        $clientBuilder->$setterMethod($value);
    }

    public function optionsDataProvider()
    {
        return [
            ['setIsTrustXForwardedProto', true],
            ['setPrefixes', ['foo', 'bar']],
            ['setSerializeAllObjects', false],
            ['setCurlPath', 'foo'],
            ['setCurlIpv4', true],
            ['setCurlSslVersion', CURL_SSLVERSION_DEFAULT],
            ['setSampleRate', 0.5],
            ['setInstallDefaultBreadcrumbHandlers', false],
            ['setInstallShutdownHandler', false],
            ['setMbDetectOrder', ['foo', 'bar']],
            ['setAutoLogStacks', false],
            ['setContextLines', 0],
            ['setCurrentEnvironment', 'test'],
            ['setEnvironments', ['default']],
            ['setExcludedLoggers', ['foo', 'bar']],
            ['setExcludedExceptions', ['foo', 'bar']],
            ['setExcludedProjectPaths', ['foo', 'bar']],
            ['setTransport', null],
            ['setProjectRoot', 'foo'],
            ['setLogger', 'bar'],
            ['setOpenTimeout', 1],
            ['setTimeout', 3],
            ['setProxy', 'foo'],
            ['setRelease', 'dev'],
            ['setServerName', 'example.com'],
            ['setSslOptions', ['foo' => 'bar']],
            ['setSslVerificationEnabled', false],
            ['setSslCaFile', 'foo'],
            ['setTags', ['foo', 'bar']],
            ['setErrorTypes', 0],
            ['setProcessors', ['foo']],
            ['setProcessorsOptions', ['foo']],
        ];
    }
}
