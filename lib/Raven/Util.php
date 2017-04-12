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

    /**
     * Determine how many parameters a callable/Closure expects when it is called.
     *
     * Only checks required parameters.
     *
     * @param callable $callable The callable to check
     *
     * @return int
     */
    public static function getCallableParamNum(callable $callable)
    {
        $reflection_fn = new ReflectionFunction($callable);

        return (int) $reflection_fn->getNumberOfRequiredParameters();
    }
}
