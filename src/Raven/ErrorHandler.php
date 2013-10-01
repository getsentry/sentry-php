<?php

namespace Raven;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * Code from the original raven-php client
 */
class ErrorHandler
{
    private $client;

    private $callExistingExceptionHandler;
    private $previousExceptionHandler;

    private $callExistingErrorHandler;
    private $previousErrorHandler;
    private $errorTypes;

    private $reservedMemory;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function registerExceptionHandler($callExistingExceptionHandler = true)
    {
        $this->previousExceptionHandler = set_exception_handler(array($this, 'handleException'));
        $this->callExistingExceptionHandler = $callExistingExceptionHandler;
    }

    public function registerErrorHandler($callExistingErrorHandler = true, $errorTypes = -1)
    {
        $this->errorTypes = $errorTypes;
        $this->previousErrorHandler = set_error_handler(array($this, 'handleError'));
        $this->callExistingErrorHandler = $callExistingErrorHandler;
    }

    public function registerShutdownFunction($reservedMemorySize = 10)
    {
        register_shutdown_function(array($this, 'handleFatalError'));

        $this->reservedMemory = str_repeat('x', 1024 * $reservedMemorySize);
    }

    public function handleException($e)
    {
        $this->client->captureException($e);

        if ($this->callExistingExceptionHandler && null !== $this->previousExceptionHandler) {
            call_user_func($this->previousExceptionHandler, $e);
        }
    }

    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        if ($this->errorTypes & $code & error_reporting()) {
            $e = new \ErrorException($message, 0, $code, $file, $line);
            $this->client->captureException($e);
        }

        if ($this->callExistingErrorHandler && $this->previousErrorHandler) {
            call_user_func($this->previousErrorHandler, $code, $message, $file, $line, $context);
        }
    }

    public function handleFatalError()
    {
        if (null === $lastError = error_get_last()) {
            return;
        }

        unset($this->reservedMemory);

        $e = new \ErrorException(
            @$lastError['message'],
            @$lastError['type'],
            @$lastError['type'],
            @$lastError['file'],
            @$lastError['line']
        );

        $this->client->captureException($e);
    }
}
