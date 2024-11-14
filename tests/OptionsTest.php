<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sentry\Dsn;
use Sentry\HttpClient\HttpClient;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Transport\HttpTransport;
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
        ?string $setterMethod
    ): void {
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
        ?string $setterMethod
    ): void {
        $options = new Options();

        if ($setterMethod !== null) {
            $options->$setterMethod($value);
        }

        $this->assertEquals($value, $options->$getterMethod());
    }

    public static function optionsDataProvider(): \Generator
    {
        yield [
            'prefixes',
            ['foo', 'bar'],
            'getPrefixes',
            'setPrefixes',
        ];

        yield [
            'sample_rate',
            0.5,
            'getSampleRate',
            'setSampleRate',
        ];

        yield [
            'traces_sample_rate',
            0.5,
            'getTracesSampleRate',
            'setTracesSampleRate',
        ];

        yield [
            'traces_sample_rate',
            null,
            'getTracesSampleRate',
            'setTracesSampleRate',
        ];

        yield [
            'traces_sampler',
            static function (): void {},
            'getTracesSampler',
            'setTracesSampler',
        ];

        yield [
            'enable_tracing',
            true,
            'getEnableTracing',
            'setEnableTracing',
        ];

        yield [
            'profiles_sample_rate',
            0.5,
            'getProfilesSampleRate',
            'setProfilesSampleRate',
        ];

        yield [
            'attach_stacktrace',
            false,
            'shouldAttachStacktrace',
            'setAttachStacktrace',
        ];

        yield [
            'attach_metric_code_locations',
            false,
            'shouldAttachMetricCodeLocations',
            'setAttachMetricCodeLocations',
        ];

        yield [
            'context_lines',
            3,
            'getContextLines',
            'setContextLines',
        ];

        yield [
            'environment',
            'foo',
            'getEnvironment',
            'setEnvironment',
        ];

        yield [
            'in_app_exclude',
            ['foo', 'bar'],
            'getInAppExcludedPaths',
            'setInAppExcludedPaths',
        ];

        yield [
            'in_app_include',
            ['foo', 'bar'],
            'getInAppIncludedPaths',
            'setInAppIncludedPaths',
        ];

        yield [
            'logger',
            new NullLogger(),
            'getLogger',
            'setLogger',
        ];

        yield [
            'spotlight',
            true,
            'isSpotlightEnabled',
            'enableSpotlight',
        ];

        yield [
            'spotlight_url',
            'http://google.com',
            'getSpotlightUrl',
            'setSpotlightUrl',
        ];

        yield [
            'release',
            'dev',
            'getRelease',
            'setRelease',
        ];

        yield [
            'server_name',
            'foo',
            'getServerName',
            'setServerName',
        ];

        yield [
            'tags',
            ['foo', 'bar'],
            'getTags',
            'setTags',
        ];

        yield [
            'error_types',
            0,
            'getErrorTypes',
            'setErrorTypes',
        ];

        yield [
            'max_breadcrumbs',
            50,
            'getMaxBreadcrumbs',
            'setMaxBreadcrumbs',
        ];

        yield [
            'ignore_exceptions',
            ['foo', 'bar'],
            'getIgnoreExceptions',
            'setIgnoreExceptions',
        ];

        yield [
            'ignore_transactions',
            ['foo', 'bar'],
            'getIgnoreTransactions',
            'setIgnoreTransactions',
        ];

        yield [
            'before_send',
            static function (): void {},
            'getBeforeSendCallback',
            'setBeforeSendCallback',
        ];

        yield [
            'before_send_transaction',
            static function (): void {},
            'getBeforeSendTransactionCallback',
            'setBeforeSendTransactionCallback',
        ];

        yield [
            'before_send_check_in',
            static function (): void {},
            'getBeforeSendCheckInCallback',
            'setBeforeSendCheckInCallback',
        ];

        yield [
            'before_send_metrics',
            static function (): void {},
            'getBeforeSendMetricsCallback',
            'setBeforeSendMetricsCallback',
        ];

        yield [
            'trace_propagation_targets',
            ['www.example.com'],
            'getTracePropagationTargets',
            'setTracePropagationTargets',
        ];

        yield [
            'before_breadcrumb',
            static function (): void {},
            'getBeforeBreadcrumbCallback',
            'setBeforeBreadcrumbCallback',
        ];

        yield [
            'send_default_pii',
            true,
            'shouldSendDefaultPii',
            'setSendDefaultPii',
        ];

        yield [
            'default_integrations',
            false,
            'hasDefaultIntegrations',
            'setDefaultIntegrations',
        ];

        yield [
            'max_value_length',
            50,
            'getMaxValueLength',
            'setMaxValueLength',
        ];

        yield [
            'transport',
            new HttpTransport(new Options(), new HttpClient('foo', 'bar'), new PayloadSerializer(new Options())),
            'getTransport',
            'setTransport',
        ];

        yield [
            'http_client',
            new HttpClient('foo', 'bar'),
            'getHttpClient',
            'setHttpClient',
        ];

        yield [
            'http_proxy',
            '127.0.0.1',
            'getHttpProxy',
            'setHttpProxy',
        ];

        yield [
            'http_proxy_authentication',
            'username:password',
            'getHttpProxyAuthentication',
            'setHttpProxyAuthentication',
        ];

        yield [
            'http_timeout',
            1,
            'getHttpTimeout',
            'setHttpTimeout',
        ];

        yield [
            'http_timeout',
            1.2,
            'getHttpTimeout',
            'setHttpTimeout',
        ];

        yield [
            'http_timeout',
            0.2,
            'getHttpTimeout',
            'setHttpTimeout',
        ];

        yield [
            'http_connect_timeout',
            1,
            'getHttpConnectTimeout',
            'setHttpConnectTimeout',
        ];

        yield [
            'http_connect_timeout',
            1.2,
            'getHttpConnectTimeout',
            'setHttpConnectTimeout',
        ];

        yield [
            'http_connect_timeout',
            0.2,
            'getHttpConnectTimeout',
            'setHttpConnectTimeout',
        ];

        yield [
            'http_ssl_verify_peer',
            false,
            'getHttpSslVerifyPeer',
            'setHttpSslVerifyPeer',
        ];

        yield [
            'http_ssl_native_ca',
            true,
            'getHttpSslNativeCa',
            'setHttpSslNativeCa',
        ];

        yield [
            'http_compression',
            false,
            'isHttpCompressionEnabled',
            'setEnableHttpCompression',
        ];

        yield [
            'capture_silenced_errors',
            true,
            'shouldCaptureSilencedErrors',
            'setCaptureSilencedErrors',
        ];

        yield [
            'max_request_body_size',
            'small',
            'getMaxRequestBodySize',
            'setMaxRequestBodySize',
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
        if ($expectedExceptionMessage !== null) {
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

    /**
     * @backupGlobals enabled
     */
    public function testReleaseOptionDefaultValueIsGotFromLambdaEnvironmentVariable(): void
    {
        $_SERVER['AWS_LAMBDA_FUNCTION_VERSION'] = '0.0.2';

        $this->assertSame('0.0.2', (new Options())->getRelease());
    }

    /**
     * @backupGlobals enabled
     */
    public function testReleaseOptionDefaultValueIsPreferredFromSentryEnvironmentVariable(): void
    {
        $_SERVER['AWS_LAMBDA_FUNCTION_VERSION'] = '0.0.3';
        $_SERVER['SENTRY_RELEASE'] = '0.0.4';

        $this->assertSame('0.0.4', (new Options())->getRelease());
    }

    /**
     * @backupGlobals enabled
     *
     * @dataProvider spotlightEnvironmentValueDataProvider
     */
    public function testSpotlightOptionDefaultValueIsControlledFromEnvironmentVariable(string $environmentVariableValue, bool $expectedSpotlightEnabled, string $expectedSpotlightUrl): void
    {
        $_SERVER['SENTRY_SPOTLIGHT'] = $environmentVariableValue;

        $options = new Options();

        $this->assertEquals($expectedSpotlightEnabled, $options->isSpotlightEnabled());
        $this->assertEquals($expectedSpotlightUrl, $options->getSpotlightUrl());
    }

    public static function spotlightEnvironmentValueDataProvider(): array
    {
        $defaultSpotlightUrl = 'http://localhost:8969';

        return [
            ['', false, $defaultSpotlightUrl],
            ['true', true, $defaultSpotlightUrl],
            ['1', true, $defaultSpotlightUrl],
            ['false', false, $defaultSpotlightUrl],
            ['0', false, $defaultSpotlightUrl],
            ['null', false, $defaultSpotlightUrl],
            ['http://localhost:1234', true, 'http://localhost:1234'],
            ['some invalid looking value', false, $defaultSpotlightUrl],
        ];
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
     *
     * @deprecated since version 4.7. To be removed in version 5.0
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
