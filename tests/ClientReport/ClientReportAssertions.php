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
        $this->assertSame(self::sortClientReports($expected), self::sortClientReports(self::getClientReports()));
    }

    /**
     * Returns the client reports recorded so far.
     *
     * @return array<string, array<string, int>> the quantities keyed by category and reason
     */
    protected static function getClientReports(): array
    {
        $reflectionProperty = new \ReflectionProperty(ClientReportAggregator::class, 'reports');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        /** @var array<string, array<string, int>> $reports */
        $reports = $reflectionProperty->getValue(ClientReportAggregator::getInstance());
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        return $reports;
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
