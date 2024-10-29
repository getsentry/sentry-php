<?php

declare(strict_types=1);

namespace Sentry\Serializer;

use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Serializer\EnvelopItems\CheckInItem;
use Sentry\Serializer\EnvelopItems\EventItem;
use Sentry\Serializer\EnvelopItems\ProfileItem;
use Sentry\Serializer\EnvelopItems\TransactionItem;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Util\JSON;

/**
 * This is a simple implementation of a serializer that takes in input an event
 * object and returns a serialized string ready to be sent off to Sentry.
 *
 * @internal
 */
final class PayloadSerializer implements PayloadSerializerInterface
{
    /**
     * @var Options The SDK client options
     */
    private $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(Event $event): string
    {
        // @see https://develop.sentry.dev/sdk/envelopes/#envelope-headers
        $envelopeHeader = [
            'event_id' => (string) $event->getId(),
            'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'dsn' => (string) $this->options->getDsn(),
            'sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
        ];

        $dynamicSamplingContext = $event->getSdkMetadata('dynamic_sampling_context');
        if ($dynamicSamplingContext instanceof DynamicSamplingContext) {
            $entries = $dynamicSamplingContext->getEntries();

            if (!empty($entries)) {
                $envelopeHeader['trace'] = $entries;
            }
        }

        $items = '';

        switch ($event->getType()) {
            case EventType::event():
                $items = EventItem::toEnvelopeItem($event);
                break;
            case EventType::transaction():
                $transactionItem = TransactionItem::toEnvelopeItem($event);
                if ($event->getSdkMetadata('profile') !== null) {
                    $profileItem = ProfileItem::toEnvelopeItem($event);
                    if ($profileItem !== '') {
                        $items = \sprintf("%s\n%s", $transactionItem, $profileItem);
                        break;
                    }
                }
                $items = $transactionItem;
                break;
            case EventType::checkIn():
                $items = CheckInItem::toEnvelopeItem($event);
                break;
        }

        return \sprintf("%s\n%s", JSON::encode($envelopeHeader), $items);
    }
}
