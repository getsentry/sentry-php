<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\DataCollectionPolicy;
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
        $this->assertFalse($policy->shouldCollectDatabaseQueryData());
        $this->assertNull($policy->getOptions());
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

    /**
     * @dataProvider databaseQueryDataProvider
     */
    public function testDatabaseQueryDataRequiresConfiguredMode(array $configuration, bool $expected): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options($configuration));

        $this->assertSame($expected, $policy->shouldCollectDatabaseQueryData());
    }

    public function databaseQueryDataProvider(): \Generator
    {
        foreach ([false, true] as $sendDefaultPii) {
            $pii = $sendDefaultPii ? 'PII enabled' : 'PII disabled';

            yield 'absent with ' . $pii => [['send_default_pii' => $sendDefaultPii], false];
            yield 'null with ' . $pii => [['send_default_pii' => $sendDefaultPii, 'data_collection' => null], false];
            yield 'configured default with ' . $pii => [['send_default_pii' => $sendDefaultPii, 'data_collection' => []], true];
            yield 'explicitly enabled with ' . $pii => [['send_default_pii' => $sendDefaultPii, 'data_collection' => ['database_query_data' => true]], true];
            yield 'explicitly disabled with ' . $pii => [['send_default_pii' => $sendDefaultPii, 'data_collection' => ['database_query_data' => false]], false];
        }
    }

    public function testDatabasePolicyObservesMutableAndReplacedConfiguration(): void
    {
        $options = new Options(['data_collection' => ['database_query_data' => false]]);
        $policy = DataCollectionPolicy::fromOptions($options);
        $dataCollection = $policy->getDataCollection();
        $this->assertInstanceOf(DataCollectionOptions::class, $dataCollection);

        $dataCollection->setDatabaseQueryData(true);
        $this->assertTrue($policy->shouldCollectDatabaseQueryData());

        $options->updateOptions(['data_collection' => ['database_query_data' => false]]);
        $this->assertFalse($policy->shouldCollectDatabaseQueryData());

        $options->updateOptions(['data_collection' => []]);
        $this->assertTrue($policy->shouldCollectDatabaseQueryData());

        $options->updateOptions(['data_collection' => null, 'send_default_pii' => true]);
        $this->assertFalse($policy->shouldCollectDatabaseQueryData());
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
}
