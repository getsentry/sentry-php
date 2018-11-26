<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class implements a simple error handler that catches all configured
 * error types and logs them using a certain Raven client. Registering more
 * than once this error handler is not supported and will lead to nasty problems.
 * The code is based on the Symfony Debug component.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ErrorHandler
{
    /**
     * The default amount of bytes of memory to reserve for the fatal error handler.
     */
    private const DEFAULT_RESERVED_MEMORY_SIZE = 10240;

    /**
     * @var callable Callback that will be invoked when an error is caught
     */
    private $callback;

    /**
     * @var \ReflectionProperty A reflection cached instance that points to the
     *                          trace property of the exception objects
     */
    private $exceptionReflection;

    /**
     * @var callable|null The previous error handler, if any
     */
    private $previousErrorHandler;

    /**
     * @var callable|null The previous exception handler, if any
     */
    private $previousExceptionHandler;

    /**
     * @var int The errors that will be catched by the error handler
     */
    private $capturedErrors = E_ALL;

    /**
     * @var bool Flag indicating whether this error handler is the first in the
     *           chain of registered error handlers
     */
    private $isRoot = false;

    /**
     * @var string|null A portion of pre-allocated memory data that will be reclaimed
     *                  in case a fatal error occurs to handle it
     */
    private static $reservedMemory;

    /**
     * @var array List of error levels and their description
     */
    private const ERROR_LEVELS_DESCRIPTION = [
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
        E_NOTICE => 'Notice',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_WARNING => 'Warning',
        E_USER_WARNING => 'User Warning',
        E_COMPILE_WARNING => 'Compile Warning',
        E_CORE_WARNING => 'Core Warning',
        E_USER_ERROR => 'User Error',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_PARSE => 'Parse Error',
        E_ERROR => 'Error',
        E_CORE_ERROR => 'Core Error',
    ];

    /**
     * Constructor.
     *
     * @param callable $callback           The callback that will be invoked in case an error is caught
     * @param int      $reservedMemorySize The amount of memory to reserve for the fatal error handler
     *
     * @throws \ReflectionException
     */
    private function __construct(callable $callback, int $reservedMemorySize = self::DEFAULT_RESERVED_MEMORY_SIZE)
    {
        if ($reservedMemorySize <= 0) {
            throw new \UnexpectedValueException('The $reservedMemorySize argument must be greater than 0.');
        }

        $this->callback = $callback;
        $this->exceptionReflection = new \ReflectionProperty(\Exception::class, 'trace');
        $this->exceptionReflection->setAccessible(true);

        if (null === self::$reservedMemory) {
            self::$reservedMemory = str_repeat('x', $reservedMemorySize);

            register_shutdown_function([$this, 'handleFatalError']);
        }

        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);

        if (null === $this->previousErrorHandler) {
            restore_error_handler();

            // Specifying the error types caught by the error handler with the
            // first call to the set_error_handler method would cause the PHP
            // bug https://bugs.php.net/63206 if the handler is not the first
            // one in the chain of handlers
            set_error_handler([$this, 'handleError'], $this->capturedErrors);

            $this->isRoot = true;
        }

        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
    }

    /**
     * Registers this error handler and associates the given callback to the
     * instance.
     *
     * @param callable $callback           callback that will be called when exception is caught
     * @param int      $reservedMemorySize The amount of memory to reserve for the fatal error handler
     *
     * @return self
     *
     * @throws \ReflectionException
     */
    public static function register(callable $callback, int $reservedMemorySize = self::DEFAULT_RESERVED_MEMORY_SIZE): self
    {
        return new self($callback, $reservedMemorySize);
    }

    /**
     * Sets the PHP error levels that will be captured by the Raven client when
     * a PHP error occurs.
     *
     * @param int  $levels  A bit field of E_* constants for captured errors
     * @param bool $replace Whether to replace or amend the previous value
     *
     * @return int The previous value
     */
    public function captureAt(int $levels, bool $replace = false): int
    {
        $prev = $this->capturedErrors;

        $this->capturedErrors = $levels;

        if (!$replace) {
            $this->capturedErrors |= $prev;
        }

        $this->reRegister($prev);

        return $prev;
    }

    /**
     * Handles errors by capturing them through the Raven client according to
     * the configured bit field.
     *
     * @param int    $level   The level of the error raised, represented by one
     *                        of the E_* constants
     * @param string $message The error message
     * @param string $file    The filename the error was raised in
     * @param int    $line    The line number the error was raised at
     *
     * @return bool If the function returns `false` then the PHP native error
     *              handler will be called
     *
     * @throws \Throwable
     *
     * @internal
     */
    public function handleError(int $level, string $message, string $file, int $line): bool
    {
        $shouldCaptureError = (bool) ($this->capturedErrors & $level);

        if (!$shouldCaptureError) {
            return false;
        }

        $errorAsException = new \ErrorException(self::ERROR_LEVELS_DESCRIPTION[$level] . ': ' . $message, 0, $level, $file, $line);
        $backtrace = $this->cleanBacktraceFromErrorHandlerFrames($errorAsException->getTrace(), $file, $line);

        $this->exceptionReflection->setValue($errorAsException, $backtrace);

        try {
            $this->handleException($errorAsException);
        } catch (\Throwable $exception) {
            // Do nothing as this error handler should be as transparent as possible
        }

        if (null !== $this->previousErrorHandler) {
            return false !== \call_user_func($this->previousErrorHandler, $level, $message, $file, $line);
        }

        return false;
    }

    /**
     * Handles fatal errors by capturing them through the Raven client. This
     * method is used as callback of a shutdown function.
     *
     * @param array|null $error The error details as returned by error_get_last()
     *
     * @internal
     */
    public function handleFatalError(array $error = null): void
    {
        // If there is not enough memory that can be used to handle the error
        // do nothing
        if (null === self::$reservedMemory) {
            return;
        }

        self::$reservedMemory = null;
        $errorAsException = null;

        if (null === $error) {
            $error = error_get_last();
        }

        if (!empty($error) && $error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING)) {
            $errorAsException = new \ErrorException(self::ERROR_LEVELS_DESCRIPTION[$error['type']] . ': ' . $error['message'], 0, $error['type'], $error['file'], $error['line']);
        }

        try {
            if (null !== $errorAsException) {
                $this->handleException($errorAsException);
            }
        } catch (\Throwable $errorAsException) {
            // Ignore this re-throw
        }
    }

    /**
     * Handles the given exception by capturing it through the Raven client and
     * then forwarding it to another handler.
     *
     * @param \Throwable $exception The exception to handle
     *
     * @throws \Throwable
     *
     * @internal
     */
    public function handleException(\Throwable $exception): void
    {
        \call_user_func($this->callback, $exception);

        $previousExceptionHandlerException = $exception;

        // Unset the previous exception handler to prevent infinite loop in case
        // we need to handle an exception thrown from it
        $previousExceptionHandler = $this->previousExceptionHandler;
        $this->previousExceptionHandler = null;

        try {
            if (null !== $previousExceptionHandler) {
                $previousExceptionHandler($exception);
            }
        } catch (\Throwable $previousExceptionHandlerException) {
            // Do nothing, we just need to set the $previousExceptionHandlerException
            // variable to the exception we just catched to compare it later
            // with the original object instance
        }

        // If the exception object instance is the same as the one catched from
        // the previous exception handler, if any, give it back to the native
        // PHP handler to prevent infinite circular loop
        if ($exception === $previousExceptionHandlerException) {
            // Disable the fatal error handler or the error will be reported twice
            self::$reservedMemory = null;

            throw $exception;
        }

        $this->handleException($previousExceptionHandlerException);
    }

    /**
     * Re-registers the error handler if the mask that configures the intercepted
     * error types changed.
     *
     * @param int $previousThrownErrors The previous error mask
     */
    private function reRegister(int $previousThrownErrors): void
    {
        if ($this->capturedErrors === $previousThrownErrors) {
            return;
        }

        $handler = set_error_handler('var_dump');
        $handler = \is_array($handler) ? $handler[0] : null;

        restore_error_handler();

        if ($handler === $this) {
            restore_error_handler();

            if ($this->isRoot) {
                set_error_handler([$this, 'handleError'], $this->capturedErrors);
            } else {
                set_error_handler([$this, 'handleError']);
            }
        }
    }

    /**
     * Cleans and returns the backtrace without the first frames that belong to
     * this error handler.
     *
     * @param array  $backtrace The backtrace to clear
     * @param string $file      The filename the backtrace was raised in
     * @param int    $line      The line number the backtrace was raised at
     *
     * @return array
     */
    private function cleanBacktraceFromErrorHandlerFrames(array $backtrace, string $file, int $line): array
    {
        $cleanedBacktrace = $backtrace;
        $index = 0;

        while ($index < \count($backtrace)) {
            if (isset($backtrace[$index]['file'], $backtrace[$index]['line']) && $backtrace[$index]['line'] === $line && $backtrace[$index]['file'] === $file) {
                $cleanedBacktrace = \array_slice($cleanedBacktrace, 1 + $index);

                break;
            }

            ++$index;
        }

        return $cleanedBacktrace;
    }
}
