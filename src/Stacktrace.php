<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

/**
 * This class contains all the information about an error stacktrace.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Stacktrace implements \JsonSerializable
{
    /**
     * This constant defines the default number of lines of code to include.
     */
    const CONTEXT_NUM_LINES = 5;

    /**
     * @var Options The client options
     */
    protected $options;

    /**
     * @var Serializer The serializer
     */
    protected $serializer;

    /**
     * @var Serializer The representation serializer
     */
    protected $representationSerializer;

    /**
     * @var Frame[] The frames that compose the stacktrace
     */
    protected $frames = [];

    /**
     * @var string[] The list of functions to import a file
     */
    protected static $importStatements = [
        'include',
        'include_once',
        'require',
        'require_once',
    ];

    /**
     * Constructor.
     *
     * @param Options $options The client options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->serializer = new Serializer($this->options->getMbDetectOrder());
        $this->representationSerializer = new ReprSerializer($this->options->getMbDetectOrder());


        if ($this->options->getSerializeAllObjects()) {
            $this->serializer->setAllObjectSerialize($this->options->getSerializeAllObjects());
            $this->representationSerializer->setAllObjectSerialize($this->options->getSerializeAllObjects());
        }
    }

    /**
     * Creates a new instance of this class using the current backtrace data.
     *
     * @param Options $options The client options
     *
     * @return static
     */
    public static function create(Options $options)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        return static::createFromBacktrace($options, $backtrace, __FILE__, __LINE__);
    }

    /**
     * Creates a new instance of this class from the given backtrace.
     *
     * @param Options $options    The client options
     * @param array           $backtrace The backtrace
     * @param string          $file      The file that originated the backtrace
     * @param int             $line      The line at which the backtrace originated
     *
     * @return static
     */
    public static function createFromBacktrace(Options $options, array $backtrace, $file, $line)
    {
        $stacktrace = new static($options);

        foreach ($backtrace as $frame) {
            $stacktrace->addFrame($file, $line, $frame);

            $file = isset($frame['file']) ? $frame['file'] : '[internal]';
            $line = isset($frame['line']) ? $frame['line'] : 0;
        }

        // Add a final stackframe for the first method ever of this stacktrace
        $stacktrace->addFrame($file, $line, []);

        return $stacktrace;
    }

    /**
     * Gets the stacktrace frames.
     *
     * @return Frame[]
     */
    public function getFrames()
    {
        return $this->frames;
    }

    /**
     * Adds a new frame to the stacktrace.
     *
     * @param string $file           The file where the frame originated
     * @param int    $line           The line at which the frame originated
     * @param array  $backtraceFrame The data of the frame to add
     */
    public function addFrame($file, $line, array $backtraceFrame)
    {
        // The $file argument can be any of these formats:
        // </path/to/filename>
        // </path/to/filename>(<line number>) : eval()'d code
        // </path/to/filename>(<line number>) : runtime-created function
        if (preg_match('/^(.*)\((\d+)\) : (?:eval\(\)\'d code|runtime-created function)$/', $file, $matches)) {
            $file = $matches[1];
            $line = $matches[2];
        }

        if (isset($backtraceFrame['class'])) {
            $functionName = sprintf('%s::%s', $backtraceFrame['class'], $backtraceFrame['function']);
        } elseif (isset($backtraceFrame['function'])) {
            $functionName = $backtraceFrame['function'];
        } else {
            $functionName = null;
        }

        $frame = new Frame($functionName, $this->stripPrefixFromFilePath($file), (int) $line);
        $sourceCodeExcerpt = self::getSourceCodeExcerpt($file, $line, self::CONTEXT_NUM_LINES);

        if (isset($sourceCodeExcerpt['pre_context'])) {
            $frame->setPreContext($sourceCodeExcerpt['pre_context']);
        }

        if (isset($sourceCodeExcerpt['context_line'])) {
            $frame->setContextLine($sourceCodeExcerpt['context_line']);
        }

        if (isset($sourceCodeExcerpt['post_context'])) {
            $frame->setPostContext($sourceCodeExcerpt['post_context']);
        }

        if (null !== $this->options->getProjectRoot()) {
            $excludedAppPaths = $this->options->getExcludedProjectPaths();
            $absoluteFilePath = @realpath($file) ?: $file;
            $isApplicationFile = 0 === strpos($absoluteFilePath, $this->options->getProjectRoot());

            if ($isApplicationFile && !empty($excludedAppPaths)) {
                foreach ($excludedAppPaths as $path) {
                    if (0 === strpos($absoluteFilePath, $path)) {
                        $frame->setIsInApp($isApplicationFile);

                        break;
                    }
                }
            }
        }

        $frameArguments = self::getFrameArguments($backtraceFrame);

        if (!empty($frameArguments)) {
            foreach ($frameArguments as $argumentName => $argumentValue) {
                $argumentValue = $this->representationSerializer->serialize($argumentValue);

                if (\is_string($argumentValue) || is_numeric($argumentValue)) {
                    $frameArguments[(string) $argumentName] = substr((string) $argumentValue, 0, Client::MESSAGE_MAX_LENGTH_LIMIT);
                } else {
                    $frameArguments[(string) $argumentName] = $argumentValue;
                }
            }

            $frame->setVars($frameArguments);
        }

        array_unshift($this->frames, $frame);
    }

    /**
     * Removes the frame at the given index from the stacktrace.
     *
     * @param int $index The index of the frame
     *
     * @throws \OutOfBoundsException If the index is out of range
     */
    public function removeFrame($index)
    {
        if (!isset($this->frames[$index])) {
            throw new \OutOfBoundsException('Invalid frame index to remove.');
        }

        array_splice($this->frames, $index, 1);
    }

    /**
     * Gets the stacktrace frames (this is the same as calling the getFrames
     * method).
     *
     * @return Frame[]
     */
    public function toArray()
    {
        return $this->frames;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Gets an excerpt of the source code around a given line.
     *
     * @param string $path       The file path
     * @param int    $lineNumber The line to centre about
     * @param int    $linesNum   The number of lines to fetch
     *
     * @return array
     */
    protected function getSourceCodeExcerpt($path, $lineNumber, $linesNum)
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $frame = [
            'pre_context' => [],
            'context_line' => '',
            'post_context' => [],
        ];

        $target = max(0, ($lineNumber - ($linesNum + 1)));
        $currentLineNumber = $target + 1;

        try {
            $file = new \SplFileObject($path);
            $file->seek($target);

            while (!$file->eof()) {
                /** @var string $row */
                $row = $file->current();
                $line = rtrim($row, "\r\n");

                if ($currentLineNumber == $lineNumber) {
                    $frame['context_line'] = $line;
                } elseif ($currentLineNumber < $lineNumber) {
                    $frame['pre_context'][] = $line;
                } elseif ($currentLineNumber > $lineNumber) {
                    $frame['post_context'][] = $line;
                }

                ++$currentLineNumber;

                if ($currentLineNumber > $lineNumber + $linesNum) {
                    break;
                }

                $file->next();
            }
            // @codeCoverageIgnoreStart
        } catch (\Exception $ex) {
        }
        // @codeCoverageIgnoreEnd

        $frame['pre_context'] = $this->serializer->serialize($frame['pre_context']);
        $frame['context_line'] = $this->serializer->serialize($frame['context_line']);
        $frame['post_context'] = $this->serializer->serialize($frame['post_context']);

        return $frame;
    }

    /**
     * Removes from the given file path the specified prefixes.
     *
     * @param string $filePath The path to the file
     *
     * @return string
     */
    protected function stripPrefixFromFilePath($filePath)
    {
        foreach ($this->options->getPrefixes() as $prefix) {
            if (0 === strpos($filePath, $prefix)) {
                return substr($filePath, \strlen($prefix));
            }
        }

        return $filePath;
    }

    /**
     * Gets the values of the arguments of the given stackframe.
     *
     * @param array $frame          The frame from where arguments are retrieved
     * @param int   $maxValueLength The maximum string length to get from the arguments values
     *
     * @return array
     */
    protected static function getFrameArgumentsValues($frame, $maxValueLength = Client::MESSAGE_MAX_LENGTH_LIMIT)
    {
        if (!isset($frame['args'])) {
            return [];
        }

        $result = [];

        for ($i = 0; $i < \count($frame['args']); ++$i) {
            $result['param' . ($i + 1)] = self::serializeArgument($frame['args'][$i], $maxValueLength);
        }

        return $result;
    }

    /**
     * Gets the arguments of the given stackframe.
     *
     * @param array $frame          The frame from where arguments are retrieved
     * @param int   $maxValueLength The maximum string length to get from the arguments values
     *
     * @return array
     */
    public static function getFrameArguments($frame, $maxValueLength = Client::MESSAGE_MAX_LENGTH_LIMIT)
    {
        if (!isset($frame['args'])) {
            return [];
        }

        // The Reflection API seems more appropriate if we associate it with the frame
        // where the function is actually called (since we're treating them as function context)
        if (!isset($frame['function'])) {
            return self::getFrameArgumentsValues($frame, $maxValueLength);
        }

        if (false !== strpos($frame['function'], '__lambda_func')) {
            return self::getFrameArgumentsValues($frame, $maxValueLength);
        }

        if (false !== strpos($frame['function'], '{closure}')) {
            return self::getFrameArgumentsValues($frame, $maxValueLength);
        }

        if (isset($frame['class']) && 'Closure' === $frame['class']) {
            return self::getFrameArgumentsValues($frame, $maxValueLength);
        }

        if (\in_array($frame['function'], static::$importStatements, true)) {
            if (empty($frame['args'])) {
                return [];
            }

            return [
                'param1' => self::serializeArgument($frame['args'][0], $maxValueLength),
            ];
        }

        try {
            if (isset($frame['class'])) {
                if (method_exists($frame['class'], $frame['function'])) {
                    $reflection = new \ReflectionMethod($frame['class'], $frame['function']);
                } elseif ('::' === $frame['type']) {
                    $reflection = new \ReflectionMethod($frame['class'], '__callStatic');
                } else {
                    $reflection = new \ReflectionMethod($frame['class'], '__call');
                }
            } elseif (\function_exists($frame['function'])) {
                $reflection = new \ReflectionFunction($frame['function']);
            } else {
                return self::getFrameArgumentsValues($frame, $maxValueLength);
            }
        } catch (\ReflectionException $ex) {
            return self::getFrameArgumentsValues($frame, $maxValueLength);
        }

        $params = $reflection->getParameters();
        $args = [];

        foreach ($frame['args'] as $index => $arg) {
            $arg = self::serializeArgument($arg, $maxValueLength);

            if (isset($params[$index])) {
                // Assign the argument by the parameter name
                $args[$params[$index]->name] = $arg;
            } else {
                $args['param' . $index] = $arg;
            }
        }

        return $args;
    }

    protected static function serializeArgument($arg, $maxValueLength)
    {
        if (\is_array($arg)) {
            $result = [];

            foreach ($arg as $key => $value) {
                if (\is_string($value) || is_numeric($value)) {
                    $result[$key] = substr((string) $value, 0, $maxValueLength);
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        } elseif (\is_string($arg) || is_numeric($arg)) {
            return substr((string) $arg, 0, $maxValueLength);
        } else {
            return $arg;
        }
    }
}
