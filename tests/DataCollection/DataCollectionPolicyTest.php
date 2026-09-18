<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\DataCollection\DataCollectionOptions;
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
        yield 'legacy disabled' => [['send_default_pii' => false], false];
        yield 'legacy enabled' => [['send_default_pii' => true], true];
        yield 'configured default ignores disabled legacy option' => [['data_collection' => [], 'send_default_pii' => false], true];
        yield 'configured default ignores enabled legacy option' => [['data_collection' => [], 'send_default_pii' => true], true];
        yield 'configured disabled' => [['data_collection' => ['user_info' => false], 'send_default_pii' => true], false];
    }

    public function testPolicyObservesLegacyPiiUpdates(): void
    {
        $options = new Options(['send_default_pii' => false]);
        $policy = DataCollectionPolicy::fromOptions($options);

        $options->updateOptions(['send_default_pii' => true]);

        $this->assertTrue($policy->isLegacyMode());
        $this->assertTrue($policy->shouldCollectUserInfo());
    }

    public function testPolicyObservesDataCollectionReplacement(): void
    {
        $options = new Options(['send_default_pii' => true]);
        $policy = DataCollectionPolicy::fromOptions($options);

        $options->updateOptions(['data_collection' => ['user_info' => false]]);

        $this->assertFalse($policy->isLegacyMode());
        $this->assertFalse($policy->shouldCollectUserInfo());
    }

    public function testPolicyObservesMutableDataCollectionOptions(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['data_collection' => ['user_info' => false]]));
        $dataCollection = $policy->getDataCollection();
        $this->assertInstanceOf(DataCollectionOptions::class, $dataCollection);

        $dataCollection->setUserInfo(true);

        $this->assertTrue($policy->shouldCollectUserInfo());
    }

    public function testPolicyObservesTransitionBackToLegacyMode(): void
    {
        $options = new Options(['data_collection' => [], 'send_default_pii' => true]);
        $policy = DataCollectionPolicy::fromOptions($options);

        $options->updateOptions(['data_collection' => null]);

        $this->assertTrue($policy->isLegacyMode());
        $this->assertTrue($policy->shouldCollectUserInfo());
    }

    public function testFromHubUsesTheCurrentClientOptions(): void
    {
        $options = new Options(['data_collection' => []]);
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')->willReturn($options);
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn($client);

        $policy = DataCollectionPolicy::fromHub($hub);

        $this->assertSame($options, $policy->getOptions());
        $this->assertSame($options->getDataCollection(), $policy->getDataCollection());
    }

    public function testHttpBodyLimitsObserveOptionUpdates(): void
    {
        $options = new Options(['data_collection' => [], 'max_request_body_size' => 'small']);
        $policy = DataCollectionPolicy::fromOptions($options);

        $this->assertSame(1000, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::incomingResponse()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());

        $options->setMaxRequestBodySize('medium');

        $this->assertSame(10000, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));

        $options->setMaxRequestBodySize('never');

        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::incomingResponse()));

        $options->setMaxRequestBodySize('always');
        $options->updateOptions(['data_collection' => ['http_bodies' => ['outgoingRequest']]]);

        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingResponse()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::outgoingRequest()));

        $collection = $policy->getDataCollection();
        $this->assertInstanceOf(DataCollectionOptions::class, $collection);
        $collection->setHttpBodies([HttpMessageType::incomingRequest()]);

        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::outgoingRequest()));
    }

    public function testLegacyAlwaysOnlyRemovesRequestBodyLimit(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['max_request_body_size' => 'always']));

        $this->assertSame(-1, $policy->getMaxHttpBodyLength(HttpMessageType::incomingRequest()));
        $this->assertSame(-1, $policy->getMaxHttpBodyLength(HttpMessageType::outgoingRequest()));
        $this->assertSame(100000, $policy->getMaxHttpBodyLength(HttpMessageType::incomingResponse()));
        $this->assertSame(100000, $policy->getMaxHttpBodyLength(HttpMessageType::outgoingResponse()));
        $this->assertSame(-1, $policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
    }

    public function testAlwaysBodyLimitsObserveCollectionModeChanges(): void
    {
        $options = new Options(['data_collection' => null, 'max_request_body_size' => 'always']);
        $policy = DataCollectionPolicy::fromOptions($options);

        $this->assertSame(-1, $policy->getLegacyRequestBodyLimit());

        $options->updateOptions(['data_collection' => []]);

        $this->assertSame(100000, $policy->getMaxHttpBodyLength(HttpMessageType::incomingRequest()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertSame(100000, $policy->getHttpBodyLimit(HttpMessageType::outgoingRequest()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());

        $options->updateOptions(['data_collection' => ['http_bodies' => []]]);

        $this->assertSame(100000, $policy->getMaxHttpBodyLength(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
        $this->assertNull($policy->getLegacyRequestBodyLimit());

        $options->updateOptions(['data_collection' => null]);

        $this->assertSame(-1, $policy->getMaxHttpBodyLength(HttpMessageType::incomingRequest()));
        $this->assertSame(-1, $policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
    }

    public function testLegacyBodyLimitsObserveModeAndSizeUpdates(): void
    {
        $options = new Options(['max_request_body_size' => 'small']);
        $policy = DataCollectionPolicy::fromOptions($options);

        $this->assertSame(1000, $policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));

        $options->setMaxRequestBodySize('never');

        $this->assertNull($policy->getLegacyRequestBodyLimit());

        $options->setMaxRequestBodySize('medium');
        $options->updateOptions(['data_collection' => []]);

        $this->assertNull($policy->getLegacyRequestBodyLimit());
        $this->assertSame(10000, $policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));

        $options->updateOptions(['data_collection' => null]);

        $this->assertSame(10000, $policy->getLegacyRequestBodyLimit());
        $this->assertNull($policy->getHttpBodyLimit(HttpMessageType::incomingRequest()));
    }
}
