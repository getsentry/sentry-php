<?php

declare(strict_types=1);

namespace Sentry\Util;

use Sentry\Exception\JsonException;

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
     * @param mixed $data     The data to encode
     * @param int   $options  Bitmask consisting of JSON_* constants
     * @param int   $maxDepth The maximum depth allowed for serializing $data
     *
     * @return mixed
     *
     * @throws JsonException If the encoding failed
     */
    public static function encode($data, int $options = 0, int $maxDepth = 512)
    {
        $options |= JSON_UNESCAPED_UNICODE;

        if (\PHP_VERSION_ID >= 70200) {
            /** @psalm-suppress UndefinedConstant */
            $options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encodedData = json_encode($data, $options);

        // This should never happen on PHP >= 7.2 as the substitution of invalid
        // UTF-8 characters is done internally. On lower versions instead, we
        // try to sanitize the data ourselves before retrying encoding. If it
        // fails again we throw an exception as usual.
        if (JSON_ERROR_UTF8 === json_last_error()) {
            $encodedData = json_encode(self::sanitizeData($data, $maxDepth - 1), $options);
        }

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(sprintf('Could not encode value into JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $encodedData;
    }

    /**
     * Decodes the given data from JSON.
     *
     * @param string $data The data to decode
     *
     * @return mixed
     *
     * @throws JsonException If the decoding failed
     */
    public static function decode(string $data)
    {
        $decodedData = json_decode($data, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(sprintf('Could not decode value from JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $decodedData;
    }

    /**
     * Performs sanity checks on data that shall be encoded to JSON.
     *
     * @param mixed $data     The data to sanitize
     * @param int   $maxDepth The maximum depth to walk through `$data`
     *
     * @return mixed
     *
     * @throws JsonException If the value of $maxDepth is less than 0
     */
    private static function sanitizeData($data, int $maxDepth)
    {
        if ($maxDepth < 0) {
            throw new JsonException('Reached the maximum depth limit while sanitizing the data.');
        }

        if (\is_string($data)) {
            return self::convertStringToUtf8($data);
        } elseif (\is_array($data) || \is_object($data)) {
            $output = [];

            foreach ($data as $key => $value) {
                if (\is_string($key)) {
                    $key = self::convertStringToUtf8($key);
                }

                if (\is_string($value)) {
                    $value = self::convertStringToUtf8($value);
                } elseif (\is_array($value) || \is_object($value)) {
                    // This check is here because the `Event::toArray()` method
                    // is broken and doesn't return all child items as scalars
                    // or objects/arrays, so the sanitification would fail (e.g.
                    // on breadcrumb objects which do not expose public properties
                    // to iterate on)
                    if (\is_object($value) && method_exists($value, 'toArray')) {
                        $value = $value->toArray();
                    }

                    $value = self::sanitizeData($value, $maxDepth - 1);
                }

                $output[$key] = $value;
            }

            return \is_array($data) ? $output : (object) $output;
        } else {
            return $data;
        }
    }

    /**
     * Converts a string to UTF-8 to avoid errors during its encoding to
     * the JSON format.
     *
     * @param string $value The text to convert to UTF-8
     */
    private static function convertStringToUtf8(string $value): string
    {
        $previousSubstituteCharacter = mb_substitute_character();
        $encoding = mb_detect_encoding($value, mb_detect_order(), true);

        mb_substitute_character(0xfffd);

        $value = mb_convert_encoding($value, 'UTF-8', $encoding ?: 'UTF-8');

        mb_substitute_character($previousSubstituteCharacter);

        return $value;
    }
}
