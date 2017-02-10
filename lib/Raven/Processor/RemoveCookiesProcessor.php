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
 * This processor removes all the cookies from the request to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Raven_Processor_RemoveCookiesProcessor extends Raven_Processor
{
    /**
     * {@inheritdoc}
     */
    public function process(&$data)
    {
        if (isset($data['request'])) {
            if (isset($data['request']['cookies'])) {
                $data['request']['cookies'] = self::STRING_MASK;
            }

            if (isset($data['request']['headers']) && isset($data['request']['headers']['Cookie'])) {
                $data['request']['headers']['Cookie'] = self::STRING_MASK;
            }
        }
    }
}
