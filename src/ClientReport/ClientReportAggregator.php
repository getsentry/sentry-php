<?php

declare(strict_types=1);

namespace Sentry\ClientReport;

use Sentry\Event;
use Sentry\EventType;
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
        if (!$this->isEnabled()) {
            return;
        }

        $category = $category->getValue();
        $reason = $reason->getValue();
        if ($quantity <= 0) {
            $client = HubAdapter::getInstance()->getClient();
            if ($client !== null) {
                $logger = $client->getOptions()->getLoggerOrNullLogger();
                $logger->debug('Dropping Client report with category={category} and reason={reason} because quantity is zero or negative ({quantity})', [
                    'category' => $category,
                    'reason' => $reason,
                    'quantity' => $quantity,
                ]);
            }

            return;
        }
        $this->reports[$category][$reason] = ($this->reports[$category][$reason] ?? 0) + $quantity;
    }

    /**
     * Records a discarded event in all the data categories it accounts for, e.g. a
     * transaction also discards its spans and its profile.
     */
    public function addEvent(Event $event, Reason $reason): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        switch ($event->getType()) {
            case EventType::event():
                $this->add(DataCategory::error(), $reason, 1);
                $this->addAttachments($event, $reason);
                break;
            case EventType::transaction():
                $this->add(DataCategory::transaction(), $reason, 1);
                // Relay extracts an additional span from the transaction itself
                $this->add(DataCategory::span(), $reason, \count($event->getSpans()) + 1);

                if ($event->getSdkMetadata('profile') !== null) {
                    $this->add(DataCategory::profile(), $reason, 1);
                }

                $this->addAttachments($event, $reason);
                break;
            case EventType::checkIn():
                $this->add(DataCategory::checkIn(), $reason, 1);
                break;
            case EventType::logs():
                $this->add(DataCategory::logItem(), $reason, \count($event->getLogs()));
                break;
            case EventType::metrics():
                $this->add(DataCategory::metric(), $reason, \count($event->getMetrics()));
                break;
        }
    }

    public function flush(): void
    {
        if (empty($this->reports)) {
            return;
        }
        $reports = [];
        foreach ($this->reports as $category => $reasons) {
            foreach ($reasons as $reason => $quantity) {
                $reports[] = new DiscardedEvent($category, $reason, $quantity);
            }
        }
        $event = Event::createClientReport();
        $event->setClientReports($reports);

        $client = HubAdapter::getInstance()->getClient();

        // Reset the client reports only if we successfully sent an event. If it fails it
        // can be sent on the next flush, or it gets discarded anyway.
        if ($client !== null && $client->captureEvent($event) !== null) {
            $this->reports = [];
        }
    }

    private function isEnabled(): bool
    {
        $client = HubAdapter::getInstance()->getClient();

        return $client !== null && $client->getOptions()->shouldSendClientReports();
    }

    private function addAttachments(Event $event, Reason $reason): void
    {
        $bytes = 0;

        foreach ($event->getAttachments() as $attachment) {
            $bytes += $attachment->getSize() ?? 0;
        }

        if ($bytes > 0) {
            $this->add(DataCategory::attachment(), $reason, $bytes);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
