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
    private $_last_handled_error = null;

    public function __construct($client, $send_errors_last = false, $error_types = null,
                                $__error_types = null)
    {
        // support legacy fourth argument for error types
        if ($error_types === null) {
            $error_types = $__error_types;
        }

        $this->client = $client;
        $this->error_types = $error_types;
        if ($send_errors_last) {
            $this->send_errors_last = true;
            $this->client->store_errors_for_bulk_send = true;
        }
    }

    public function handleException($e, $isError = false, $vars = null)
    {
        $e->event_id = $this->client->captureException($e, null, null, $vars);

        if (!$isError && $this->call_existing_exception_handler && $this->old_exception_handler) {
            call_user_func($this->old_exception_handler, $e);
        }
    }

    public function handleError($type, $message, $file = '', $line = 0, $context = array())
    {
        if (error_reporting() !== 0) {
            $error_types = $this->error_types;
            if ($error_types === null) {
                $error_types = error_reporting();
            }
            if ($error_types & $type) {
                $e = new ErrorException($message, 0, $type, $file, $line);
                $this->handleException($e, true, $context);
                $this->_last_handled_error = $e;
            }
        }
        if ($this->call_existing_error_handler) {
            if ($this->old_error_handler !== null) {
                return call_user_func(
                    $this->old_error_handler,
                    $type,
                    $message,
                    $file,
                    $line,
                    $context
                );
            } else {
                return false;
            }
        }
    }

    public function handleFatalError()
    {
        if (null === $error = error_get_last()) {
            return;
        }

        unset($this->reservedMemory);

        if (error_reporting() !== 0) {
            $error_types = $this->error_types;
            if ($error_types === null) {
                $error_types = error_reporting();
            }
            if ($error_types & $error['type']) {
                $e = new ErrorException(
                    @$error['message'], 0, @$error['type'],
                    @$error['file'], @$error['line']
                );

                // ensure that if this error was reported via handleError that
                // we don't duplicate it here
                if ($this->_last_handled_error) {
                    $le = $this->_last_handled_error;
                    if ($e->getMessage() === $le->getMessage() &&
                        $e->getSeverity() === $le->getSeverity() &&
                        $e->getLine() === $le->getLine() &&
                        $e->getFile() === $le->getFile()
                    ) {
                        return;
                    }
                }

                $this->handleException($e, true);
            }
        }
    }

    /**
     * Register a handler which will intercept unhnalded exceptions and report them to the
     * associated Sentry client.
     *
     * @param bool $call_existing Call any existing exception handlers after processing
     *                            this instance.
     * @return $this
     */
    public function registerExceptionHandler($call_existing = true)
    {
        $this->old_exception_handler = set_exception_handler(array($this, 'handleException'));
        $this->call_existing_exception_handler = $call_existing;
        return $this;
    }

    /**
     * Register a handler which will intercept standard PHP errors and report them to the
     * associated Sentry client.
     *
     * @param bool $call_existing Call any existing errors handlers after processing
     *                            this instance.
     * @return array
     */
    public function registerErrorHandler($call_existing = true, $error_types = null)
    {
        if ($error_types !== null) {
            $this->error_types = $error_types;
        }
        $this->old_error_handler = set_error_handler(array($this, 'handleError'), E_ALL);
        $this->call_existing_error_handler = $call_existing;
        return $this;
    }

    /**
     * Register a fatal error handler, which will attempt to capture errors which
     * shutdown the PHP process. These are commonly things like OOM or timeouts.
     *
     * @param int $reservedMemorySize Number of kilobytes memory space to reserve,
     *                                which is utilized when handling fatal errors.
     * @return $this
     */
    public function registerShutdownFunction($reservedMemorySize = 10)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        $this->reservedMemory = str_repeat('x', 1024 * $reservedMemorySize);
        return $this;
    }
}
