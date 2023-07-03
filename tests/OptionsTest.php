<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Dsn;
use Sentry\Options;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class OptionsTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var int
     */
    private $errorReportingOnSetUp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorReportingOnSetUp = error_reporting();
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReportingOnSetUp);

        parent::tearDown();
    }

    /**
     * @group legacy
     *
     * @dataProvider optionsDataProvider
     */
    public function testConstructor(
        string $option,
        $value,
        string $getterMethod,
        ?string $setterMethod,
        ?string $expectedGetterDeprecationMessage
    ): void {
        if (null !== $expectedGetterDeprecationMessage) {
            $this->expectDeprecation($expectedGetterDeprecationMessage);
        }

        $options = new Options([$option => $value]);

        $this->assertEquals($value, $options->$getterMethod());
    }

    /**
     * @group legacy
     *
     * @dataProvider optionsDataProvider
     */
    public function testGettersAndSetters(
        string $option,
        $value,
        string $getterMethod,
        ?string $setterMethod,
        ?string $expectedGetterDeprecationMessage,
        ?string $expectedSetterDeprecationMessage
    ): void {
        if (null !== $expectedSetterDeprecationMessage) {
            $this->expectDeprecation($expectedSetterDeprecationMessage);
        }

        if (null !== $expectedGetterDeprecationMessage) {
            $this->expectDeprecation($expectedGetterDeprecationMessage);
        }

        $options = new Options();

        if (null !== $setterMethod) {
            $options->$setterMethod($value);
        }

        $this->assertEquals($value, $options->$getterMethod());
    }

    public static function optionsDataProvider(): \Generator
    {
        yield [
            'send_attempts',
            1,
            'getSendAttempts',
            'setSendAttempts',
            'Method Sentry\\Options::getSendAttempts() is deprecated since version 3.5 and will be removed in 4.0.',
            'Method Sentry\\Options::setSendAttempts() is deprecated since version 3.5 and will be removed in 4.0.',
        ];

        yield [
            'prefixes',
            ['foo', 'bar'],
            'getPrefixes',
            'setPrefixes',
            null,
            null,
        ];

        yield [
            'sample_rate',
            0.5,
            'getSampleRate',
            'setSampleRate',
            null,
            null,
        ];

        yield [
            'traces_sample_rate',
            0.5,
            'getTracesSampleRate',
            'setTracesSampleRate',
            null,
            null,
        ];

        yield [
            'traces_sample_rate',
            null,
            'getTracesSampleRate',
            'setTracesSampleRate',
            null,
            null,
        ];

        yield [
            'traces_sampler',
            static function (): void {},
            'getTracesSampler',
            'setTracesSampler',
            null,
            null,
        ];

        yield [
            'enable_tracing',
            true,
            'getEnableTracing',
            'setEnableTracing',
            null,
            null,
        ];

        yield [
            'profiles_sample_rate',
            0.5,
            'getProfilesSampleRate',
            'setProfilesSampleRate',
            null,
            null,
        ];

        yield [
            'attach_stacktrace',
            false,
            'shouldAttachStacktrace',
            'setAttachStacktrace',
            null,
            null,
        ];

        yield [
            'context_lines',
            3,
            'getContextLines',
            'setContextLines',
            null,
            null,
        ];

        yield [
            'enable_compression',
            false,
            'isCompressionEnabled',
            'setEnableCompression',
            null,
            null,
        ];

        yield [
            'environment',
            'foo',
            'getEnvironment',
            'setEnvironment',
            null,
            null,
        ];

        yield [
            'in_app_exclude',
            ['foo', 'bar'],
            'getInAppExcludedPaths',
            'setInAppExcludedPaths',
            null,
            null,
        ];

        yield [
            'in_app_include',
            ['foo', 'bar'],
            'getInAppIncludedPaths',
            'setInAppIncludedPaths',
            null,
            null,
        ];

        yield [
            'logger',
            'foo',
            'getLogger',
            'setLogger',
            'Method Sentry\\Options::getLogger() is deprecated since version 3.2 and will be removed in 4.0.',
            'Method Sentry\\Options::setLogger() is deprecated since version 3.2 and will be removed in 4.0.',
        ];

        yield [
            'release',
            'dev',
            'getRelease',
            'setRelease',
            null,
            null,
        ];

        yield [
            'server_name',
            'foo',
            'getServerName',
            'setServerName',
            null,
            null,
        ];

        yield [
            'tags',
            ['foo', 'bar'],
            'getTags',
            'setTags',
            'Method Sentry\\Options::getTags() is deprecated since version 3.2 and will be removed in 4.0.',
            'Method Sentry\\Options::setTags() is deprecated since version 3.2 and will be removed in 4.0. Use Sentry\\Scope::setTags() instead.',
        ];

        yield [
            'error_types',
            0,
            'getErrorTypes',
            'setErrorTypes',
            null,
            null,
        ];

        yield [
            'max_breadcrumbs',
            50,
            'getMaxBreadcrumbs',
            'setMaxBreadcrumbs',
            null,
            null,
        ];

        yield [
            'ignore_exceptions',
            ['foo', 'bar'],
            'getIgnoreExceptions',
            'setIgnoreExceptions',
            null,
            null,
        ];

        yield [
            'ignore_transactions',
            ['foo', 'bar'],
            'getIgnoreTransactions',
            'setIgnoreTransactions',
            null,
            null,
        ];

        yield [
            'before_send',
            static function (): void {},
            'getBeforeSendCallback',
            'setBeforeSendCallback',
            null,
            null,
        ];

        yield [
            'before_send_transaction',
            static function (): void {},
            'getBeforeSendTransactionCallback',
            'setBeforeSendTransactionCallback',
            null,
            null,
        ];

        yield [
            'trace_propagation_targets',
            ['www.example.com'],
            'getTracePropagationTargets',
            'setTracePropagationTargets',
            null,
            null,
        ];

        yield [
            'before_breadcrumb',
            static function (): void {},
            'getBeforeBreadcrumbCallback',
            'setBeforeBreadcrumbCallback',
            null,
            null,
        ];

        yield [
            'send_default_pii',
            true,
            'shouldSendDefaultPii',
            'setSendDefaultPii',
            null,
            null,
        ];

        yield [
            'default_integrations',
            false,
            'hasDefaultIntegrations',
            'setDefaultIntegrations',
            null,
            null,
        ];

        yield [
            'max_value_length',
            50,
            'getMaxValueLength',
            'setMaxValueLength',
            null,
            null,
        ];

        yield [
            'http_proxy',
            '127.0.0.1',
            'getHttpProxy',
            'setHttpProxy',
            null,
            null,
        ];

        yield [
            'http_timeout',
            1,
            'getHttpTimeout',
            'setHttpTimeout',
            null,
            null,
        ];

        yield [
            'http_timeout',
            1.2,
            'getHttpTimeout',
            'setHttpTimeout',
            null,
            null,
        ];

        yield [
            'http_connect_timeout',
            1,
            'getHttpConnectTimeout',
            'setHttpConnectTimeout',
            null,
            null,
        ];

        yield [
            'http_connect_timeout',
            1.2,
            'getHttpConnectTimeout',
            'setHttpConnectTimeout',
            null,
            null,
        ];

        yield [
            'capture_silenced_errors',
            true,
            'shouldCaptureSilencedErrors',
            'setCaptureSilencedErrors',
            null,
            null,
        ];

        yield [
            'max_request_body_size',
            'small',
            'getMaxRequestBodySize',
            'setMaxRequestBodySize',
            null,
            null,
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

    public static function dsnOptionDataProvider(): \Generator
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

    public static function dsnOptionThrowsOnInvalidValueDataProvider(): \Generator
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

    public static function maxBreadcrumbsOptionIsValidatedCorrectlyDataProvider(): array
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

    public static function contextLinesOptionValidatesInputValueDataProvider(): \Generator
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

    public function testErrorTypesOptionIsNotDynamiclyReadFromErrorReportingLevelWhenSet(): void
    {
        $errorReportingBeforeTest = error_reporting(\E_ERROR);
        $errorTypesOptionValue = \E_NOTICE;

        $options = new Options(['error_types' => $errorTypesOptionValue]);

        $this->assertSame($errorTypesOptionValue, $options->getErrorTypes());

        error_reporting($errorReportingBeforeTest);

        $this->assertSame($errorTypesOptionValue, $options->getErrorTypes());
    }

    /**
     * @dataProvider enableTracingDataProvider
     */
    public function testEnableTracing(?bool $enabledTracing, ?float $tracesSampleRate, $expectedResult): void
    {
        $options = new Options([
            'enable_tracing' => $enabledTracing,
            'traces_sample_rate' => $tracesSampleRate,
        ]);

        $this->assertSame($expectedResult, $options->isTracingEnabled());
    }

    public static function enableTracingDataProvider(): array
    {
        return [
            [null, null, false],
            [null, 1.0, true],
            [false, 1.0, false],
            [true, 1.0, true],
            [null, 0.0, true], // We use this as - it's configured but turned off
            [false, 0.0, false],
            [true, 0.0, true], // We use this as - it's configured but turned off
            [true, null, true],
        ];
    }
}
