<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This processor removes the `pre_context`, `context_line` and `post_context`
 * informations from all exceptions captured by an event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Raven_Processor_SanitizeStacktraceProcessor extends Raven_Processor
{
    /**
     * {@inheritdoc}
     */
    public function process(&$data)
    {
        if (!isset($data['exception'], $data['exception']['values'])) {
            return;
        }

        foreach ($data['exception']['values'] as &$exception) {
            if (!isset($exception['stacktrace'])) {
                continue;
            }

            foreach ($exception['stacktrace']['frames'] as &$frame) {
                unset($frame['pre_context'], $frame['context_line'], $frame['post_context']);
            }
        }
    }
}
