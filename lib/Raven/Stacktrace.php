<?php
/**
 * Small helper class to inspect the stacktrace
 *
 * @package raven
 */
class Raven_Stacktrace
{
    public static $statements = array(
        'include',
        'include_once',
        'require',
        'require_once',
    );

    public static function get_stack_info($frames,
                                          $trace = false,
                                          $errcontext = null,
                                          $frame_var_limit = Raven_Client::MESSAGE_LIMIT,
                                          $strip_prefixes = null,
                                          $app_path = null,
                                          $excluded_app_paths = null,
                                          Raven_Serializer $serializer = null,
                                          Raven_ReprSerializer $reprSerializer = null)
    {
        $serializer = $serializer ?: new Raven_Serializer();
        $reprSerializer = $reprSerializer ?: new Raven_ReprSerializer();

        /**
         * PHP stores calls in the stacktrace, rather than executing context. Sentry
         * wants to know "when Im calling this code, where am I", and PHP says "I'm
         * calling this function" not "I'm in this function". Due to that, we shift
         * the context for a frame up one, meaning the variables (which are the calling
         * args) come from the previous frame.
         */
        $result = array();
        for ($i = 0; $i < count($frames); $i++) {
            $frame = isset($frames[$i]) ? $frames[$i] : array();
            $nextframe = isset($frames[$i + 1]) ? $frames[$i + 1] : array();

            if (!array_key_exists('file', $frame)) {
                $context = array();

                if (!empty($frame['class'])) {
                    $context['line'] = sprintf('%s%s%s', $frame['class'], $frame['type'], $frame['function']);

                    try {
                        $reflect = new ReflectionClass($frame['class']);
                        $context['filename'] = $filename = $reflect->getFileName();
                    } catch (ReflectionException $e) {
                        // Forget it if we run into errors, it's not worth it.
                    }
                } elseif (!empty($frame['function'])) {
                    $context['line'] = sprintf('%s(anonymous)', $frame['function']);
                } else {
                    $context['line'] = sprintf('(anonymous)');
                }

                if (empty($context['filename'])) {
                    $context['filename'] = $filename = '[Anonymous function]';
                }

                $abs_path = '';
                $context['prefix'] = '';
                $context['suffix'] = '';
                $context['lineno'] = 0;
            } else {
                $context = self::read_source_file($frame['file'], $frame['line']);
                $abs_path = $frame['file'];
            }

            // strip base path if present
            $context['filename'] = self::strip_prefixes($context['filename'], $strip_prefixes);
            if ($i === 0 && isset($errcontext)) {
                // If we've been given an error context that can be used as the vars for the first frame.
                $vars = $errcontext;
            } else {
                if ($trace) {
                    $vars = self::get_frame_context($nextframe, $frame_var_limit);
                } else {
                    $vars = array();
                }
            }

            $data = array(
                'filename' => $context['filename'],
                'lineno' => (int) $context['lineno'],
                'function' => isset($nextframe['function']) ? $nextframe['function'] : null,
                'pre_context' => $serializer->serialize($context['prefix']),
                'context_line' => $serializer->serialize($context['line']),
                'post_context' => $serializer->serialize($context['suffix']),
            );

            // detect in_app based on app path
            if ($app_path) {
                $norm_abs_path = @realpath($abs_path) ?: $abs_path;
                if (!$abs_path) {
                    $in_app = false;
                } else {
                    $in_app = (bool)(substr($norm_abs_path, 0, strlen($app_path)) === $app_path);
                }
                if ($in_app && $excluded_app_paths) {
                    foreach ($excluded_app_paths as $path) {
                        if (substr($norm_abs_path, 0, strlen($path)) === $path) {
                            $in_app = false;
                            break;
                        }
                    }
                }
                $data['in_app'] = $in_app;
            }

            // dont set this as an empty array as PHP will treat it as a numeric array
            // instead of a mapping which goes against the defined Sentry spec
            if (!empty($vars)) {
                $cleanVars = array();
                foreach ($vars as $key => $value) {
                    $value = $reprSerializer->serialize($value);
                    if (is_string($value) || is_numeric($value)) {
                        $cleanVars[(string)$key] = substr($value, 0, $frame_var_limit);
                    } else {
                        $cleanVars[(string)$key] = $value;
                    }
                }
                $data['vars'] = $cleanVars;
            }

            $result[] = $data;
        }

        return array_reverse($result);
    }

    public static function get_default_context($frame, $frame_arg_limit = Raven_Client::MESSAGE_LIMIT)
    {
        if (!isset($frame['args'])) {
            return array();
        }

        $i = 1;
        $args = array();
        foreach ($frame['args'] as $arg) {
            $args['param'.$i] = self::serialize_argument($arg, $frame_arg_limit);
            $i++;
        }
        return $args;
    }

    public static function get_frame_context($frame, $frame_arg_limit = Raven_Client::MESSAGE_LIMIT)
    {
        if (!isset($frame['args'])) {
            return array();
        }

        // The reflection API seems more appropriate if we associate it with the frame
        // where the function is actually called (since we're treating them as function context)
        if (!isset($frame['function'])) {
            return self::get_default_context($frame, $frame_arg_limit);
        }
        if (strpos($frame['function'], '__lambda_func') !== false) {
            return self::get_default_context($frame, $frame_arg_limit);
        }
        if (isset($frame['class']) && $frame['class'] == 'Closure') {
            return self::get_default_context($frame, $frame_arg_limit);
        }
        if (strpos($frame['function'], '{closure}') !== false) {
            return self::get_default_context($frame, $frame_arg_limit);
        }
        if (in_array($frame['function'], self::$statements)) {
            if (empty($frame['args'])) {
                // No arguments
                return array();
            } else {
                // Sanitize the file path
                return array(
                    'param1' => self::serialize_argument($frame['args'][0], $frame_arg_limit),
                );
            }
        }
        try {
            if (isset($frame['class'])) {
                if (method_exists($frame['class'], $frame['function'])) {
                    $reflection = new ReflectionMethod($frame['class'], $frame['function']);
                } elseif ($frame['type'] === '::') {
                    $reflection = new ReflectionMethod($frame['class'], '__callStatic');
                } else {
                    $reflection = new ReflectionMethod($frame['class'], '__call');
                }
            } elseif (function_exists($frame['function'])) {
                $reflection = new ReflectionFunction($frame['function']);
            } else {
                return self::get_default_context($frame, $frame_arg_limit);
            }
        } catch (ReflectionException $e) {
            return self::get_default_context($frame, $frame_arg_limit);
        }

        $params = $reflection->getParameters();

        $args = array();
        foreach ($frame['args'] as $i => $arg) {
            $arg = self::serialize_argument($arg, $frame_arg_limit);
            if (isset($params[$i])) {
                // Assign the argument by the parameter name
                $args[$params[$i]->name] = $arg;
            } else {
                $args['param'.$i] = $arg;
            }
        }

        return $args;
    }

    private static function serialize_argument($arg, $frame_arg_limit)
    {
        if (is_array($arg)) {
            $_arg = array();
            foreach ($arg as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $_arg[$key] = substr($value, 0, $frame_arg_limit);
                } else {
                    $_arg[$key] = $value;
                }
            }
            return $_arg;
        } elseif (is_string($arg) || is_numeric($arg)) {
            return substr($arg, 0, $frame_arg_limit);
        } else {
            return $arg;
        }
    }

    private static function strip_prefixes($filename, $prefixes)
    {
        if ($prefixes === null) {
            return $filename;
        }
        foreach ($prefixes as $prefix) {
            if (substr($filename, 0, strlen($prefix)) === $prefix) {
                return substr($filename, strlen($prefix));
            }
        }
        return $filename;
    }

    private static function read_source_file($filename, $lineno, $context_lines = 5)
    {
        $frame = array(
            'prefix' => array(),
            'line' => '',
            'suffix' => array(),
            'filename' => $filename,
            'lineno' => $lineno,
        );

        if ($filename === null || $lineno === null) {
            return $frame;
        }

        // Code which is eval'ed have a modified filename.. Extract the
        // correct filename + linenumber from the string.
        $matches = array();
        $matched = preg_match("/^(.*?)\\((\\d+)\\) : eval\\(\\)'d code$/",
            $filename, $matches);
        if ($matched) {
            $frame['filename'] = $filename = $matches[1];
            $frame['lineno'] = $lineno = $matches[2];
        }

        // In the case of an anonymous function, the filename is sent as:
        // "</path/to/filename>(<lineno>) : runtime-created function"
        // Extract the correct filename + linenumber from the string.
        $matches = array();
        $matched = preg_match("/^(.*?)\\((\\d+)\\) : runtime-created function$/",
            $filename, $matches);
        if ($matched) {
            $frame['filename'] = $filename = $matches[1];
            $frame['lineno'] = $lineno = $matches[2];
        }
        
        if (!file_exists($filename)) {
            return $frame;
        }

        try {
            $file = new SplFileObject($filename);
            $target = max(0, ($lineno - ($context_lines + 1)));
            $file->seek($target);
            $cur_lineno = $target+1;
            while (!$file->eof()) {
                $line = rtrim($file->current(), "\r\n");
                if ($cur_lineno == $lineno) {
                    $frame['line'] = $line;
                } elseif ($cur_lineno < $lineno) {
                    $frame['prefix'][] = $line;
                } elseif ($cur_lineno > $lineno) {
                    $frame['suffix'][] = $line;
                }
                $cur_lineno++;
                if ($cur_lineno > $lineno + $context_lines) {
                    break;
                }
                $file->next();
            }
        } catch (RuntimeException $exc) {
            return $frame;
        }

        return $frame;
    }
}
