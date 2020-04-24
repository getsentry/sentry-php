<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Dsn;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class OptionsTest extends TestCase
{
    /**
     * @group legacy
     *
     * @dataProvider optionsDataProvider
     */
    public function testConstructor($option, $value, $getterMethod): void
    {
        $configuration = new Options([$option => $value]);

        $this->assertEquals($value, $configuration->$getterMethod());
    }

    /**
     * @group legacy
     *
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
            ['in_app_include', ['foo', 'bar'], 'getInAppIncludedPaths', 'setInAppIncludedPaths'],
            ['project_root', 'baz', 'getProjectRoot', 'setProjectRoot'],
            ['logger', 'foo', 'getLogger', 'setLogger'],
            ['release', 'dev', 'getRelease', 'setRelease'],
            ['server_name', 'foo', 'getServerName', 'setServerName'],
            ['tags', ['foo', 'bar'], 'getTags', 'setTags'],
            ['error_types', 0, 'getErrorTypes', 'setErrorTypes'],
            ['max_breadcrumbs', 50, 'getMaxBreadcrumbs', 'setMaxBreadcrumbs'],
            ['before_send', static function (): void {}, 'getBeforeSendCallback', 'setBeforeSendCallback'],
            ['before_breadcrumb', static function (): void {}, 'getBeforeBreadcrumbCallback', 'setBeforeBreadcrumbCallback'],
            ['send_default_pii', true, 'shouldSendDefaultPii', 'setSendDefaultPii'],
            ['default_integrations', false, 'hasDefaultIntegrations', 'setDefaultIntegrations'],
            ['max_value_length', 50, 'getMaxValueLength', 'setMaxValueLength'],
            ['http_proxy', '127.0.0.1', 'getHttpProxy', 'setHttpProxy'],
            ['capture_silenced_errors', true, 'shouldCaptureSilencedErrors', 'setCaptureSilencedErrors'],
            ['max_request_body_size', 'small', 'getMaxRequestBodySize', 'setMaxRequestBodySize'],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider dsnOptionDataProvider
     *
     * @expectedDeprecationMessage Calling the method getDsn() and expecting it to return a string is deprecated since version 2.4 and will stop working in 3.0.
     */
    public function testDsnOption($value, ?string $expectedProjectId, ?string $expectedPublicKey, ?string $expectedSecretKey, ?string $expectedDsnAsString, ?Dsn $expectedDsnAsObject): void
    {
        $options = new Options(['dsn' => $value]);

        $this->assertSame($expectedProjectId, $options->getProjectId());
        $this->assertSame($expectedPublicKey, $options->getPublicKey());
        $this->assertSame($expectedSecretKey, $options->getSecretKey());
        $this->assertEquals($expectedDsnAsString, $options->getDsn());
        $this->assertEquals($expectedDsnAsObject, $options->getDsn(false));
    }

    public function dsnOptionDataProvider(): \Generator
    {
        yield [
            'http://public:secret@example.com/sentry/1',
            '1',
            'public',
            'secret',
            'http://example.com/sentry',
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
        ];

        yield [
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
            '1',
            'public',
            'secret',
            'http://example.com/sentry',
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
        ];

        yield [
            null,
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            'null',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            '(null)',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            false,
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            'false',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            '(false)',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            '',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            'empty',
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            '(empty)',
            null,
            null,
            null,
            null,
            null,
        ];
    }

    /**
     * @dataProvider dsnOptionThrowsOnInvalidValueDataProvider
     */
    public function testDsnOptionThrowsOnInvalidValue($value, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new Options(['dsn' => $value]);
    }

    public function dsnOptionThrowsOnInvalidValueDataProvider(): \Generator
    {
        yield [
            true,
            'The option "dsn" with value true is invalid.',
        ];

        yield [
            'foo',
            'The option "dsn" with value "foo" is invalid.',
        ];
    }

    /**
     * @dataProvider excludedExceptionsDataProvider
     */
    public function testIsExcludedException(array $excludedExceptions, \Throwable $exception, bool $result): void
    {
        $configuration = new Options(['excluded_exceptions' => $excludedExceptions]);

        $this->assertSame($result, $configuration->isExcludedException($exception, false));
    }

    public function excludedExceptionsDataProvider(): \Generator
    {
        yield [
            [\BadFunctionCallException::class, \BadMethodCallException::class],
            new \BadMethodCallException(),
            true,
        ];

        yield [
            [\BadFunctionCallException::class],
            new \Exception(),
            false,
        ];

        yield [
            [\Exception::class],
            new \BadFunctionCallException(),
            true,
        ];
    }

    /**
     * @dataProvider excludedPathProviders
     */
    public function testExcludedAppPathsPathRegressionWithFileName(string $value, string $expected): void
    {
        $configuration = new Options(['in_app_exclude' => [$value]]);

        $this->assertSame([$expected], $configuration->getInAppExcludedPaths());
    }

    public function excludedPathProviders(): array
    {
        return [
            ['some/path', 'some/path'],
            ['some/specific/file.php', 'some/specific/file.php'],
            [__DIR__, __DIR__],
            [__FILE__, __FILE__],
        ];
    }

    /**
     * @dataProvider includedPathProviders
     */
    public function testIncludedAppPathsOverrideExcludedAppPaths(string $value, string $expected)
    {
        $configuration = new Options(['in_app_include' => [$value]]);

        $this->assertSame([$expected], $configuration->getInAppIncludedPaths());
    }

    public function includedPathProviders(): array
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

    /**
     * @group legacy
     *
     * @dataProvider contextLinesOptionValidatesInputValueDataProvider
     */
    public function testContextLinesOptionValidatesInputValue(?int $value, ?string $expectedExceptionMessage): void
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(InvalidOptionsException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        } else {
            $this->expectNotToPerformAssertions();
        }

        new Options(['context_lines' => $value]);
    }

    public function contextLinesOptionValidatesInputValueDataProvider(): \Generator
    {
        yield [
            -1,
            'The option "context_lines" with value -1 is invalid.',
        ];

        yield [
            0,
            null,
        ];

        yield [
            1,
            null,
        ];

        yield [
            null,
            null,
        ];
    }

    /**
     * @group legacy
     * @backupGlobals enabled
     *
     * @expectedDeprecationMessage Calling the method getDsn() and expecting it to return a string is deprecated since version 2.4 and will stop working in 3.0.
     */
    public function testDsnOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_DSN'] = 'http://public@example.com/1';

        $options = new Options();

        $this->assertSame('http://example.com', $options->getDsn());
        $this->assertSame('public', $options->getPublicKey());
        $this->assertSame('1', $options->getProjectId());
    }

    /**
     * @backupGlobals enabled
     */
    public function testEnvironmentOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_ENVIRONMENT'] = 'test_environment';

        $options = new Options();

        $this->assertSame('test_environment', $options->getEnvironment());
    }

    /**
     * @backupGlobals enabled
     */
    public function testReleaseOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_RELEASE'] = '0.0.1';

        $options = new Options();

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
                new FrameContextifierIntegration(),
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
                new FrameContextifierIntegration(),
            ],
        ];
    }
}
