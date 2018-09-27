<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Util;

/**
 * This class provides some utility methods to encode/decode JSON data.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class JSON
{
    /**
     * Encodes the given data into JSON.
     *
     * @param mixed $data The data to encode
     *
     * @return string
     */
    public static function encode($data)
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(sprintf('Could not encode value into JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $encoded;
    }
}
