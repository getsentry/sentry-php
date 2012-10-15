<?php
/**
 * Small helper class to inspect the stacktrace
 *
 * @package raven
 */
class Raven_Stacktrace
{
    public static function get_stack_info($frames, $trace=false)
    {
        /**
         * PHP's way of storing backstacks seems bass-ackwards to me
         * 'function' is not the function you're in; it's any function being
         * called, so we have to shift 'function' down by 1. Ugh.
         */
        $result = array();
        for ($i = 0; $i < count($frames) - 1; $i++) {
            var_dump('wat');
            $frame = $frames[$i];
            $nextframe = $frames[$i];

            if (!isset($frame['file'])) {
                if (isset($frame['args'])) {
                    $args = is_string($frame['args']) ? $frame['args'] : @json_encode($frame['args']);
                }
                else {
                    $args = array();
                }
                if (isset($frame['class'])) {
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
            if (isset($frame['class'])) {
                $module .= ':' . $frame['class'];
            }

            if ($trace) {
                $vars = self::get_frame_context($frame);
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

        var_dump($result);
        return array_reverse($result);
    }

    public static function get_frame_context($frame) {
        if (!isset($frame['function'])) {
            return array();
        }

        if (!isset($frame['args'])) {
            return array();
        }

        if (strpos($frame['function'], '{closure}') !== false) {
            return array();
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
                $args[$params[$i]->name] = (string)$arg;
            }
            else
            {
                // Assign the argument by number
                $args[(string)$i] = (string)$arg;
            }
        }

        return $args;
    }

    private static function read_source_file($filename, $lineno)
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
            elseif ($lineno - $cur_lineno > 0 && $lineno - $cur_lineno < 3)
            {
                $frame['prefix'][] = $line;
            }
            elseif ($lineno - $cur_lineno > -3 && $lineno - $cur_lineno < 0)
            {
                $frame['suffix'][] = $line;
            }
        }
        fclose($fh);
        return $frame;
    }
}
