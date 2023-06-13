<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;

final class MonitorConfigTest extends TestCase
{
    public function testConstructor(): void
    {
        $monitorSchedule = MonitorSchedule::crontab('* * * * *');
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('* * * * *'),
            10,
            12,
            'Europe/Amsterdam'
        );

        $this->assertEquals($monitorSchedule, $monitorConfig->getSchedule());
        $this->assertEquals(10, $monitorConfig->getCheckinMargin());
        $this->assertEquals(12, $monitorConfig->getMaxRuntime());
        $this->assertEquals('Europe/Amsterdam', $monitorConfig->getTimezone());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('* * * * *')
        );
        $monitorConfig->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $monitorConfig->$getterMethod());
    }

    public function gettersAndSettersDataProvider(): array
    {
        return [
            ['getSchedule', 'setSchedule', MonitorSchedule::crontab('foo')],
            ['getCheckinMargin', 'setCheckinMargin', 10],
            ['getMaxRuntime', 'setMaxRuntime', 12],
            ['getTimezone', 'setTimezone', 'Europe/Amsterdam'],
        ];
    }
}
