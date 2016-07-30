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

// TODO(dcramer): deprecate default error types in favor of runtime configuration
// unless a reason can be determined that making them dynamic is better. They
// currently are not used outside of the fatal handler.
class Raven_ErrorHandler
{
    private $old_exception_handler;
    private $call_existing_exception_handler = false;
    private $old_error_handler;
    private $call_existing_error_handler = false;
    private $reservedMemory;
    private $send_errors_last = false;

    /**
     * @var array
     * Error types which should be processed by the handler.
     * A 'null' value implies "whatever error_reporting is at time of error".
     */
    private $error_types = null;

    public function __construct($client, $send_errors_last = false, $error_types = null,
                                $__error_types = null)
    {
        // support legacy fourth argument for error types
        if ($error_types === null) {
            $error_types = $__error_types;
        }

        $this->client = $client;
        $this->error_types = $error_types;
        register_shutdown_function(array($this, 'detectShutdown'));
        if ($send_errors_last) {
            $this->send_errors_last = true;
            $this->client->store_errors_for_bulk_send = true;
            register_shutdown_function(array($this->client, 'sendUnsentErrors'));
        }
    }

    public function handleException($e, $isError = false, $vars = null)
    {
        $e->event_id = $this->client->captureException($e, null, null, $vars);

        if (!$isError && $this->call_existing_exception_handler && $this->old_exception_handler) {
            call_user_func($this->old_exception_handler, $e);
        }
    }

    public function handleError($code, $message, $file = '', $line = 0, $context=array())
    {
        if (error_reporting() !== 0) {
            $error_types = $this->error_types;
            if ($error_types === null) {
                $error_types = error_reporting();
            }
            if ($error_types & $code) {
                $e = new ErrorException($message, 0, $code, $file, $line);
                $this->handleException($e, true, $context);
            }
        }
        if ($this->call_existing_error_handler) {
            if ($this->old_error_handler !== null) {
                return call_user_func($this->old_error_handler, $code, $message, $file, $line, $context);
            } else {
                return false;
            }
        }
    }

    public function handleFatalError()
    {
        if (null === $lastError = error_get_last()) {
            return;
        }

        unset($this->reservedMemory);

        if (error_reporting() !== 0) {
            $error_types = $this->error_types;
            if ($error_types === null) {
                $error_types = error_reporting();
            }
            if ($error_types & $lastError['type']) {
                $e = new ErrorException(
                    @$lastError['message'], @$lastError['type'], @$lastError['type'],
                    @$lastError['file'], @$lastError['line']
                );
                $this->handleException($e, true);
            }
        }
    }

    public function registerExceptionHandler($call_existing_exception_handler = true)
    {
        $this->old_exception_handler = set_exception_handler(array($this, 'handleException'));
        $this->call_existing_exception_handler = $call_existing_exception_handler;
        return $this;
    }

    /**
     * Register a handler which will intercept standard PHP errors and report them to the
     * associated Sentry client.
     *
     * @return array
     */
    //
    public function registerErrorHandler($call_existing_error_handler = true, $error_types = null)
    {
        if ($error_types !== null) {
            $this->error_types = $error_types;
        }
        $this->old_error_handler = set_error_handler(array($this, 'handleError'), E_ALL);
        $this->call_existing_error_handler = $call_existing_error_handler;
        return $this;
    }

    public function registerShutdownFunction($reservedMemorySize = 10)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        $this->reservedMemory = str_repeat('x', 1024 * $reservedMemorySize);
        return $this;
    }

    // TODO(dcramer): this should be in Client
    public function detectShutdown()
    {
        if (!defined('RAVEN_CLIENT_END_REACHED')) {
            define('RAVEN_CLIENT_END_REACHED', true);
        }
    }
}
