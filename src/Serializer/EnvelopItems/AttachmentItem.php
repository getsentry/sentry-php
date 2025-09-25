<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Attachment\Attachment;
use Sentry\Event;
use Sentry\Util\JSON;

class AttachmentItem implements EnvelopeItemInterface
{
    /**
     * {@inheritDoc}
     */
    public static function toEnvelopeItem(Event $event)
    {
        $attachments = $event->getAttachments();

        $items = [];

        foreach ($attachments as $attachment) {
            $header = [
                'filename' => $attachment->getFilename(),
                'content_type' => $attachment->getContentType(),
                'attachment_type' => 'event.attachment',
            ];

            $items[] = \sprintf("%s\n%s", JSON::encode($header), file_get_contents($attachment->getFilename()));
        }

        return $items;
    }

    public static function toAttachmentItem(Attachment $attachment): ?string
    {
        $data = $attachment->getData();
        if ($data === null) {
            return null;
        }

        $header = [
            'type' => 'attachment',
            'filename' => $attachment->getFilename(),
            'content_type' => $attachment->getContentType(),
            'attachment_type' => 'event.attachment',
            'length' => $attachment->getSize(),
        ];

        return \sprintf("%s\n%s", JSON::encode($header), $data);
    }

    /**
     * Returns the total size of all attachments in bytes.
     *
     * @param Attachment[] $attachments
     */
    public static function totalAttachmentSize(array $attachments): int
    {
        $sum = 0;
        foreach ($attachments as $attachment) {
            $sum += $attachment->getSize();
        }

        return $sum;
    }
}
