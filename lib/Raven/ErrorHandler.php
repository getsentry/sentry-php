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

    /**
     * @deprecated
     * @var array
     * Error types that can be processed by the handler
     */
    private $validErrorTypes = array(
        E_ERROR,
        E_WARNING,
        E_PARSE,
        E_NOTICE,
        E_CORE_ERROR,
        E_CORE_WARNING,
        E_COMPILE_ERROR,
        E_COMPILE_WARNING,
        E_USER_ERROR,
        E_USER_WARNING,
        E_USER_NOTICE,
        E_STRICT,
        E_RECOVERABLE_ERROR,
        E_DEPRECATED,
        E_USER_DEPRECATED,
    );

    /**
     * @deprecated
     * @var array
     * The default Error types that are always processed by the handler. Can be set during construction.
     */
    private $defaultErrorTypes = array(
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_CORE_WARNING,
        E_COMPILE_ERROR,
        E_COMPILE_WARNING,
        E_STRICT,
    );

    public function __construct($client, $send_errors_last = false, $default_error_types = null,
                                $error_types = null)
    {
        $this->client = $client;
        if ($default_error_types !== null) {
            $this->defaultErrorTypes = $default_error_types;
        }
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
        $e->event_id = $this->client->getIdent($this->client->captureException($e, null, null, $vars));

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

    /**
     * Nothing by default, use it in child classes for catching other types of errors
     * Only constants from $this->validErrorTypes can be used
     *
     * @deprecated
     * @return array
     */

    protected function getAdditionalErrorTypesToProcess()
    {
        return array();
    }

    /**
     * @deprecated
     * @return array
     */
    private function getErrorTypesToProcess()
    {
        $additionalErrorTypes = array_intersect($this->getAdditionalErrorTypesToProcess(), $this->validErrorTypes);
        // array_unique so bitwise "or" operation wouldn't fail if some error type gets repeated
        return array_unique(array_merge($this->defaultErrorTypes, $additionalErrorTypes));
    }

    public function handleFatalError()
    {
        if (null === $lastError = error_get_last()) {
            return;
        }

        unset($this->reservedMemory);

        $errors = 0;
        foreach ($this->getErrorTypesToProcess() as $errorType) {
            $errors |= $errorType;
        }

        if ($lastError['type'] & $errors) {
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

    public function detectShutdown()
    {
        if (!defined('RAVEN_CLIENT_END_REACHED')) {
            define('RAVEN_CLIENT_END_REACHED', true);
        }
    }
}
