<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class OptionsTest extends TestCase
{
    /**
     * @dataProvider optionsDataProvider
     */
    public function testConstructor($option, $value, $getterMethod): void
    {
        $configuration = new Options([$option => $value]);

        $this->assertEquals($value, $configuration->$getterMethod());
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testGettersAndSetters(string $option, $value, string $getterMethod, ?string $setterMethod = null): void
    {
        $configuration = new Options();

        if (null !== $setterMethod) {
            $configuration->$setterMethod($value);
        }

        $this->assertEquals($value, $configuration->$getterMethod());
    }

    public function optionsDataProvider(): array
    {
        return [
            ['send_attempts', 1, 'getSendAttempts', 'setSendAttempts'],
            ['prefixes', ['foo', 'bar'], 'getPrefixes', 'setPrefixes'],
            ['sample_rate', 0.5, 'getSampleRate', 'setSampleRate'],
            ['attach_stacktrace', false, 'shouldAttachStacktrace', 'setAttachStacktrace'],
            ['context_lines', 3, 'getContextLines', 'setContextLines'],
            ['enable_compression', false, 'isCompressionEnabled', 'setEnableCompression'],
            ['environment', 'foo', 'getEnvironment', 'setEnvironment'],
            ['excluded_exceptions', ['foo', 'bar', 'baz'], 'getExcludedExceptions', 'setExcludedExceptions'],
            ['in_app_exclude', ['foo', 'bar'], 'getInAppExcludedPaths', 'setInAppExcludedPaths'],
            ['project_root', 'baz', 'getProjectRoot', 'setProjectRoot'],
            ['logger', 'foo', 'getLogger', 'setLogger'],
            ['release', 'dev', 'getRelease', 'setRelease'],
            ['server_name', 'foo', 'getServerName', 'setServerName'],
            ['tags', ['foo', 'bar'], 'getTags', 'setTags'],
            ['error_types', 0, 'getErrorTypes', 'setErrorTypes'],
            ['max_breadcrumbs', 50, 'getMaxBreadcrumbs', 'setMaxBreadcrumbs'],
            ['before_send', function () {}, 'getBeforeSendCallback', 'setBeforeSendCallback'],
            ['before_breadcrumb', function () {}, 'getBeforeBreadcrumbCallback', 'setBeforeBreadcrumbCallback'],
            ['send_default_pii', true, 'shouldSendDefaultPii', 'setSendDefaultPii'],
            ['default_integrations', false, 'hasDefaultIntegrations', 'setDefaultIntegrations'],
            ['max_value_length', 50, 'getMaxValueLength', 'setMaxValueLength'],
            ['http_proxy', '127.0.0.1', 'getHttpProxy', 'setHttpProxy'],
            ['capture_silenced_errors', true, 'shouldCaptureSilencedErrors', 'setCaptureSilencedErrors'],
            ['max_request_body_size', 'small', 'getMaxRequestBodySize', 'setMaxRequestBodySize'],
        ];
    }

    /**
     * @dataProvider serverOptionDataProvider
     */
    public function testServerOption(string $dsn, array $options): void
    {
        $configuration = new Options(['dsn' => $dsn]);

        $this->assertEquals($options['project_id'], $configuration->getProjectId());
        $this->assertEquals($options['public_key'], $configuration->getPublicKey());
        $this->assertEquals($options['secret_key'], $configuration->getSecretKey());
        $this->assertEquals($options['server'], $configuration->getDsn());
    }

    public function serverOptionDataProvider(): array
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
    public function testServerOptionsWithInvalidServer(string $dsn): void
    {
        new Options(['dsn' => $dsn]);
    }

    public function invalidServerOptionDataProvider(): array
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
        $configuration = new Options(['in_app_exclude' => [$value]]);

        $this->assertSame([$expected], $configuration->getInAppExcludedPaths());
    }

    public function excludedPathProviders()
    {
        return [
            ['some/path', 'some/path'],
            ['some/specific/file.php', 'some/specific/file.php'],
            [__DIR__, __DIR__],
            [__FILE__, __FILE__],
        ];
    }

    /**
     * @dataProvider maxBreadcrumbsOptionIsValidatedCorrectlyDataProvider
     */
    public function testMaxBreadcrumbsOptionIsValidatedCorrectly(bool $isValid, $value): void
    {
        if (!$isValid) {
            $this->expectException(InvalidOptionsException::class);
        }

        $options = new Options(['max_breadcrumbs' => $value]);

        $this->assertSame($value, $options->getMaxBreadcrumbs());
    }

    public function maxBreadcrumbsOptionIsValidatedCorrectlyDataProvider(): array
    {
        return [
            [false, -1],
            [true, 0],
            [true, 1],
            [true, Options::DEFAULT_MAX_BREADCRUMBS],
            [false, Options::DEFAULT_MAX_BREADCRUMBS + 1],
            [false, 'string'],
            [false, '1'],
        ];
    }

    public function testDsnOptionSupportsEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_DSN'] = 'http://public@example.com/1';

        $options = new Options();

        unset($_SERVER['SENTRY_DSN']);

        $this->assertSame('http://example.com', $options->getDsn());
        $this->assertSame('public', $options->getPublicKey());
        $this->assertSame('1', $options->getProjectId());
    }

    public function testEnvironmentOptionSupportsEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_ENVIRONMENT'] = 'test_environment';

        $options = new Options();

        unset($_SERVER['SENTRY_ENVIRONMENT']);

        $this->assertSame('test_environment', $options->getEnvironment());
    }

    public function testReleaseOptionSupportsEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_RELEASE'] = '0.0.1';

        $options = new Options();

        unset($_SERVER['SENTRY_RELEASE']);

        $this->assertSame('0.0.1', $options->getRelease());
    }

    /**
     * @dataProvider integrationsOptionAsCallableDataProvider
     */
    public function testIntegrationsOptionAsCallable(bool $useDefaultIntegrations, $integrations, array $expectedResult): void
    {
        $options = new Options([
            'default_integrations' => $useDefaultIntegrations,
            'integrations' => $integrations,
        ]);

        $this->assertEquals($expectedResult, $options->getIntegrations());
    }

    public function integrationsOptionAsCallableDataProvider(): \Generator
    {
        yield 'No default integrations && no user integrations' => [
            false,
            [],
            [],
        ];

        $integration = new class() implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        yield 'User integration added && default integration appearing only once' => [
            true,
            [
                $integration,
                new ExceptionListenerIntegration(),
            ],
            [
                new ExceptionListenerIntegration(),
                new ErrorListenerIntegration(null, false),
                new FatalErrorListenerIntegration(),
                new RequestIntegration(),
                new TransactionIntegration(),
                $integration,
            ],
        ];

        $integration = new class() implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        yield 'User integration added twice' => [
            false,
            [
                $integration,
                $integration,
            ],
            [
                $integration,
            ],
        ];

        yield 'User integrations as callable returning empty list' => [
            true,
            static function (): array {
                return [];
            },
            [],
        ];

        $integration = new class() implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        yield 'User integrations as callable returning custom list' => [
            true,
            static function () use ($integration): array {
                return [$integration];
            },
            [$integration],
        ];

        yield 'User integrations as callable returning $defaultIntegrations argument' => [
            true,
            static function (array $defaultIntegrations): array {
                return $defaultIntegrations;
            },
            [
                new ExceptionListenerIntegration(),
                new ErrorListenerIntegration(null, false),
                new FatalErrorListenerIntegration(),
                new RequestIntegration(),
                new TransactionIntegration(),
            ],
        ];
    }
}
