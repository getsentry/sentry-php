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
 * Event handlers for exceptions and errors
 *
 * $client = new Raven_Client('http://public:secret/example.com/1');
 * $error_handler = new Raven_ErrorHandler($client);
 * set_error_handler(array($error_handler, 'handleError');
 * set_exception_handler(array($error_handler, 'handleException'));
 *
 * @package raven
 */

class Raven_ErrorHandler
{
    function __construct($client) {
        $this->client = $client;
    }

    function handleException($e) {
        $this->client->captureException($e);
    }

    function handleError($code, $message, $file='', $line=0, $context=array()) {
        $e = new ErrorException($message, 0, $code, $file, $line);
        $this->handleException($e);
    }
}
?>