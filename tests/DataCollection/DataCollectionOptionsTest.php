<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;

final class DataCollectionOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new DataCollectionOptions();
        $collectionDefault = ['mode' => 'denyList', 'terms' => []];

        $this->assertTrue($options->shouldCollectUserInfo());
        $this->assertSame($collectionDefault, $options->getCookies());
        $this->assertSame(['request' => $collectionDefault, 'response' => $collectionDefault], $options->getHttpHeaders());
        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $options->getHttpBodies());
        $this->assertSame($collectionDefault, $options->getUrlQueryParams());
        $this->assertSame(['inputs' => true, 'outputs' => true], $options->getGenAi());
        $this->assertTrue($options->shouldCollectDatabaseQueryData());
        $this->assertTrue($options->shouldCollectQueues());
        $this->assertSame($collectionDefault, $options->getStackFrameVariables());
        $this->assertTrue($options->shouldCollectStackFrameVariables());
        $this->assertSame(5, $options->getFrameContextLines());
    }

    public function testSharedHttpHeadersConfigurationAppliesToBothDirections(): void
    {
        $options = new DataCollectionOptions([
            'http_headers' => ['mode' => 'allowList', 'terms' => ['x-request-id']],
        ]);
        $expected = ['mode' => 'allowList', 'terms' => ['x-request-id']];

        $this->assertSame(['request' => $expected, 'response' => $expected], $options->getHttpHeaders());
    }

    public function testCookieSetterPreservesUnchangedNestedValues(): void
    {
        $options = new DataCollectionOptions([
            'cookies' => ['mode' => 'allowList', 'terms' => ['first']],
        ]);

        $result = $options->setCookies(['terms' => ['second']]);

        $this->assertSame($options, $result);
        $this->assertSame(['mode' => 'allowList', 'terms' => ['second']], $options->getCookies());
    }

    public function testUserInfoSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setUserInfo(false));
        $this->assertFalse($options->shouldCollectUserInfo());
    }

    public function testCookieSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setCookies(['mode' => 'off']));
        $this->assertSame(['mode' => 'off', 'terms' => []], $options->getCookies());
    }

    public function testHttpHeaderSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setHttpHeaders(['request' => ['mode' => 'off']]));
        $this->assertSame('off', $options->getHttpHeaders()['request']['mode']);
        $this->assertSame('denyList', $options->getHttpHeaders()['response']['mode']);
    }

    public function testHttpBodySetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setHttpBodies([]));
        $this->assertSame([], $options->getHttpBodies());
    }

    public function testUrlQueryParameterSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setUrlQueryParams(['mode' => 'allowList', 'terms' => ['page']]));
        $this->assertSame(['mode' => 'allowList', 'terms' => ['page']], $options->getUrlQueryParams());
    }

    public function testGenAiSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setGenAi(['inputs' => false]));
        $this->assertSame(['inputs' => false, 'outputs' => true], $options->getGenAi());
    }

    public function testDatabaseQueryDataSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setDatabaseQueryData(false));
        $this->assertFalse($options->shouldCollectDatabaseQueryData());
    }

    public function testQueueSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setQueues(false));
        $this->assertFalse($options->shouldCollectQueues());
    }

    public function testStackFrameVariableSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setStackFrameVariables(false));
        $this->assertFalse($options->shouldCollectStackFrameVariables());
    }

    public function testFrameContextLineSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setFrameContextLines(0));
        $this->assertSame(0, $options->getFrameContextLines());
    }

    public function testNullHttpBodiesUsesDefault(): void
    {
        $options = new DataCollectionOptions(['http_bodies' => null]);

        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $options->getHttpBodies());
    }

    public function testStackFrameVariablesAcceptKeyValueBehavior(): void
    {
        $options = new DataCollectionOptions([
            'stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['request_id']],
        ]);

        $this->assertSame(['mode' => 'allowList', 'terms' => ['request_id']], $options->getStackFrameVariables());
        $this->assertTrue($options->shouldCollectStackFrameVariables());
    }

    public function testStackFrameVariableSetterPreservesMode(): void
    {
        $options = new DataCollectionOptions([
            'stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['request_id']],
        ]);

        $options->setStackFrameVariables(['terms' => ['trace_id']]);

        $this->assertSame(['mode' => 'allowList', 'terms' => ['trace_id']], $options->getStackFrameVariables());
    }

    /**
     * @dataProvider stackFrameVariableBooleanProvider
     */
    public function testStackFrameVariablesAcceptBooleanShorthand(bool $value, array $expected): void
    {
        $options = new DataCollectionOptions();

        $options->setStackFrameVariables($value);

        $this->assertSame($expected, $options->getStackFrameVariables());
        $this->assertSame($value, $options->shouldCollectStackFrameVariables());
    }

    public function stackFrameVariableBooleanProvider(): \Generator
    {
        yield 'enabled' => [true, ['mode' => 'denyList', 'terms' => []]];
        yield 'disabled' => [false, ['mode' => 'off', 'terms' => []]];
    }

    /**
     * @dataProvider invalidConstructorValueProvider
     *
     * @param array<string, mixed> $configuration
     * @param mixed                $expected
     */
    public function testInvalidConstructorValuesUseDefaults(array $configuration, string $getter, $expected): void
    {
        $options = new DataCollectionOptions($configuration);

        $this->assertSame($expected, $options->{$getter}());
    }

    public function invalidConstructorValueProvider(): \Generator
    {
        yield 'cookies' => [['cookies' => ['mode' => 'invalid', 'terms' => [42]]], 'getCookies', ['mode' => 'denyList', 'terms' => []]];
        yield 'HTTP bodies' => [['http_bodies' => ['invalid']], 'getHttpBodies', DataCollectionOptions::HTTP_BODY_TYPES];
        yield 'GenAI' => [['gen_ai' => ['inputs' => 'invalid']], 'getGenAi', ['inputs' => true, 'outputs' => true]];
        yield 'database query data' => [['database_query_data' => 'invalid'], 'shouldCollectDatabaseQueryData', true];
        yield 'queues' => [['queues' => 'invalid'], 'shouldCollectQueues', true];
        yield 'stack frame variables' => [['stack_frame_variables' => ['mode' => 'invalid']], 'getStackFrameVariables', ['mode' => 'denyList', 'terms' => []]];
        yield 'frame context lines' => [['frame_context_lines' => -1], 'getFrameContextLines', 5];
    }

    /**
     * @dataProvider invalidSetterValueProvider
     *
     * @param mixed $valid
     * @param mixed $invalid
     * @param mixed $expected
     */
    public function testInvalidSetterValuesKeepCurrentValues(string $setter, string $getter, $valid, $invalid, $expected): void
    {
        $options = new DataCollectionOptions();
        $options->{$setter}($valid);

        $this->assertSame($options, $options->{$setter}($invalid));
        $this->assertSame($expected, $options->{$getter}());
    }

    public function invalidSetterValueProvider(): \Generator
    {
        yield 'cookies' => ['setCookies', 'getCookies', ['mode' => 'allowList'], ['mode' => 'invalid'], ['mode' => 'allowList', 'terms' => []]];
        yield 'HTTP bodies' => ['setHttpBodies', 'getHttpBodies', ['incomingRequest'], ['invalid'], ['incomingRequest']];
        yield 'stack frame variables' => ['setStackFrameVariables', 'getStackFrameVariables', ['mode' => 'allowList'], ['terms' => [42]], ['mode' => 'allowList', 'terms' => []]];
        yield 'frame context lines' => ['setFrameContextLines', 'getFrameContextLines', 2, -1, 2];
    }
}
