<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Processor;

use Raven\Event;

/**
 * This processor removes the `pre_context`, `context_line` and `post_context`
 * information from all exceptions captured by an event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizeStacktraceProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(Event $event)
    {
        $stacktrace = $event->getStacktrace();

        if (null === $stacktrace) {
            return $event;
        }

        foreach ($stacktrace->getFrames() as $frame) {
            $frame->setPreContext(null);
            $frame->setContextLine(null);
            $frame->setPostContext(null);
        }

        return $event->withStacktrace($stacktrace);
    }
}
