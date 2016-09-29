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
            $frame = isset($frames[$i]) ? $frames[$i] : null;
            $nextframe = isset($frames[$i + 1]) ? $frames[$i + 1] : null;

            if (!array_key_exists('file', $frame)) {
                if (!empty($frame['class'])) {
                    $context['line'] = sprintf('%s%s%s',
                        $frame['class'], $frame['type'], $frame['function']);
                } else {
                    $context['line'] = sprintf('%s(anonymous)', $frame['function']);
                }
                $abs_path = '';
                $context['prefix'] = '';
                $context['suffix'] = '';
                $context['filename'] = $filename = '[Anonymous function]';
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
                $in_app = (bool)(substr($abs_path, 0, strlen($app_path)) === $app_path);
                if ($in_app && $excluded_app_paths) {
                    foreach ($excluded_app_paths as $path) {
                        if (substr($abs_path, 0, strlen($path)) === $path) {
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
            if (is_string($arg) || is_numeric($arg)) {
                $arg = substr($arg, 0, $frame_arg_limit);
            }
            $args['param'.$i] = $arg;
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
                return array('param1' => $frame['args'][0]);
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
            } else {
                $reflection = new ReflectionFunction($frame['function']);
            }
        } catch (ReflectionException $e) {
            return self::get_default_context($frame, $frame_arg_limit);
        }

        $params = $reflection->getParameters();

        $args = array();
        foreach ($frame['args'] as $i => $arg) {
            if (isset($params[$i])) {
                // Assign the argument by the parameter name
                if (is_array($arg)) {
                    foreach ($arg as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            $arg[$key] = substr($value, 0, $frame_arg_limit);
                        }
                    }
                }
                $args[$params[$i]->name] = $arg;
            } else {
                $args['param'.$i] = $arg;
            }
        }

        return $args;
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
        $matched = preg_match("/^(.*?)\((\d+)\) : eval\(\)'d code$/",
            $filename, $matches);
        if ($matched) {
            $frame['filename'] = $filename = $matches[1];
            $frame['lineno'] = $lineno = $matches[2];
        }

        // In the case of an anonymous function, the filename is sent as:
        // "</path/to/filename>(<lineno>) : runtime-created function"
        // Extract the correct filename + linenumber from the string.
        $matches = array();
        $matched = preg_match("/^(.*?)\((\d+)\) : runtime-created function$/",
            $filename, $matches);
        if ($matched) {
            $frame['filename'] = $filename = $matches[1];
            $frame['lineno'] = $lineno = $matches[2];
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
