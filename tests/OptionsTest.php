<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sentry\ClientBuilder;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\Dsn;
use Sentry\Event;
use Sentry\HttpClient\HttpClient;
use Sentry\Options;
use Sentry\OptionsResolver;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Transport\HttpTransport;

final class OptionsTest extends TestCase
{
    /**
     * @var int
     */
    private $errorReportingOnSetUp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorReportingOnSetUp = error_reporting();
        StubLogger::$logs = [];
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
     * @dataProvider optionsWithSettersDataProvider
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
            'org_id',
            1,
            'getOrgId',
            'setOrgId',
        ];

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
            'enable_logs',
            true,
            'getEnableLogs',
            'setEnableLogs',
        ];

        yield [
            'enable_metrics',
            false,
            'getEnableMetrics',
            'setEnableMetrics',
        ];

        yield [
            'log_flush_threshold',
            10,
            'getLogFlushThreshold',
            'setLogFlushThreshold',
        ];

        yield [
            'log_flush_threshold',
            null,
            'getLogFlushThreshold',
            'setLogFlushThreshold',
        ];

        yield [
            'metric_flush_threshold',
            10,
            'getMetricFlushThreshold',
            'setMetricFlushThreshold',
        ];

        yield [
            'metric_flush_threshold',
            null,
            'getMetricFlushThreshold',
            'setMetricFlushThreshold',
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
            'profiles_sampler',
            static function (): void {},
            'getProfilesSampler',
            'setProfilesSampler',
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
            'dsn',
            Dsn::createFromString('http://public:secret@example.com/sentry/1'),
            'getDsn',
            null,
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
            'before_send_log',
            static function (): void {},
            'getBeforeSendLogCallback',
            'setBeforeSendLogCallback',
        ];

        yield [
            'before_send_metrics',
            static function (): void {},
            'getBeforeSendMetricsCallback',
            'setBeforeSendMetricsCallback',
        ];

        yield [
            'before_send_metric',
            static function (): void {},
            'getBeforeSendMetricCallback',
            'setBeforeSendMetricCallback',
        ];

        yield [
            'trace_propagation_targets',
            ['www.example.com'],
            'getTracePropagationTargets',
            'setTracePropagationTargets',
        ];

        yield [
            'strict_trace_continuation',
            true,
            'isStrictTraceContinuationEnabled',
            'enableStrictTraceContinuation',
        ];

        yield [
            'strict_trace_propagation',
            true,
            'isStrictTracePropagationEnabled',
            'enableStrictTracePropagation',
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
            'option' => 'data_collection',
            'value' => (new DataCollectionOptions())->setUserInfo(false),
            'getter' => 'getDataCollection',
            'setter' => null,
        ];

        yield [
            'default_integrations',
            false,
            'hasDefaultIntegrations',
            'setDefaultIntegrations',
        ];

        yield [
            'integrations',
            static function (array $integrations): array {
                return $integrations;
            },
            'getIntegrations',
            'setIntegrations',
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
            'http_enable_curl_share_handle',
            false,
            'isShareHandleEnabled',
            'setEnableShareHandle',
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

        yield [
            'class_serializers',
            [\stdClass::class => static function (\stdClass $value): array {
                return ['value' => $value];
            }],
            'getClassSerializers',
            'setClassSerializers',
        ];
    }

    public static function optionsWithSettersDataProvider(): \Generator
    {
        foreach (self::optionsDataProvider() as $testCase) {
            if (($testCase['setter'] ?? $testCase[3] ?? null) !== null) {
                yield $testCase;
            }
        }
    }

    public function testAllOptionsAreCoveredByOptionsDataProvider(): void
    {
        $configuredOptions = array_keys(self::getResolvedOptions(new Options()));
        $testedOptions = [];

        foreach (self::optionsDataProvider() as $testCase) {
            $testedOptions[] = $testCase['option'] ?? $testCase[0];
        }

        $testedOptions = array_values(array_unique($testedOptions));

        sort($configuredOptions);
        sort($testedOptions);

        $this->assertSame($configuredOptions, $testedOptions);
    }

    /**
     * @backupGlobals enabled
     */
    public function testDefaultOptionValues(): void
    {
        unset(
            $_SERVER['SENTRY_DSN'],
            $_SERVER['SENTRY_ENVIRONMENT'],
            $_SERVER['SENTRY_RELEASE'],
            $_SERVER['SENTRY_SPOTLIGHT'],
            $_SERVER['AWS_LAMBDA_FUNCTION_VERSION']
        );

        $prefixes = array_map(static function (string $prefix): string {
            $absolutePath = @realpath($prefix);

            return $absolutePath === false ? $prefix : $absolutePath;
        }, array_filter(explode(\PATH_SEPARATOR, get_include_path() ?: '')));

        $actual = self::getResolvedOptions(new Options());
        $callbackOptions = [
            'before_send',
            'before_send_transaction',
            'before_send_check_in',
            'before_send_log',
            'before_send_metrics',
            'before_send_metric',
            'before_breadcrumb',
        ];

        foreach ($callbackOptions as $callbackOption) {
            $this->assertInstanceOf(\Closure::class, $actual[$callbackOption]);
            $actual[$callbackOption] = \Closure::class;
        }

        $expected = [
            'integrations' => [],
            'default_integrations' => true,
            'prefixes' => $prefixes,
            'sample_rate' => 1,
            'enable_tracing' => null,
            'enable_logs' => false,
            'log_flush_threshold' => null,
            'enable_metrics' => true,
            'metric_flush_threshold' => null,
            'traces_sample_rate' => null,
            'traces_sampler' => null,
            'profiles_sample_rate' => null,
            'profiles_sampler' => null,
            'attach_stacktrace' => false,
            'attach_metric_code_locations' => false,
            'context_lines' => 5,
            'environment' => null,
            'logger' => null,
            'spotlight' => false,
            'spotlight_url' => 'http://localhost:8969',
            'release' => null,
            'dsn' => null,
            'org_id' => null,
            'server_name' => gethostname(),
            'ignore_exceptions' => [],
            'ignore_transactions' => [],
            'before_send' => \Closure::class,
            'before_send_transaction' => \Closure::class,
            'before_send_check_in' => \Closure::class,
            'before_send_log' => \Closure::class,
            'before_send_metrics' => \Closure::class,
            'before_send_metric' => \Closure::class,
            'trace_propagation_targets' => null,
            'strict_trace_continuation' => false,
            'strict_trace_propagation' => false,
            'tags' => [],
            'error_types' => null,
            'max_breadcrumbs' => Options::DEFAULT_MAX_BREADCRUMBS,
            'before_breadcrumb' => \Closure::class,
            'in_app_exclude' => [],
            'in_app_include' => [],
            'send_default_pii' => false,
            'data_collection' => null,
            'max_value_length' => 1024,
            'transport' => null,
            'http_client' => null,
            'http_proxy' => null,
            'http_proxy_authentication' => null,
            'http_connect_timeout' => Options::DEFAULT_HTTP_CONNECT_TIMEOUT,
            'http_timeout' => Options::DEFAULT_HTTP_TIMEOUT,
            'http_ssl_verify_peer' => true,
            'http_ssl_native_ca' => false,
            'http_compression' => true,
            'http_enable_curl_share_handle' => true,
            'capture_silenced_errors' => false,
            'max_request_body_size' => 'medium',
            'class_serializers' => [],
        ];

        ksort($actual);
        ksort($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @backupGlobals enabled
     */
    public function testAllDefaultValuesPassValidation(): void
    {
        unset(
            $_SERVER['SENTRY_DSN'],
            $_SERVER['SENTRY_ENVIRONMENT'],
            $_SERVER['SENTRY_RELEASE'],
            $_SERVER['SENTRY_SPOTLIGHT'],
            $_SERVER['AWS_LAMBDA_FUNCTION_VERSION']
        );

        $resolver = new RecordingOptionsResolver();
        self::configureOptions(new Options(), $resolver);
        $logger = StubLogger::getInstance();

        $resolver->resolve($resolver->getConfiguredDefaults(), $logger);

        $this->assertSame([], StubLogger::$logs);
    }

    public function testDataCollectionOptionNormalizesNestedArray(): void
    {
        $dataCollection = (new Options([
            'data_collection' => [
                'user_info' => false,
                'http_headers' => [
                    'request' => ['mode' => 'off'],
                ],
                'url_query_params' => ['terms' => ['private']],
                'gen_ai' => ['outputs' => false],
                'database_query_data' => false,
                'queues' => false,
                'stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['request_id']],
            ],
        ]))->getDataCollection();

        $this->assertInstanceOf(DataCollectionOptions::class, $dataCollection);
        $this->assertFalse($dataCollection->shouldCollectUserInfo());
        $this->assertSame('off', $dataCollection->getHttpHeaders()['request']['mode']);
        $this->assertSame('denyList', $dataCollection->getHttpHeaders()['response']['mode']);
        $this->assertSame(['mode' => 'denyList', 'terms' => ['private']], $dataCollection->getUrlQueryParams());
        $this->assertSame(['inputs' => true, 'outputs' => false], $dataCollection->getGenAi());
        $this->assertFalse($dataCollection->shouldCollectDatabaseQueryData());
        $this->assertFalse($dataCollection->shouldCollectQueues());
        $this->assertSame([
            'mode' => 'allowList',
            'terms' => ['request_id'],
        ], $dataCollection->getStackFrameVariables());
    }

    public function testDataCollectionOptionPreservesObjectIdentityAndCanBeUpdatedThroughGetter(): void
    {
        $dataCollection = (new DataCollectionOptions())->setUserInfo(false);
        $options = new Options(['data_collection' => $dataCollection]);

        $this->assertSame($dataCollection, $options->getDataCollection());
        $resolvedDataCollection = $options->getDataCollection();
        $this->assertNotNull($resolvedDataCollection);
        $resolvedDataCollection->setFrameContextLines(0);
        $this->assertSame(0, $dataCollection->getFrameContextLines());
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
     * @dataProvider dsnOptionInvalidValueDataProvider
     */
    public function testDsnOptionInvalidValueFallsBackToDefault($value): void
    {
        $options = new Options(['dsn' => $value]);

        $this->assertNull($options->getDsn());
    }

    public static function dsnOptionInvalidValueDataProvider(): \Generator
    {
        yield '"true" is not a valid DSN' => [
            true,
        ];

        yield '"foo" is not a valid DSN' => [
            'foo',
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
    public function testIncludedAppPathsOverrideExcludedAppPaths(string $value, string $expected): void
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
    public function testMaxBreadcrumbsOptionIsValidatedCorrectly($value, int $expectedValue): void
    {
        $options = new Options(['max_breadcrumbs' => $value]);

        $this->assertSame($expectedValue, $options->getMaxBreadcrumbs());
    }

    public static function maxBreadcrumbsOptionIsValidatedCorrectlyDataProvider(): array
    {
        return [
            [-1, Options::DEFAULT_MAX_BREADCRUMBS],
            [0, 0],
            [1, 1],
            [Options::DEFAULT_MAX_BREADCRUMBS, Options::DEFAULT_MAX_BREADCRUMBS],
            [Options::DEFAULT_MAX_BREADCRUMBS + 1, Options::DEFAULT_MAX_BREADCRUMBS + 1],
            ['string', Options::DEFAULT_MAX_BREADCRUMBS],
            ['1', Options::DEFAULT_MAX_BREADCRUMBS],
        ];
    }

    /**
     * @dataProvider contextLinesOptionValidatesInputValueDataProvider
     */
    public function testContextLinesOptionValidatesInputValue(?int $value, ?int $expectedValue): void
    {
        $options = new Options(['context_lines' => $value]);

        $this->assertSame($expectedValue, $options->getContextLines());
    }

    public static function contextLinesOptionValidatesInputValueDataProvider(): \Generator
    {
        yield [
            -1,
            5,
        ];

        yield [
            0,
            0,
        ];

        yield [
            1,
            1,
        ];

        yield [
            null,
            null,
        ];
    }

    /**
     * @dataProvider logFlushThresholdOptionIsValidatedCorrectlyDataProvider
     */
    public function testLogFlushThresholdOptionIsValidatedCorrectly($value, ?int $expectedValue): void
    {
        $options = new Options(['log_flush_threshold' => $value]);

        $this->assertSame($expectedValue, $options->getLogFlushThreshold());
    }

    public static function logFlushThresholdOptionIsValidatedCorrectlyDataProvider(): array
    {
        return [
            [-1, null],
            [0, null],
            [1, 1],
            [10, 10],
            [null, null],
            ['string', null],
            ['1', null],
        ];
    }

    /**
     * @dataProvider metricFlushThresholdOptionIsValidatedCorrectlyDataProvider
     */
    public function testMetricFlushThresholdOptionIsValidatedCorrectly($value, ?int $expectedValue): void
    {
        $options = new Options(['metric_flush_threshold' => $value]);

        $this->assertSame($expectedValue, $options->getMetricFlushThreshold());
    }

    public static function metricFlushThresholdOptionIsValidatedCorrectlyDataProvider(): array
    {
        return [
            [-1, null],
            [0, null],
            [1, 1],
            [10, 10],
            [null, null],
            ['string', null],
            ['1', null],
        ];
    }

    public function testTagsAreValidatedAndReplacedAsOneAssociativeArray(): void
    {
        $options = new Options([
            'tags' => [
                'environment' => 'production',
                'release' => '1.0',
            ],
        ]);

        $this->assertSame([
            'environment' => 'production',
            'release' => '1.0',
        ], $options->getTags());

        $options->setTags(['environment' => 'staging']);

        $this->assertSame([
            'environment' => 'staging',
        ], $options->getTags());

        $options->updateOptions(['tags' => ['invalid' => 42]]);

        $this->assertSame([
            'environment' => 'staging',
        ], $options->getTags());
    }

    public function testUpdateOptionsLogsInvalidValuesAndKeepsCurrentValue(): void
    {
        $logger = StubLogger::getInstance();

        $options = new Options([
            'logger' => $logger,
            'sample_rate' => 0.5,
            'environment' => 'custom',
        ]);

        $options->updateOptions(['sample_rate' => 'invalid']);

        $this->assertSame(0.5, $options->getSampleRate());
        $this->assertSame('custom', $options->getEnvironment());
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Invalid value for option "sample_rate". The value has been ignored.',
            'context' => [],
        ]], StubLogger::$logs);
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
    public function testInvalidDsnOptionFromEnvironmentVariableFallsBackToNull(): void
    {
        $_SERVER['SENTRY_DSN'] = 'invalid';

        $options = new Options(['logger' => StubLogger::getInstance()]);

        $this->assertNull($options->getDsn());
        $this->assertSame([], StubLogger::$logs);
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

    /**
     * @backupGlobals enabled
     *
     * @dataProvider invalidEnvironmentBackedOptionDataProvider
     *
     * @param mixed $expected
     */
    public function testInvalidEnvironmentBackedOptionFallsBackToSafeDefault(
        string $serverVariable,
        string $getter,
        $expected
    ): void {
        $_SERVER[$serverVariable] = [];

        $options = new Options(['logger' => StubLogger::getInstance()]);

        $this->assertSame($expected, $options->$getter());
        $this->assertSame([], StubLogger::$logs);
    }

    public static function invalidEnvironmentBackedOptionDataProvider(): \Generator
    {
        yield 'environment' => ['SENTRY_ENVIRONMENT', 'getEnvironment', null];
        yield 'Sentry release' => ['SENTRY_RELEASE', 'getRelease', null];
        yield 'Lambda release' => ['AWS_LAMBDA_FUNCTION_VERSION', 'getRelease', null];
        yield 'Spotlight' => ['SENTRY_SPOTLIGHT', 'isSpotlightEnabled', false];
        yield 'DSN' => ['SENTRY_DSN', 'getDsn', null];
    }

    /**
     * @backupGlobals enabled
     */
    public function testInvalidEnvironmentBackedOptionsAreSafeDuringClientConstruction(): void
    {
        $_SERVER['SENTRY_DSN'] = [];
        $_SERVER['SENTRY_ENVIRONMENT'] = [];
        $_SERVER['SENTRY_RELEASE'] = [];
        $_SERVER['SENTRY_SPOTLIGHT'] = [];

        $client = ClientBuilder::create(['logger' => StubLogger::getInstance()])->getClient();
        $options = $client->getOptions();

        $this->assertNull($options->getDsn());
        $this->assertNull($options->getEnvironment());
        $this->assertNull($options->getRelease());
        $this->assertFalse($options->isSpotlightEnabled());

        $this->assertNotNull($client->captureMessage('Invalid environment-backed options'));

        $lastLog = StubLogger::$logs[
            \count(StubLogger::$logs) - 1
        ];

        $this->assertSame('info', $lastLog['level']);
        $this->assertStringContainsString('because no DSN is set.', $lastLog['message']);
        $this->assertArrayHasKey('event', $lastLog['context']);
        $this->assertInstanceOf(Event::class, $lastLog['context']['event']);
        $this->assertSame(Event::DEFAULT_ENVIRONMENT, $lastLog['context']['event']->getEnvironment());
        $this->assertNull($lastLog['context']['event']->getRelease());
    }

    /**
     * @backupGlobals enabled
     */
    public function testExplicitOptionsTakePrecedenceOverEnvironmentBackedOptions(): void
    {
        $_SERVER['SENTRY_DSN'] = [];
        $_SERVER['SENTRY_ENVIRONMENT'] = [];
        $_SERVER['SENTRY_RELEASE'] = [];
        $_SERVER['SENTRY_SPOTLIGHT'] = [];

        $dsn = Dsn::createFromString('http://public@example.com/1');
        $options = new Options([
            'dsn' => $dsn,
            'environment' => 'production',
            'release' => '1.0.0',
            'spotlight' => true,
            'logger' => StubLogger::getInstance(),
        ]);

        $this->assertSame($dsn, $options->getDsn());
        $this->assertSame('production', $options->getEnvironment());
        $this->assertSame('1.0.0', $options->getRelease());
        $this->assertTrue($options->isSpotlightEnabled());
        $this->assertSame([], StubLogger::$logs);
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

    /**
     * @dataProvider spotlightUrlNormalizationDataProvider
     */
    public function testSpotlightUrlNormalization(array $data, string $expected): void
    {
        $options = new Options($data);
        $this->assertSame($expected, $options->getSpotlightUrl());
    }

    public static function spotlightUrlNormalizationDataProvider(): \Generator
    {
        yield [['spotlight_url' => 'http://localhost:8969'], 'http://localhost:8969'];
        yield [['spotlight_url' => 'http://localhost:8969/stream'], 'http://localhost:8969'];
        yield [['spotlight_url' => 'http://localhost:8969/foo'], 'http://localhost:8969/foo'];
        yield [['spotlight_url' => 'http://localhost:8969/foo/stream'], 'http://localhost:8969/foo'];
        yield [['spotlight_url' => 'http://localhost:8969/stream/foo'], 'http://localhost:8969/stream/foo'];
        yield [['spotlight' => 'http://localhost:8969'], 'http://localhost:8969'];
        yield [['spotlight' => 'http://localhost:8969/stream'], 'http://localhost:8969'];
        yield [['spotlight' => 'http://localhost:8969/foo'], 'http://localhost:8969/foo'];
        yield [['spotlight' => 'http://localhost:8969/foo/stream'], 'http://localhost:8969/foo'];
        yield [['spotlight' => 'http://localhost:8969/stream/foo'], 'http://localhost:8969/stream/foo'];
    }

    /**
     * @dataProvider setSpotlightUrlNormalizationDataProvider
     */
    public function testSetSpotlightUrlNormalization(string $url, string $expected): void
    {
        $options = new Options();
        $options->setSpotlightUrl($url);
        $this->assertSame($expected, $options->getSpotlightUrl());
    }

    /**
     * @dataProvider setSpotlightUrlNormalizationDataProvider
     */
    public function testEnableSpotlightNormalization(string $url, string $expected): void
    {
        $options = new Options();
        $options->enableSpotlight($url);
        $this->assertSame($expected, $options->getSpotlightUrl());
    }

    public static function setSpotlightUrlNormalizationDataProvider(): \Generator
    {
        yield ['http://localhost:8969', 'http://localhost:8969'];
        yield ['http://localhost:8969/stream', 'http://localhost:8969'];
        yield ['http://localhost:8969/foo', 'http://localhost:8969/foo'];
        yield ['http://localhost:8969/foo/stream', 'http://localhost:8969/foo'];
        yield ['http://localhost:8969/stream/foo', 'http://localhost:8969/stream/foo'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getResolvedOptions(Options $options): array
    {
        /** @var \Closure(Options): array<string, mixed> $getOptions */
        $getOptions = \Closure::bind(static function (Options $options): array {
            return $options->options;
        }, null, Options::class);

        return $getOptions($options);
    }

    private static function configureOptions(Options $options, OptionsResolver $resolver): void
    {
        /** @var \Closure(Options, OptionsResolver): void $configureOptions */
        $configureOptions = \Closure::bind(static function (Options $options, OptionsResolver $resolver): void {
            $options->configureOptions($resolver);
        }, null, Options::class);

        $configureOptions($options, $resolver);
    }
}

final class RecordingOptionsResolver extends OptionsResolver
{
    /**
     * @var array<string, mixed>
     */
    private $configuredDefaults = [];

    public function setDefaults(array $defaults): void
    {
        $this->configuredDefaults = $defaults;

        parent::setDefaults($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguredDefaults(): array
    {
        return $this->configuredDefaults;
    }
}
