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
 * This processor removes all the data of the HTTP body to ensure no sensitive
 * informations are sent to the server in case the request method is POST, PUT,
 * PATCH or DELETE.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Raven_Processor_RemoveHttpBodyProcessor extends Raven_Processor
{
    /**
     * {@inheritdoc}
     */
    public function process(&$data)
    {
        if (isset($data['request'], $data['request']['method']) && in_array(strtoupper($data['request']['method']), array('POST', 'PUT', 'PATCH', 'DELETE'))) {
            $data['request']['data'] = self::STRING_MASK;
        }
    }
}
