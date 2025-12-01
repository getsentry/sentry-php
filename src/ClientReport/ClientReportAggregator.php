<?php

declare(strict_types=1);

namespace Sentry\ClientReport;

use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\HubAdapter;
use Sentry\Transport\DataCategory;

class ClientReportAggregator
{
    private static $instance;

    /**
     * Nested array for local aggregation.
     *
     * @var array
     */
    private $reports = [];

    public function add(DataCategory $category, Reason $reason, int $quantity): void
    {
        $this->reports[(string) $category][(string) $reason] = ($this->reports[(string) $category][(string) $reason] ?? 0) + $quantity;
    }

    public function flush(): void
    {
        $reports = [];
        foreach ($this->reports as $category => $reasons) {
            foreach ($reasons as $reason => $quantity) {
                $reports[] = new ClientReport($category, $reason, $quantity);
            }
        }
        $event = Event::createClientReport();
        $event->setClientReports($reports);

        HubAdapter::getInstance()->captureEvent($event);
        $this->reports = [];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
