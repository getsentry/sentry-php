<?php
/**
 * Small helper class to inspect the stacktrace
 *
 * @package raven
 */
class Raven_Stacktrace
{
    public static function get_stack_info($stack)
    {
        $result = array();
        foreach($stack as $frame) {
            if (!isset($frame['file'])) {
                if (isset($frame['args'])) {
                    $args = is_string($frame['args']) ? $frame['args'] : @json_encode($frame['args']);
                }
                else {
                    $args = array();
                }
                if (isset($frame['class'])) {
                    $context['line'] = sprintf('%s%s%s(%s)',
                        $frame['class'], $frame['type'], $frame['function'],
                        $args);
                }
                else {
                    $context['line'] = sprintf('%s(%s)', $frame['function'], $args);
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

            array_push($result, array(
                'abs_path' => $abs_path,
                'filename' => $context['filename'],
                'lineno' => $context['lineno'],
                'module' => $module,
                'function' => $frame['function'],
                'vars' => array(),
                'pre_context' => $context['prefix'],
                'context_line' => $context['line'],
                'post_context' => $context['suffix'],

            ));
        }
        return array_reverse($result);
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
