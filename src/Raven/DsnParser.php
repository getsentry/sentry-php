<?php

namespace Raven;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class DsnParser
{
    private static $pathRegex = '#
        ^
        (?P<path>
            /
            (?:[^/]+/)*
        )
        (?P<project_id>
            [^/]+
        )
        /?
        $
    #x';

    /**
     * @param  string $dsn
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function parse($dsn)
    {
        $dsnParts = parse_url($dsn);

        if (false === $dsnParts) {
            throw new \InvalidArgumentException(
                sprintf('Malformed DSN "%s".', $dsn)
            );
        }

        $requiredParts = array('scheme', 'user', 'pass', 'host', 'path');
        $missingRequiredParts = array_diff($requiredParts, array_flip($dsnParts));
        if (!empty($missingRequiredParts)) {
            throw new \InvalidArgumentException(
                sprintf('The DSN is missing the %s part(s).', join(', ', $missingRequiredParts))
            );
        }

        if (!preg_match(self::$pathRegex, $dsnParts['path'], $matches)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid DSN path "%s".', $dsnParts['path'])
            );
        }

        return array(
            'protocol' => $dsnParts['scheme'],
            'public_key' => $dsnParts['user'],
            'secret_key' => $dsnParts['pass'],
            'host' => $dsnParts['host'],
            'port' => isset($dsnParts['port']) ? $dsnParts['port'] : null,
            'path' => $matches['path'],
            'project_id' => $matches['project_id'],
        );
    }
}
