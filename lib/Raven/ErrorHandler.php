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
 * $error_handler->registerExceptionHandler();
 * $error_handler->registerErrorHandler();
 *
 * @package raven
 */

class Raven_ErrorHandler
{
    function __construct($client) {
        $this->client = $client;
    }

    function handleException($e, $isError = false) {
        $e->event_id = $this->client->getIdent($this->client->captureException($e));

        if (!$isError && $this->call_existing_exception_handler && $this->old_exception_handler) {
            call_user_func($this->old_exception_handler, $e);
        }
    }

    function handleError($code, $message, $file='', $line=0, $context=array()) {
        
        $e = new ErrorException($message, 0, $code, $file, $line);
        $this->handleException($e, true);


        if ($this->call_existing_error_handler && $this->old_error_handler) {
            call_user_func($this->old_error_handler, $code, $message, $file, $line, $context);
        }
    }

    function registerExceptionHandler($call_existing_exception_handler = true)
    {
        $this->old_exception_handler = set_exception_handler(array($this, 'handleException'));
        $this->call_existing_exception_handler = $call_existing_exception_handler;
    }

    function registerErrorHandler($call_existing_error_handler = true, $error_types = E_ALL)
    {
        $this->old_error_handler = set_error_handler(array($this, 'handleError'), $error_types);
        $this->call_existing_error_handler = $call_existing_error_handler;
    }
    
    private $old_exception_handler = null;
    private $call_existing_exception_handler = false;
    private $old_error_handler = null;
    private $call_existing_error_handler = false;
}