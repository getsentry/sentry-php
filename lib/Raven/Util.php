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
 * Utilities
 *
 * @package raven
 */

class Raven_Util
{
    public static function get($array, $var, $default=null)
    {
        if (isset($array[$var])) {
            return $array[$var];
        }
        return $default;
    }
}