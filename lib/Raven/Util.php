<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

/**
 * Utilities
 *
 * @package raven
 */

class Util
{
    /**
     * Because we love Python, this works much like dict.get() in Python.
     *
     * Returns $var from $array if set, otherwise returns $default.
     *
     * @param array  $array
     * @param string $var
     * @param mixed  $default
     * @return mixed
     */
    public static function get($array, $var, $default = null)
    {
        if (isset($array[$var])) {
            return $array[$var];
        }

        return $default;
    }
}
