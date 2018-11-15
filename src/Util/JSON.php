<?php

declare(strict_types=1);

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
     *
     * @throws \InvalidArgumentException If the encoding failed
     */
    public static function encode($data): string
    {
        $encodedData = json_encode($data, JSON_UNESCAPED_UNICODE);

        if (JSON_ERROR_NONE !== json_last_error() || false === $encodedData) {
            throw new \InvalidArgumentException(sprintf('Could not encode value into JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $encodedData;
    }
}
