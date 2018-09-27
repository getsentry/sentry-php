<?php

namespace Sentry\Util;

class PHPVersion
{
    const VERSION_PARSING_REGEX = '/^(?<base>\d\.\d\.\d{1,2})(?<extra>-(beta|rc)-?(\d+)?(-dev)?)?/i';

    /**
     * @param string $version
     *
     * @return string
     */
    public static function parseVersion($version = PHP_VERSION)
    {
        if (!preg_match(self::VERSION_PARSING_REGEX, $version, $matches)) {
            return $version;
        }

        $version = $matches['base'];
        if (isset($matches['extra'])) {
            $version .= $matches['extra'];
        }

        return $version;
    }
}
