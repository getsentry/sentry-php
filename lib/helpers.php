<?php namespace Raven;

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
if ( ! function_exists("Raven\\get")) {

    /**
     * Safely fetch an element from an array,
     * falling back to a default.
     *
     * @param array|\ArrayAccess $array
     * @param string|int         $key
     * @param mixed              $default
     *
     * @return mixed
     */
    function get($array, $key, $default = null)
    {
        if (array_key_exists($array, $key)) {
            return $array[$key];
        }

        return $default;
    }
}