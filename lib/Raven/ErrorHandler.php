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
 * $error_handler->registerShutdownFunction();
 *
 * @package raven
 */

class Raven_ErrorHandler
{
    private $old_exception_handler = null;
    private $call_existing_exception_handler = false;
    private $old_error_handler = null;
    private $call_existing_error_handler = false;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function handleException($e, $isError = false)
    {
        $e->event_id = $this->client->getIdent($this->client->captureException($e));

        if (!$isError && $this->call_existing_exception_handler && $this->old_exception_handler) {
            call_user_func($this->old_exception_handler, $e);
        }
    }

    public function handleError($code, $message, $file='', $line=0, $context=array())
    {    
        $e = new ErrorException($message, 0, $code, $file, $line);
        $this->handleException($e, true);

        if ($this->call_existing_error_handler && $this->old_error_handler) {
            call_user_func($this->old_error_handler, $code, $message, $file, $line, $context);
        }
    }

    public function handleFatalError()
    {
        if (null === $lastError = error_get_last()) {
            return;
        }

        self::freeMemory();

        $errors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING, E_STRICT);

        if (in_array($lastError['type'], $errors)) {
            $e = new ErrorException(
                @$lastError['message'], @$lastError['type'], @$lastError['type'],
                @$lastError['file'], @$lastError['line']
            );
            $this->handleException($e, true);
        }
    }

    public function registerExceptionHandler($call_existing_exception_handler = true)
    {
        $this->old_exception_handler = set_exception_handler(array($this, 'handleException'));
        $this->call_existing_exception_handler = $call_existing_exception_handler;
    }

    public function registerErrorHandler($call_existing_error_handler = true, $error_types = E_ALL)
    {
        $this->old_error_handler = set_error_handler(array($this, 'handleError'), $error_types);
        $this->call_existing_error_handler = $call_existing_error_handler;
    }

    public function registerShutdownFunction($reservedMemorySize = 10)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        self::reserveMemory($reservedMemorySize);
    }

    /**
     * This is allows to catch memory limit fatal errors.
     */
    private static function reserveMemory($reservedMemorySize)
    {
        $GLOBALS['tmp_buf'] = str_repeat('x', 1024 * $reservedMemorySize);
    }

    /**
     * Free momory
     */
    private static function freeMemory()
    {
        unset($GLOBALS['tmp_buf']);
    }
}