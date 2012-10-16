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


    public static function get_stack_info($frames, $trace=false)
    {
        /**
         * PHP's way of storing backstacks seems bass-ackwards to me
         * 'function' is not the function you're in; it's any function being
         * called, so we have to shift 'function' down by 1. Ugh.
         */
        $result = array();
        for ($i = 0; $i < count($frames) - 1; $i++) {
            $frame = $frames[$i];
            $nextframe = @$frames[$i + 1];

            if (!isset($frame['file'])) {
                if (isset($frame['args'])) {
                    $args = is_string($frame['args']) ? $frame['args'] : @json_encode($frame['args']);
                }
                else {
                    $args = array();
                }
                if (!empty($nextframe['class'])) {
                    $context['line'] = sprintf('%s%s%s(%s)',
                        $nextframe['class'], $nextframe['type'], $nextframe['function'],
                        $args);
                }
                else {
                    $context['line'] = sprintf('%s(%s)', $nextframe['function'], $args);
                }
                $abs_path = '';
                $context['prefix'] = '';
                $context['suffix'] = '';
                $context['filename'] = $filename = '[Anonymous function]';
                $context['lineno'] = 0;
            }
            else {
                $context = self::read_source_file($frame['file'], $frame['line']);
                $abs_path = $frame['file'];
                $filename = basename($frame['file']);
            }

            $module = $filename;
            if (isset($nextframe['class'])) {
                $module .= ':' . $nextframe['class'];
            }

            if ($trace) {
                $vars = self::get_frame_context($nextframe);
            } else {
                $vars = array();
            }

            $result[] = array(
                'abs_path' => $abs_path,
                'filename' => $context['filename'],
                'lineno' => $context['lineno'],
                'module' => $module,
                'function' => $nextframe['function'],
                'vars' => $vars,
                'pre_context' => $context['prefix'],
                'context_line' => $context['line'],
                'post_context' => $context['suffix'],
            );
        }

        return array_reverse($result);
    }

    public static function get_frame_context($frame) {
        // The reflection API seems more appropriate if we associate it with the frame
        // where the function is actually called (since we're treating them as function context)
        if (!isset($frame['function'])) {
            return array();
        }

        if (!isset($frame['args'])) {
            return array();
        }

        if (strpos($frame['function'], '{closure}') !== false) {
            return array();
        }
        if (in_array($frame['function'], self::$statements))
        {
            if (empty($frame['args']))
            {
                // No arguments
                return array();
            }
            else
            {
                // Sanitize the file path
                return array($frame['args'][0]);
            }
        }
        if (isset($frame['class'])) {
            if (method_exists($frame['class'], $frame['function'])) {
                $reflection = new ReflectionMethod($frame['class'], $frame['function']);
            }
            else
            {
                $reflection = new ReflectionMethod($frame['class'], '__call');
            }
        }
        else
        {
            $reflection = new ReflectionFunction($frame['function']);
        }

        $params = $reflection->getParameters();

        $args = array();
        foreach ($frame['args'] as $i => $arg)
        {
            if (isset($params[$i]))
            {
                // Assign the argument by the parameter name
                $args[$params[$i]->name] = $arg;
            }
            else
            {
                // TODO: Sentry thinks of these as context locals, so they must be named
                // Assign the argument by number
                // $args[$i] = $arg;
            }
        }

        return $args;
    }

    private static function read_source_file($filename, $lineno, $context_lines=5)
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


        // Try to open the file. We wrap this in a try/catch block in case
        // someone has modified the error_trigger to throw exceptions.
        try {
            $fh = fopen($filename, 'r');
            if ($fh === false) {
                return $frame;
            }
        }
        catch (ErrorException $exc) {
            return $frame;
        }

        $line = false;
        $cur_lineno = 0;

        while(!feof($fh)) {
            $cur_lineno++;
            $line = fgets($fh);

            if ($cur_lineno == $lineno) {
                $frame['line'] = $line;
            }
            elseif ($lineno - $cur_lineno > 0 && $lineno - $cur_lineno <= ($context_lines + 1))
            {
                $frame['prefix'][] = $line;
            }
            elseif ($lineno - $cur_lineno >= -$context_lines && $lineno - $cur_lineno < 0)
            {
                $frame['suffix'][] = $line;
            }
        }
        fclose($fh);
        return $frame;
    }
}
