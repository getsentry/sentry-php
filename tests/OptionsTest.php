<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Options;

class OptionsTest extends TestCase
{
    /**
     * @dataProvider optionsDataProvider
     */
    public function testConstructor($option, $value, $getterMethod)
    {
        $configuration = new Options([$option => $value]);

        $this->assertEquals($value, $configuration->$getterMethod());
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testGettersAndSetters($option, $value, $getterMethod, $setterMethod = null)
    {
        $configuration = new Options();

        if (null !== $setterMethod) {
            $configuration->$setterMethod($value);
        }

        $this->assertEquals($value, $configuration->$getterMethod());
    }

    public function optionsDataProvider()
    {
        return [
            ['send_attempts', 1, 'getSendAttempts', 'setSendAttempts'],
            ['prefixes', ['foo', 'bar'], 'getPrefixes', 'setPrefixes'],
            ['serialize_all_object', false, 'getSerializeAllObjects', 'setSerializeAllObjects'],
            ['sample_rate', 0.5, 'getSampleRate', 'setSampleRate'],
            ['mb_detect_order', null, 'getMbDetectOrder', 'setMbDetectOrder'],
            ['mb_detect_order', 'UTF-8', 'getMbDetectOrder', 'setMbDetectOrder'],
            ['mb_detect_order', ['UTF-8'], 'getMbDetectOrder', 'setMbDetectOrder'],
            ['auto_log_stacks', false, 'getAutoLogStacks', 'setAutoLogStacks'],
            ['context_lines', 3, 'getContextLines', 'setContextLines'],
            ['encoding', 'json', 'getEncoding', 'setEncoding'],
            ['current_environment', 'foo', 'getCurrentEnvironment', 'setCurrentEnvironment'],
            ['environments', ['foo', 'bar'], 'getEnvironments', 'setEnvironments'],
            ['excluded_loggers', ['bar', 'foo'], 'getExcludedLoggers', 'setExcludedLoggers'],
            ['excluded_exceptions', ['foo', 'bar', 'baz'], 'getExcludedExceptions', 'setExcludedExceptions'],
            ['excluded_app_paths', ['foo', 'bar'], 'getExcludedProjectPaths', 'setExcludedProjectPaths'],
            ['project_root', 'baz', 'getProjectRoot', 'setProjectRoot'],
            ['logger', 'foo', 'getLogger', 'setLogger'],
            ['release', 'dev', 'getRelease', 'setRelease'],
            ['server_name', 'foo', 'getServerName', 'setServerName'],
            ['tags', ['foo', 'bar'], 'getTags', 'setTags'],
            ['error_types', 0, 'getErrorTypes', 'setErrorTypes'],
            ['max_breadcrumbs', 50, 'getMaxBreadcrumbs', 'setMaxBreadcrumbs'],
            ['before_send', function () {}, 'getBeforeSendCallback', 'setBeforeSendCallback'],
            ['before_breadcrumb', function () {}, 'getBeforeBreadcrumbCallback', 'setBeforeBreadcrumbCallback'],
        ];
    }

    /**
     * @dataProvider serverOptionDataProvider
     */
    public function testServerOption($dsn, $options)
    {
        $configuration = new Options(['dsn' => $dsn]);

        $this->assertEquals($options['project_id'], $configuration->getProjectId());
        $this->assertEquals($options['public_key'], $configuration->getPublicKey());
        $this->assertEquals($options['secret_key'], $configuration->getSecretKey());
        $this->assertEquals($options['server'], $configuration->getDsn());
    }

    public function serverOptionDataProvider()
    {
        return [
            [
                'http://public@example.com/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => null,
                    'server' => 'http://example.com',
                ],
            ],
            [
                'http://public:secret@example.com/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'http://example.com',
                ],
            ],
            [
                'http://public:secret@example.com:80/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'http://example.com',
                ],
            ],
            [
                'https://public:secret@example.com/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'https://example.com',
                ],
            ],
            [
                'https://public:secret@example.com:443/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'https://example.com',
                ],
            ],
            [
                'http://public:secret@example.com/sentry/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'http://example.com/sentry',
                ],
            ],
            [
                'http://public:secret@example.com:3000/sentry/1',
                [
                    'project_id' => 1,
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'server' => 'http://example.com:3000/sentry',
                ],
            ],
        ];
    }

    /**
     * @dataProvider invalidServerOptionDataProvider
     *
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessageRegExp /^The option "dsn" with value "(.*)" is invalid.$/
     */
    public function testServerOptionsWithInvalidServer($dsn)
    {
        new Options(['dsn' => $dsn]);
    }

    public function invalidServerOptionDataProvider()
    {
        return [
            ['http://public:secret@/1'],
            ['http://public:secret@example.com'],
            ['http://:secret@example.com/1'],
            ['http://public:@example.com'],
            ['tcp://public:secret@example.com/1'],
        ];
    }

    /**
     * @dataProvider disabledDsnProvider
     */
    public function testParseDSNWithDisabledValue($dsn)
    {
        $configuration = new Options(['dsn' => $dsn]);

        $this->assertNull($configuration->getProjectId());
        $this->assertNull($configuration->getPublicKey());
        $this->assertNull($configuration->getSecretKey());
        $this->assertNull($configuration->getDsn());
    }

    public function disabledDsnProvider()
    {
        return [
            [null],
            ['null'],
            [false],
            ['false'],
            [''],
            ['empty'],
        ];
    }

    /**
     * @dataProvider excludedExceptionsDataProvider
     */
    public function testIsExcludedException($excludedExceptions, $exception, $result)
    {
        $configuration = new Options(['excluded_exceptions' => $excludedExceptions]);

        $this->assertSame($result, $configuration->isExcludedException($exception));
    }

    public function excludedExceptionsDataProvider()
    {
        return [
            [
                [\BadFunctionCallException::class, \BadMethodCallException::class],
                new \BadMethodCallException(),
                true,
            ],
            [
                [\BadFunctionCallException::class],
                new \Exception(),
                false,
            ],
            [
                [\Exception::class],
                new \BadFunctionCallException(),
                true,
            ],
        ];
    }

    /**
     * @dataProvider excludedPathProviders
     *
     * @param string $value
     * @param string $expected
     */
    public function testExcludedAppPathsPathRegressionWithFileName($value, $expected)
    {
        $configuration = new Options(['excluded_app_paths' => [$value]]);

        $this->assertSame([$expected], $configuration->getExcludedProjectPaths());
    }

    public function excludedPathProviders()
    {
        return [
            ['some/path', 'some/path'],
            ['some/specific/file.php', 'some/specific/file.php'],
            [__DIR__, __DIR__ . '/'],
            [__FILE__, __FILE__],
        ];
    }
}
