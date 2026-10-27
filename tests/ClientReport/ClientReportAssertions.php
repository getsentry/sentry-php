<?php

declare(strict_types=1);

namespace Sentry\Tests\ClientReport;

use Sentry\ClientReport\ClientReportAggregator;

trait ClientReportAssertions
{
    /**
     * Asserts the client reports recorded so far, independent of their order.
     *
     * @param array<string, array<string, int>> $expected the quantities keyed by category and reason
     */
    protected function assertClientReports(array $expected): void
    {
        $reflectionProperty = new \ReflectionProperty(ClientReportAggregator::class, 'reports');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        /** @var array<string, array<string, int>> $actual */
        $actual = $reflectionProperty->getValue(ClientReportAggregator::getInstance());
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        $this->assertSame(self::sortClientReports($expected), self::sortClientReports($actual));
    }

    /**
     * @param array<string, array<string, int>> $reports
     *
     * @return array<string, array<string, int>>
     */
    private static function sortClientReports(array $reports): array
    {
        ksort($reports);

        foreach ($reports as &$reasons) {
            ksort($reasons);
        }

        return $reports;
    }
}
