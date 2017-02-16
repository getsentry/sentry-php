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
 * Autoloads Raven classes.
 *
 * @package raven
 */
class Autoloader
{
    /**
     * Registers \Raven\Autoloader as an SPL autoloader.
     */
    public static function register()
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(
            function ($class) {
                Autoloader::autoload($class);
            }
        );
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     */
    public static function autoload($class)
    {
        if (0 !== strpos($class, 'Raven\\')) {
            return;
        }

        $file = dirname(__FILE__).'/../'.str_replace(array('\\', "\0"), array('/', ''), $class).'.php';
        if (is_file($file)) {
            /** @noinspection PhpIncludeInspection */
            require_once $file;
        }
    }
}
