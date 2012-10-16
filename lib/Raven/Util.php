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

    public static function apply($value, $fn, $key=null) {
        if (is_array($value)) {
            foreach ($value as $k=>$v) {
                $value[$k] = self::apply($v, $fn, $k);
            }
            return $value;
        }
        return call_user_func($fn, $key, $value);
    }

    public static function to_string($value) {
        return (string)$value;
    }
}