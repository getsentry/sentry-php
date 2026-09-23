<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpMessageType;
use Sentry\Options;
use Sentry\State\HubInterface;

final class DataCollectionPolicyTest extends TestCase
{
    public function testMissingClientUsesLegacyModeWithoutPii(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn(null);
        $policy = DataCollectionPolicy::fromHub($hub);

        $this->assertTrue($policy->isLegacyMode());
        $this->assertFalse($policy->shouldCollectUserInfo());
        $this->assertNull($policy->getOptions());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getFrameContextLines());
    }

    /**
     * @dataProvider userInfoProvider
     */
    public function testUserInfoUsesOnlyTheActiveMode(array $configuration, bool $expected): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options($configuration));

        $this->assertSame($expected, $policy->shouldCollectUserInfo());
    }

    public function userInfoProvider(): \Generator
    {
        yield 'legacy default' => [[], false];
        yield 'legacy enabled' => [['send_default_pii' => true], true];
        yield 'configured default ignores disabled legacy option' => [['data_collection' => [], 'send_default_pii' => false], true];
        yield 'configured disabled' => [['data_collection' => ['user_info' => false], 'send_default_pii' => true], false];
    }

    public function testFromHubUsesClientConfiguration(): void
    {
        $options = new Options(['send_default_pii' => true]);
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn($options);
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn($client);

        $policy = DataCollectionPolicy::fromHub($hub);

        $this->assertTrue($policy->shouldCollectUserInfo());
    }

    /**
     * @dataProvider httpBodyLimitProvider
     */
    public function testHttpBodyLimits(string $maxRequestBodySize, ?int $expectedRequestLimit): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options([
            'data_collection' => [],
            'max_request_body_size' => $maxRequestBodySize,
        ]));

        $this->assertSame($expectedRequestLimit, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertSame($expectedRequestLimit, $policy->getHttpBodyLimit(HttpMessageType::outgoingRequest()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::incomingResponse()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());
    }

    public function httpBodyLimitProvider(): \Generator
    {
        yield 'small' => ['small', 1000];
        yield 'medium' => ['medium', 10000];
        yield 'never' => ['never', null];
        yield 'always' => ['always', 100000];
    }

    public function testHttpBodyCollectionRespectsSelectedTypes(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options([
            'data_collection' => ['http_bodies' => ['outgoingRequest']],
            'max_request_body_size' => 'always',
        ]));

        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingResponse()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::outgoingRequest()));
    }

    public function testHttpBodyCollectionCanBeDisabled(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options([
            'data_collection' => ['http_bodies' => []],
            'max_request_body_size' => 'always',
        ]));

        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());
    }

    /**
     * @dataProvider legacyRequestBodyLimitProvider
     */
    public function testLegacyRequestBodyLimits(string $maxRequestBodySize, ?int $expectedLimit): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['max_request_body_size' => $maxRequestBodySize]));

        $this->assertSame($expectedLimit, $policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
    }

    public function legacyRequestBodyLimitProvider(): \Generator
    {
        yield 'small' => ['small', 1000];
        yield 'medium' => ['medium', 10000];
        yield 'never' => ['never', null];
        yield 'always' => ['always', -1];
    }

    /**
     * @dataProvider frameContextLinesProvider
     *
     * @param array<string, mixed> $configuration
     */
    public function testFrameContextLinesUseOnlyTheActiveMode(array $configuration, ?int $expected): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options($configuration));

        $this->assertSame($expected, $policy->getFrameContextLines());
    }

    public function frameContextLinesProvider(): \Generator
    {
        yield 'legacy default' => [[], 5];
        yield 'legacy configured' => [['context_lines' => 3], 3];
        yield 'legacy disabled' => [['context_lines' => null], null];
        yield 'configured default ignores legacy option' => [['data_collection' => [], 'context_lines' => null], 5];
        yield 'configured' => [['data_collection' => ['frame_context_lines' => 0], 'context_lines' => 3], 0];
    }
}
