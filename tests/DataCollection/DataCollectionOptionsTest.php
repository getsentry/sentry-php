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
        $this->assertSame([
            'request' => $collectionDefault,
            'response' => $collectionDefault,
        ], $options->getHttpHeaders());
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
            'http_headers' => [
                'mode' => 'allowList',
                'terms' => ['x-request-id'],
            ],
        ]);

        $expected = ['mode' => 'allowList', 'terms' => ['x-request-id']];
        $this->assertSame(['request' => $expected, 'response' => $expected], $options->getHttpHeaders());
    }

    public function testSetterPreservesUnchangedNestedValues(): void
    {
        $options = new DataCollectionOptions([
            'cookies' => ['mode' => 'allowList', 'terms' => ['first']],
        ]);

        $result = $options->setCookies(['terms' => ['second']]);

        $this->assertSame($options, $result);
        $this->assertSame(['mode' => 'allowList', 'terms' => ['second']], $options->getCookies());
    }

    public function testNullHttpBodiesUsesDefault(): void
    {
        $options = new DataCollectionOptions(['http_bodies' => null]);

        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $options->getHttpBodies());
    }

    public function testStackFrameVariablesSupportsBooleanAndKeyValueCollectionBehavior(): void
    {
        $options = new DataCollectionOptions([
            'stack_frame_variables' => [
                'mode' => 'allowList',
                'terms' => ['request_id'],
            ],
        ]);

        $this->assertSame([
            'mode' => 'allowList',
            'terms' => ['request_id'],
        ], $options->getStackFrameVariables());
        $this->assertTrue($options->shouldCollectStackFrameVariables());

        $options->setStackFrameVariables(['terms' => ['trace_id']]);
        $this->assertSame([
            'mode' => 'allowList',
            'terms' => ['trace_id'],
        ], $options->getStackFrameVariables());

        $options->setStackFrameVariables(false);
        $this->assertSame(['mode' => 'off', 'terms' => []], $options->getStackFrameVariables());
        $this->assertFalse($options->shouldCollectStackFrameVariables());

        $options->setStackFrameVariables(true);
        $this->assertSame(['mode' => 'denyList', 'terms' => []], $options->getStackFrameVariables());
        $this->assertTrue($options->shouldCollectStackFrameVariables());

        $options->setStackFrameVariables(['mode' => 'off']);
        $this->assertSame(['mode' => 'off', 'terms' => []], $options->getStackFrameVariables());
        $this->assertFalse($options->shouldCollectStackFrameVariables());
    }

    public function testInvalidValuesUseDefaultsAndSettersKeepCurrentValues(): void
    {
        $options = new DataCollectionOptions([
            'cookies' => ['mode' => 'invalid', 'terms' => [42]],
            'http_bodies' => ['invalid'],
            'gen_ai' => ['inputs' => 'invalid'],
            'database_query_data' => 'invalid',
            'queues' => 'invalid',
            'stack_frame_variables' => ['mode' => 'invalid'],
            'frame_context_lines' => -1,
        ]);

        $this->assertSame(['mode' => 'denyList', 'terms' => []], $options->getCookies());
        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $options->getHttpBodies());
        $this->assertSame(['inputs' => true, 'outputs' => true], $options->getGenAi());
        $this->assertTrue($options->shouldCollectDatabaseQueryData());
        $this->assertTrue($options->shouldCollectQueues());
        $this->assertSame(['mode' => 'denyList', 'terms' => []], $options->getStackFrameVariables());
        $this->assertSame(5, $options->getFrameContextLines());

        $options->setCookies(['mode' => 'allowList'])->setCookies(['mode' => 'invalid']);
        $options->setHttpBodies(['incomingRequest'])->setHttpBodies(['invalid']);
        $options->setStackFrameVariables(['mode' => 'allowList'])->setStackFrameVariables(['terms' => [42]]);
        $options->setFrameContextLines(2)->setFrameContextLines(-1);

        $this->assertSame('allowList', $options->getCookies()['mode']);
        $this->assertSame(['incomingRequest'], $options->getHttpBodies());
        $this->assertSame(['mode' => 'allowList', 'terms' => []], $options->getStackFrameVariables());
        $this->assertSame(2, $options->getFrameContextLines());
    }

    public function testArrayAccessReadsNestedOptions(): void
    {
        $options = new DataCollectionOptions([
            'http_headers' => [
                'request' => ['mode' => 'allowList'],
            ],
        ]);

        $this->assertTrue(isset($options['http_headers']));
        $this->assertFalse(isset($options['unknown']));
        $this->assertSame('allowList', $options['http_headers']['request']['mode']);
        $this->assertNull($options['unknown']);
        $this->assertNull($options[0]);
    }

    public function testArrayAccessWritesUseResolver(): void
    {
        $options = new DataCollectionOptions();

        $options['http_headers'] = [
            'request' => ['mode' => 'off'],
        ];
        $this->assertSame('off', $options['http_headers']['request']['mode']);
        $this->assertSame('denyList', $options['http_headers']['response']['mode']);

        $options['http_headers'] = ['request' => ['mode' => 'invalid']];
        $options['frame_context_lines'] = -1;
        $options['http_bodies'] = ['incomingRequest'];
        $options['http_bodies'] = null;
        $options['unknown'] = true;
        $options[] = true;

        $this->assertSame('off', $options['http_headers']['request']['mode']);
        $this->assertSame(5, $options['frame_context_lines']);
        $this->assertSame(['incomingRequest'], $options['http_bodies']);
        $this->assertNull($options['unknown']);
    }

    public function testArrayAccessUnsetRestoresDefault(): void
    {
        $options = new DataCollectionOptions([
            'user_info' => false,
            'http_bodies' => [],
            'stack_frame_variables' => false,
        ]);

        unset(
            $options['user_info'],
            $options['http_bodies'],
            $options['stack_frame_variables'],
            $options['unknown'],
            $options[0]
        );

        $this->assertTrue($options['user_info']);
        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $options['http_bodies']);
        $this->assertSame(['mode' => 'denyList', 'terms' => []], $options['stack_frame_variables']);
    }
}
