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
            ['in_app_exclude', ['foo', 'bar'], 'getInAppExcludedPaths', 'setInAppExcludedPaths'],
            ['in_app_include', ['foo', 'bar'], 'getInAppIncludedPaths', 'setInAppIncludedPaths'],
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
     * @dataProvider dsnOptionDataProvider
     */
    public function testDsnOption($value, ?Dsn $expectedDsnAsObject): void
    {
        $options = new Options(['dsn' => $value]);

        $this->assertEquals($expectedDsnAsObject, $options->getDsn());
    }

    public function dsnOptionDataProvider(): \Generator
    {
        yield [
            'http://public:secret@example.com/sentry/1',
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
        ];

        yield [
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
        ];

        yield [
            null,
            null,
        ];

        yield [
            'null',
            null,
        ];

        yield [
            '(null)',
            null,
        ];

        yield [
            false,
            null,
        ];

        yield [
            'false',
            null,
        ];

        yield [
            '(false)',
            null,
        ];

        yield [
            '',
            null,
        ];

        yield [
            'empty',
            null,
        ];

        yield [
            '(empty)',
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
     * @backupGlobals enabled
     */
    public function testDsnOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_DSN'] = 'http://public@example.com/1';

        $this->assertEquals(Dsn::createFromString($_SERVER['SENTRY_DSN']), (new Options())->getDsn());
    }

    /**
     * @backupGlobals enabled
     */
    public function testEnvironmentOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_ENVIRONMENT'] = 'test_environment';

        $this->assertSame('test_environment', (new Options())->getEnvironment());
    }

    /**
     * @backupGlobals enabled
     */
    public function testReleaseOptionDefaultValueIsGotFromEnvironmentVariable(): void
    {
        $_SERVER['SENTRY_RELEASE'] = '0.0.1';

        $this->assertSame('0.0.1', (new Options())->getRelease());
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
