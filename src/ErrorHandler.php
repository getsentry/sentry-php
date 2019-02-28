<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Exception\SilencedErrorException;

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
     * @var self The current registered handler (this class is a singleton)
     */
    private static $handlerInstance;

    /**
     * @var callable[] List of listeners that will act on each captured error
     */
    private $errorListeners = [];

    /**
     * @var callable[] List of listeners that will act on each captured exception
     */
    private $exceptionListeners = [];

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
     * @param int $reservedMemorySize The amount of memory to reserve for the fatal error handler
     */
    private function __construct(int $reservedMemorySize)
    {
        if ($reservedMemorySize <= 0) {
            throw new \InvalidArgumentException('The $reservedMemorySize argument must be greater than 0.');
        }

        $this->exceptionReflection = new \ReflectionProperty(\Exception::class, 'trace');
        $this->exceptionReflection->setAccessible(true);

        self::$reservedMemory = str_repeat('x', $reservedMemorySize);

        register_shutdown_function([$this, 'handleFatalError']);

        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);

        if (null === $this->previousErrorHandler) {
            restore_error_handler();

            // Specifying the error types caught by the error handler with the
            // first call to the set_error_handler method would cause the PHP
            // bug https://bugs.php.net/63206 if the handler is not the first
            // one in the chain of handlers
            set_error_handler([$this, 'handleError'], E_ALL);
        }

        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
    }

    /**
     * Gets the current registered error handler; if none is present, it will register it.
     * Subsequent calls will not change the reserved memory size.
     *
     * @param int $reservedMemorySize The requested amount of memory to reserve
     *
     * @return self The ErrorHandler singleton
     */
    public static function registerOnce(int $reservedMemorySize = self::DEFAULT_RESERVED_MEMORY_SIZE): self
    {
        if (null === self::$handlerInstance) {
            self::$handlerInstance = new self($reservedMemorySize);
        }

        return self::$handlerInstance;
    }

    /**
     * Adds a listener to the current error handler to be called upon each
     * invoked captured error; if no handler is registered, this method will
     * instantiate and register it.
     *
     * @param callable $listener A callable that will act as a listener;
     *                           this callable will receive a single
     *                           \ErrorException argument
     */
    public static function addErrorListener(callable $listener): void
    {
        $handler = self::registerOnce();
        $handler->errorListeners[] = $listener;
    }

    /**
     * Adds a listener to the current error handler to be called upon each
     * invoked captured exception; if no handler is registered, this method
     * will instantiate and register it.
     *
     * @param callable $listener A callable that will act as a listener;
     *                           this callable will receive a single
     *                           \Throwable argument
     */
    public static function addExceptionListener(callable $listener): void
    {
        $handler = self::registerOnce();
        $handler->exceptionListeners[] = $listener;
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
        if (0 === error_reporting()) {
            $errorAsException = new SilencedErrorException(self::ERROR_LEVELS_DESCRIPTION[$level] . ': ' . $message, 0, $level, $file, $line);
        } else {
            $errorAsException = new \ErrorException(self::ERROR_LEVELS_DESCRIPTION[$level] . ': ' . $message, 0, $level, $file, $line);
        }

        $backtrace = $this->cleanBacktraceFromErrorHandlerFrames($errorAsException->getTrace(), $file, $line);

        $this->exceptionReflection->setValue($errorAsException, $backtrace);

        $this->invokeListeners($this->errorListeners, $errorAsException);

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

            $this->invokeListeners($this->errorListeners, $errorAsException);
        }
    }

    /**
     * Handles the given exception by passing it to all the listeners,
     * then forwarding it to another handler.
     *
     * @param \Throwable $exception The exception to handle
     *
     * @throws \Throwable
     *
     * @internal This method is public only because it's used with set_exception_handler
     */
    public function handleException(\Throwable $exception): void
    {
        $this->invokeListeners($this->exceptionListeners, $exception);

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

    /**
     * Invokes all the listeners and pass the exception to all of them.
     *
     * @param callable[] $listeners The array of listeners to be called
     * @param \Throwable $throwable The exception to be passed onto listeners
     */
    private function invokeListeners(array $listeners, \Throwable $throwable): void
    {
        foreach ($listeners as $listener) {
            try {
                \call_user_func($listener, $throwable);
            } catch (\Throwable $exception) {
                // Do nothing as this should be as transparent as possible
            }
        }
    }
}
