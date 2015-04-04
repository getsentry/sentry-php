<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (version_compare(phpversion(), "5.4.0", ">="))
{
    error_reporting(E_ALL);
}
else
{
    error_reporting(E_ALL | E_STRICT);
}

session_start();

require __DIR__ . "/../vendor/autoload.php";
