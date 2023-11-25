<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Util\PrefixStripper;

/**
 * This class builds a {@see Frame} object out of a backtrace's raw frame.
 *
 * @internal
 *
 * @psalm-type StacktraceFrame array{
 *     function?: string,
 *     line?: int,
 *     file?: string,
 *     class?: class-string,
 *     type?: string,
 *     args?: mixed[]
 * }
 */
final class FrameBuilder
{
    use PrefixStripper;

    /**
     * @var Options The SDK client options
     */
    private $options;

    /**
     * @var RepresentationSerializerInterface The representation serializer
     */
    private $representationSerializer;

    /**
     * @var string The default name for function parameters when we cannot get
     *             them from function definition
     */
    private const DEFAULT_PARAMETER_NAME = 'param';

    /**
     * Constructor.
     *
     * @param Options                           $options                  The SDK client options
     * @param RepresentationSerializerInterface $representationSerializer The representation serializer
     */
    public function __construct(Options $options, RepresentationSerializerInterface $representationSerializer)
    {
        $this->options = $options;
        $this->representationSerializer = $representationSerializer;
    }

    /**
     * Builds a {@see Frame} object from the given backtrace's raw frame.
     *
     * @param string               $file           The file where the frame originated
     * @param int                  $line           The line at which the frame originated
     * @param array<string, mixed> $backtraceFrame The raw frame
     *
     * @psalm-param StacktraceFrame $backtraceFrame
     */
    public function buildFromBacktraceFrame(string $file, int $line, array $backtraceFrame): Frame
    {
        // The filename can be in any of these formats:
        //   - </path/to/filename>
        //   - </path/to/filename>(<line number>) : eval()'d code
        //   - </path/to/filename>(<line number>) : runtime-created function
        if (preg_match('/^(.*)\((\d+)\) : (?:eval\(\)\'d code|runtime-created function)$/', $file, $matches)) {
            $file = $matches[1];
            $line = (int) $matches[2];
        }

        $functionName = null;
        $rawFunctionName = null;
        $strippedFilePath = $this->stripPrefixFromFilePath($this->options, $file);

        if (isset($backtraceFrame['class']) && isset($backtraceFrame['function'])) {
            $functionName = $backtraceFrame['class'];

            if (mb_substr($functionName, 0, mb_strlen(Frame::ANONYMOUS_CLASS_PREFIX)) === Frame::ANONYMOUS_CLASS_PREFIX) {
                $functionName = Frame::ANONYMOUS_CLASS_PREFIX . $this->stripPrefixFromFilePath($this->options, substr($backtraceFrame['class'], \strlen(Frame::ANONYMOUS_CLASS_PREFIX)));
            }

            $rawFunctionName = sprintf('%s::%s', $backtraceFrame['class'], $backtraceFrame['function']);
            $functionName = sprintf('%s::%s', preg_replace('/(?::\d+\$|0x)[a-fA-F0-9]+$/', '', $functionName), $backtraceFrame['function']);
        } elseif (isset($backtraceFrame['function'])) {
            $functionName = $backtraceFrame['function'];
        }

        return new Frame(
            $functionName,
            $strippedFilePath,
            $line,
            $rawFunctionName,
            $file !== Frame::INTERNAL_FRAME_FILENAME ? $file : null,
            $this->getFunctionArguments($backtraceFrame),
            $this->isFrameInApp($file, $functionName)
        );
    }

    /**
     * Checks whether a certain frame should be marked as "in app" or not.
     *
     * @param string      $file         The file to check
     * @param string|null $functionName The name of the function
     */
    private function isFrameInApp(string $file, ?string $functionName): bool
    {
        if ($file === Frame::INTERNAL_FRAME_FILENAME) {
            return false;
        }

        if ($functionName !== null && substr($functionName, 0, \strlen('Sentry\\')) === 'Sentry\\') {
            return false;
        }

        $excludedAppPaths = $this->options->getInAppExcludedPaths();
        $includedAppPaths = $this->options->getInAppIncludedPaths();
        $absoluteFilePath = @realpath($file) ?: $file;
        $isInApp = true;

        foreach ($excludedAppPaths as $excludedAppPath) {
            if (mb_substr($absoluteFilePath, 0, mb_strlen($excludedAppPath)) === $excludedAppPath) {
                $isInApp = false;

                break;
            }
        }

        foreach ($includedAppPaths as $includedAppPath) {
            if (mb_substr($absoluteFilePath, 0, mb_strlen($includedAppPath)) === $includedAppPath) {
                $isInApp = true;

                break;
            }
        }

        return $isInApp;
    }

    /**
     * Gets the arguments of the function called in the given frame.
     *
     * @param array<string, mixed> $backtraceFrame The frame data
     *
     * @return array<string, mixed>
     *
     * @psalm-param StacktraceFrame $backtraceFrame
     */
    private function getFunctionArguments(array $backtraceFrame): array
    {
        if (!isset($backtraceFrame['function'], $backtraceFrame['args'])) {
            return [];
        }

        $reflectionFunction = null;

        try {
            if (isset($backtraceFrame['class'])) {
                if (method_exists($backtraceFrame['class'], $backtraceFrame['function'])) {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], $backtraceFrame['function']);
                } elseif (isset($backtraceFrame['type']) && $backtraceFrame['type'] === '::') {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], '__callStatic');
                } else {
                    $reflectionFunction = new \ReflectionMethod($backtraceFrame['class'], '__call');
                }
            } elseif (!\in_array($backtraceFrame['function'], ['{closure}', '__lambda_func'], true) && \function_exists($backtraceFrame['function'])) {
                $reflectionFunction = new \ReflectionFunction($backtraceFrame['function']);
            }
        } catch (\ReflectionException $e) {
            // Reflection failed, we do nothing instead
        }

        $argumentValues = [];

        if ($reflectionFunction !== null) {
            $argumentValues = $this->getFunctionArgumentValues($reflectionFunction, $backtraceFrame['args']);
        } else {
            foreach ($backtraceFrame['args'] as $parameterPosition => $parameterValue) {
                $argumentValues[self::DEFAULT_PARAMETER_NAME . $parameterPosition] = $parameterValue;
            }
        }

        foreach ($argumentValues as $argumentName => $argumentValue) {
            $argumentValues[$argumentName] = $this->representationSerializer->representationSerialize($argumentValue);
        }

        return $argumentValues;
    }

    /**
     * Gets an hashmap indexed by argument name containing all the arguments
     * passed to the function called in the given frame of the stacktrace.
     *
     * @param \ReflectionFunctionAbstract $reflectionFunction A reflection object
     * @param mixed[]                     $backtraceFrameArgs The arguments of the frame
     *
     * @return array<string, mixed>
     */
    private function getFunctionArgumentValues(\ReflectionFunctionAbstract $reflectionFunction, array $backtraceFrameArgs): array
    {
        $argumentValues = [];

        // $backtraceFrameArgs can contain string keys if function has
        // variadic parameters and we passed them by name. Because of this,
        // we cannot use indexes to access our values.
        // Using foreach here will complicate logic for handling variadic and
        // undeclared arguments, so we have to use iterator.
        reset($backtraceFrameArgs);

        $variadicParameter = null;
        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            // We want to handle variadic parameters separately,
            // so we remember it and break out of the loop.
            if ($reflectionParameter->isVariadic()) {
                $variadicParameter = $reflectionParameter;
                break;
            }

            if (key($backtraceFrameArgs) !== null) {
                $value = current($backtraceFrameArgs);
                next($backtraceFrameArgs);
            } elseif ($reflectionParameter->isDefaultValueAvailable()) {
                $value = $reflectionParameter->getDefaultValue();
            } else {
                // In theory, we will only end up here if not enough arguments
                // were passed to the function (ArgumentCountError).
                // In this case we can break out of the loop and let next
                // condition handle returning result.
                break;
            }

            $argumentValues[$reflectionParameter->getName()] = $value;
        }

        // Return if we reached the end of supplied args, so we don't
        // waste time handling things, which aren't there.
        if (key($backtraceFrameArgs) === null) {
            return $argumentValues;
        }

        if ($variadicParameter !== null) {
            // If we have variadic parameters, we will write them into an
            // array, since this is how they appear inside of function they
            // were passed to.
            $variadicArgs = [];
            while (key($backtraceFrameArgs) !== null) {
                $variadicArgs[key($backtraceFrameArgs)] = current($backtraceFrameArgs);
                next($backtraceFrameArgs);
            }
            $argumentValues[$variadicParameter->getName()] = $variadicArgs;
        } else {
            // If we don't have variadic parameter, but still have additional
            // arguments, it means those arguments weren't declared in a function.
            // We will name them the same way it's done when we can't create
            // function reflection.
            while (key($backtraceFrameArgs) !== null) {
                $argumentKey = self::DEFAULT_PARAMETER_NAME . key($backtraceFrameArgs);
                $argumentValues[$argumentKey] = current($backtraceFrameArgs);
                next($backtraceFrameArgs);
            }
        }

        return $argumentValues;
    }
}
