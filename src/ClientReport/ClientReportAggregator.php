<?php

declare(strict_types=1);

namespace Sentry\ClientReport;

use Sentry\Event;
use Sentry\State\HubAdapter;
use Sentry\Transport\DataCategory;

class ClientReportAggregator
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * Nested array for local aggregation. The first key is the category and the second one is the reason.
     *
     * ```
     * [
     *  'example-category' => [
     *      'example-reason' => 10
     *   ]
     * ]
     *```
     *
     * @var array<array<string, int>>
     */
    private $reports = [];

    public function add(DataCategory $category, Reason $reason, int $quantity): void
    {
        $category = $category->getValue();
        $reason = $reason->getValue();
        if ($quantity <= 0) {
            $client = HubAdapter::getInstance()->getClient();
            if ($client !== null) {
                $logger = $client->getOptions()->getLoggerOrNullLogger();
                $logger->debug('Dropping Client report with category={category} and reason={} because quantity is zero or negative ({quantity})', [
                    'category' => $category,
                    'reason' => $reason,
                    'quantity' => $quantity,
                ]);

                return;
            }
        }
        $this->reports[$category][$reason] = ($this->reports[$category][$reason] ?? 0) + $quantity;
    }

    public function flush(): void
    {
        if (empty($this->reports)) {
            return;
        }
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
