<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\KeyValueCollectionBehavior;
use Sentry\Tests\StubLogger;

final class DataCollectionOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new DataCollectionOptions();
        $collectionDefault = KeyValueCollectionBehavior::denyList();

        $this->assertTrue($options->shouldCollectUserInfo());
        $this->assertEquals($collectionDefault, $options->getCookies());
        $this->assertEquals(['request' => $collectionDefault, 'response' => $collectionDefault], $options->getHttpHeaders());
        $this->assertSame([
            HttpMessageType::incomingRequest(),
            HttpMessageType::outgoingRequest(),
            HttpMessageType::incomingResponse(),
            HttpMessageType::outgoingResponse(),
        ], $options->getHttpBodies());
        $this->assertEquals($collectionDefault, $options->getUrlQueryParams());
        $this->assertSame(['inputs' => true, 'outputs' => true], $options->getGenAi());
        $this->assertTrue($options->shouldCollectDatabaseQueryData());
        $this->assertTrue($options->shouldCollectQueues());
        $this->assertEquals($collectionDefault, $options->getStackFrameVariables());
        $this->assertSame(5, $options->getFrameContextLines());
    }

    public function testSharedHttpHeadersConfigurationAppliesToBothDirections(): void
    {
        $options = new DataCollectionOptions([
            'http_headers' => ['mode' => 'allowList', 'terms' => ['x-request-id']],
        ]);
        $expected = KeyValueCollectionBehavior::allowList(['x-request-id']);

        $this->assertEquals(['request' => $expected, 'response' => $expected], $options->getHttpHeaders());
    }

    public function testCookieSetterReplacesTheWholeCollection(): void
    {
        $options = new DataCollectionOptions([
            'cookies' => ['mode' => 'allowList', 'terms' => ['first']],
        ]);

        $result = $options->setCookies(['terms' => ['second']]);

        $this->assertSame($options, $result);
        $this->assertEquals(KeyValueCollectionBehavior::denyList(['second']), $options->getCookies());
    }

    public function testHttpHeaderSetterOnlyReplacesTheGivenDirection(): void
    {
        $options = new DataCollectionOptions([
            'http_headers' => ['mode' => 'allowList', 'terms' => ['x-request-id']],
        ]);
        $response = $options->getHttpHeaders()['response'];

        $options->setHttpHeaders(['request' => ['mode' => 'off']]);

        $this->assertTrue($options->getHttpHeaders()['request']->isOff());
        $this->assertSame($response, $options->getHttpHeaders()['response']);
    }

    public function testKeyValueCollectionsAreOnlyCreatedWhenTheirValueChanges(): void
    {
        $options = new DataCollectionOptions();
        $cookies = $options->getCookies();
        $requestHeaders = $options->getHttpHeaders()['request'];

        $this->assertSame($cookies, $options->getCookies());
        $this->assertSame($requestHeaders, $options->getHttpHeaders()['request']);

        $options->setUserInfo(false);

        $this->assertSame($cookies, $options->getCookies());
        $this->assertSame($requestHeaders, $options->getHttpHeaders()['request']);

        $options->setCookies(['mode' => 'off']);

        $this->assertNotSame($cookies, $options->getCookies());
        $this->assertTrue($options->getCookies()->isOff());
    }

    public function testInvalidValuesAreLogged(): void
    {
        StubLogger::$logs = [];

        $options = new DataCollectionOptions(['cookies' => ['mode' => 'allowlist']], StubLogger::getInstance());
        $options->setUrlQueryParams(['mode' => 'denylist']);

        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Invalid value for option "cookies". The value has been ignored.',
                'context' => [],
            ],
            [
                'level' => 'debug',
                'message' => 'Invalid value for option "url_query_params". The value has been ignored.',
                'context' => [],
            ],
        ], StubLogger::$logs);
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
        $this->assertEquals(KeyValueCollectionBehavior::off(), $options->getCookies());
    }

    public function testHttpHeaderSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setHttpHeaders(['request' => ['mode' => 'off']]));
        $this->assertTrue($options->getHttpHeaders()['request']->isOff());
        $this->assertSame('denyList', $options->getHttpHeaders()['response']->getMode());
    }

    public function testHttpBodySetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setHttpBodies([]));
        $this->assertSame([], $options->getHttpBodies());
    }

    /**
     * @dataProvider httpBodyCollectionProvider
     */
    public function testHttpBodyCollection(HttpMessageType $messageType, bool $selected): void
    {
        $this->assertTrue((new DataCollectionOptions())->shouldCollectHttpBody($messageType));

        $options = new DataCollectionOptions(['http_bodies' => ['incomingRequest', 'outgoingResponse']]);

        $this->assertSame([HttpMessageType::incomingRequest(), HttpMessageType::outgoingResponse()], $options->getHttpBodies());
        $this->assertSame($selected, $options->shouldCollectHttpBody($messageType));

        $options->setHttpBodies(['outgoingRequest', 'incomingResponse']);

        $this->assertSame([HttpMessageType::outgoingRequest(), HttpMessageType::incomingResponse()], $options->getHttpBodies());
        $this->assertSame(!$selected, $options->shouldCollectHttpBody($messageType));

        $options->setHttpBodies([]);

        $this->assertFalse($options->shouldCollectHttpBody($messageType));
    }

    public function testHttpMessageTypesCanBePassedToConstructorAndSetter(): void
    {
        $messageTypes = [HttpMessageType::incomingRequest(), HttpMessageType::outgoingResponse()];
        $options = new DataCollectionOptions(['http_bodies' => $messageTypes]);

        $this->assertSame($messageTypes, $options->getHttpBodies());
        $this->assertTrue($options->shouldCollectHttpBody(HttpMessageType::incomingRequest()));
        $this->assertFalse($options->shouldCollectHttpBody(HttpMessageType::incomingResponse()));

        $options->setHttpBodies($options->getHttpBodies());

        $this->assertSame($messageTypes, $options->getHttpBodies());
        $this->assertTrue($options->shouldCollectHttpBody(HttpMessageType::outgoingResponse()));

        $options->setHttpBodies([HttpMessageType::outgoingRequest()]);

        $this->assertSame([HttpMessageType::outgoingRequest()], $options->getHttpBodies());
        $this->assertTrue($options->shouldCollectHttpBody(HttpMessageType::outgoingRequest()));
        $this->assertFalse($options->shouldCollectHttpBody(HttpMessageType::incomingRequest()));
    }

    public function httpBodyCollectionProvider(): \Generator
    {
        yield 'incoming request' => [HttpMessageType::incomingRequest(), true];
        yield 'outgoing request' => [HttpMessageType::outgoingRequest(), false];
        yield 'incoming response' => [HttpMessageType::incomingResponse(), false];
        yield 'outgoing response' => [HttpMessageType::outgoingResponse(), true];
    }

    public function testUrlQueryParameterSetter(): void
    {
        $options = new DataCollectionOptions();

        $this->assertSame($options, $options->setUrlQueryParams(['mode' => 'allowList', 'terms' => ['page']]));
        $this->assertEquals(KeyValueCollectionBehavior::allowList(['page']), $options->getUrlQueryParams());
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
        $this->assertTrue($options->getStackFrameVariables()->isOff());
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

        $this->assertSame([
            HttpMessageType::incomingRequest(),
            HttpMessageType::outgoingRequest(),
            HttpMessageType::incomingResponse(),
            HttpMessageType::outgoingResponse(),
        ], $options->getHttpBodies());
    }

    public function testStackFrameVariablesAcceptKeyValueBehavior(): void
    {
        $options = new DataCollectionOptions([
            'stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['request_id']],
        ]);

        $this->assertEquals(KeyValueCollectionBehavior::allowList(['request_id']), $options->getStackFrameVariables());
    }

    public function testStackFrameVariableSetterReplacesTheWholeCollection(): void
    {
        $options = new DataCollectionOptions([
            'stack_frame_variables' => ['mode' => 'allowList', 'terms' => ['request_id']],
        ]);

        $options->setStackFrameVariables(['terms' => ['trace_id']]);

        $this->assertEquals(KeyValueCollectionBehavior::denyList(['trace_id']), $options->getStackFrameVariables());
    }

    /**
     * @dataProvider stackFrameVariableBooleanProvider
     */
    public function testStackFrameVariablesAcceptBooleanShorthand(bool $value, KeyValueCollectionBehavior $expected): void
    {
        $options = new DataCollectionOptions();

        $options->setStackFrameVariables($value);

        $this->assertEquals($expected, $options->getStackFrameVariables());
        $this->assertSame(!$value, $options->getStackFrameVariables()->isOff());
    }

    public function stackFrameVariableBooleanProvider(): \Generator
    {
        yield 'enabled' => [true, KeyValueCollectionBehavior::denyList()];
        yield 'disabled' => [false, KeyValueCollectionBehavior::off()];
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

        $this->assertEquals($expected, $options->{$getter}());
    }

    public function invalidConstructorValueProvider(): \Generator
    {
        yield 'cookies' => [['cookies' => ['mode' => 'invalid', 'terms' => [42]]], 'getCookies', KeyValueCollectionBehavior::denyList()];
        yield 'cookies with an invalid mode keep none of the terms' => [['cookies' => ['mode' => 'allowlist', 'terms' => ['theme']]], 'getCookies', KeyValueCollectionBehavior::denyList()];
        yield 'cookies with an unknown key' => [['cookies' => ['mode' => 'allowList', 'term' => ['theme']]], 'getCookies', KeyValueCollectionBehavior::denyList()];
        yield 'cookies with terms that are not a list of strings' => [['cookies' => ['terms' => 'theme']], 'getCookies', KeyValueCollectionBehavior::denyList()];
        yield 'cookies with a null mode' => [['cookies' => ['mode' => null, 'terms' => ['theme']]], 'getCookies', KeyValueCollectionBehavior::denyList()];
        yield 'HTTP request headers' => [['http_headers' => ['request' => ['mode' => 'invalid']]], 'getHttpHeaders', ['request' => KeyValueCollectionBehavior::denyList(), 'response' => KeyValueCollectionBehavior::denyList()]];
        yield 'URL query parameters' => [['url_query_params' => 'off'], 'getUrlQueryParams', KeyValueCollectionBehavior::denyList()];
        yield 'HTTP bodies' => [['http_bodies' => ['invalid']], 'getHttpBodies', [HttpMessageType::incomingRequest(), HttpMessageType::outgoingRequest(), HttpMessageType::incomingResponse(), HttpMessageType::outgoingResponse()]];
        yield 'GenAI' => [['gen_ai' => ['inputs' => 'invalid']], 'getGenAi', ['inputs' => true, 'outputs' => true]];
        yield 'database query data' => [['database_query_data' => 'invalid'], 'shouldCollectDatabaseQueryData', true];
        yield 'queues' => [['queues' => 'invalid'], 'shouldCollectQueues', true];
        yield 'stack frame variables' => [['stack_frame_variables' => ['mode' => 'invalid']], 'getStackFrameVariables', KeyValueCollectionBehavior::denyList()];
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
        $this->assertEquals($expected, $options->{$getter}());
    }

    public function invalidSetterValueProvider(): \Generator
    {
        yield 'cookies' => ['setCookies', 'getCookies', ['mode' => 'allowList'], ['mode' => 'invalid'], KeyValueCollectionBehavior::allowList()];
        yield 'HTTP bodies' => ['setHttpBodies', 'getHttpBodies', ['incomingRequest'], ['invalid'], [HttpMessageType::incomingRequest()]];
        yield 'stack frame variables' => ['setStackFrameVariables', 'getStackFrameVariables', ['mode' => 'allowList'], ['terms' => [42]], KeyValueCollectionBehavior::allowList()];
        yield 'frame context lines' => ['setFrameContextLines', 'getFrameContextLines', 2, -1, 2];
    }
}
