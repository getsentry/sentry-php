<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Attachment\Attachment;
use Sentry\Event;
use Sentry\Util\JSON;

class AttachmentItem
{
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
}
