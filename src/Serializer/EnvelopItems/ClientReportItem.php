<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\ClientReport\ClientReport;
use Sentry\Event;

class ClientReportItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): ?string
    {
        $reports = $event->getClientReports();

        $headers = ['type' => 'client_report'];
        $body = [
            'timestamp' => $event->getTimestamp(),
            'discarded_events' => array_map(function (ClientReport $report) {
                return [
                    'category' => $report->getCategory(),
                    'reason' => $report->getReason(),
                    'quantity' => $report->getQuantity(),
                ];
            }, $reports),
        ];

        return \sprintf("%s\n%s", json_encode($headers), json_encode($body));
    }
}
