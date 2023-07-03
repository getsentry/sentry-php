<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\CheckIn;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\Util\SentryUid;

final class CheckInTest extends TestCase
{
    public function testConstructor(): void
    {
        $checkInId = SentryUid::generate();
        $checkIn = new CheckIn(
            'my-monitor',
            CheckInStatus::ok(),
            $checkInId,
            '1.0.0',
            'dev',
            10
        );

        $this->assertEquals($checkInId, $checkIn->getId());
        $this->assertEquals('my-monitor', $checkIn->getMonitorSlug());
        $this->assertEquals('ok', $checkIn->getStatus());
        $this->assertEquals('1.0.0', $checkIn->getRelease());
        $this->assertEquals('dev', $checkIn->getEnvironment());
        $this->assertEquals(10, $checkIn->getDuration());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $checkIn = new CheckIn(
            'my-monitor',
            CheckInStatus::ok()
        );
        $checkIn->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $checkIn->$getterMethod());
    }

    public static function gettersAndSettersDataProvider(): array
    {
        return [
            ['getId', 'setId', SentryUid::generate()],
            ['getMonitorSlug', 'setMonitorSlug', 'my-monitor'],
            ['getStatus', 'setStatus', CheckInStatus::ok()],
            ['getRelease', 'setRelease', '1.0.0'],
            ['getEnvironment', 'setEnvironment', 'dev'],
            ['getDuration', 'setDuration', 10],
            ['getMonitorConfig', 'setMonitorConfig', new MonitorConfig(MonitorSchedule::crontab('* * * * *'))],
        ];
    }
}
