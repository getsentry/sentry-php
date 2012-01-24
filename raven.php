<?php
/**
 * PHP Client for sentry
 * 
 * See http://github.com/dcramer/sentry and http://github.com/dcramer/raven
 *
 * Author: Michael van Tellingen
 *
 */
class RavenClient
{
    const DEBUG = 10;
    const INFO = 20;
    const WARN = 30;
    const WARNING = 30;
    const ERROR = 40;

    function __construct($servers, $public_key, $secret_key, 
                         $project=1, $site='', $auto_log_stacks=True)
    {
        $this->servers = $servers;
        $this->secret_key = $secret_key;
        $this->public_key = $public_key;
        $this->project = $project;
        $this->auto_log_stacks = $auto_log_stacks;
        $this->name = gethostname();
        $this->site = $site;
    }

    /**
     * Log a message to sentry
     */
    public function message($message, $params=array(), $level=self::INFO, 
                            $stack=false)
    {
        $data = array(
            'message' => vsprintf($message, $params),
            'level' => $level,
            'sentry.interfaces.Message' => array(
                'message' => $message,
                'params' => $params,
            )
        );
        $this->capture($data, $stack);
    }

    /**
     * Log an exception to sentry
     */
    public function exception($exception)
    {
        $exc_message = $exception->getMessage();
        if ($exc_message == '') {
            $exc_message = '<unknown exception>';
        }

        $data = array(
            'message' => $exc_message,
            'sentry.interfaces.Exception' => array(
                'value' =>  '',
                'type' => $exception->getCode(),
                'module' => $exception->getFile() .':'. $exception->getLine(),
            )
        );
        $this->capture($data, $exception->getTrace());
    }

    public function capture($data, $stack)
    {
        $event_id = $this->uuid4();

        if (!isset($data['timestamp'])) $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        if (!isset($data['level'])) $data['level'] = self::ERROR;

        $data = array_merge($data, array(
            'server_name' => $this->name,
            'event_id' => $event_id,
            'project' => $this->project,
            'site' => $this->site,
            'sentry.interfaces.Http' => array(
                'method' => $_SERVER['REQUEST_METHOD'],
                'url' => $this->get_current_url(),
                'query_string' => $_SERVER['QUERY_STRING'],
                'data' => $_POST,
                'cookies' => $_COOKIE,
                'headers' => getallheaders(),
                'env' => $_SERVER,
            )
        ));

        if ((!$stack && $this->auto_log_stacks) || $stack === True) {
            $stack = debug_backtrace();

            // Drop last stack
             array_shift($stack); 
        }

        if ($stack && !isset($data['sentry.interfaces.Stacktrace'])) {
            $data['sentry.interfaces.Stacktrace'] = array(
                'frames' => RavenStacktrace::get_stack_info($stack)
            );
        }

        $this->send($data);
    }

    public function send($data)
    {
        $message = base64_encode(gzcompress(json_encode($data)));

        foreach($this->servers as $url) {
            $timestamp = microtime(true);
            $signature = $this->get_signature(
                $message, $timestamp, $this->secret_key);

            $headers = array(
                'X-Sentry-Auth' => $this->get_auth_header(
                    $signature, $timestamp, 'raven-php/0.1', $this->public_key),
                'Content-Type' => 'application/octet-stream'
            );

            $this->send_remote($url, $message, $headers);
        }
    }

    private function send_remote($url, $data, $headers=array())
    {
        $parts = parse_url($url);
        if ($parts['scheme'] === 'udp') {
            // Not implemented yet
            return $this->send_udp(
                $parts['netloc'], $data, $headers['X-Sentry-Auth']);
        }
        return $this->send_http($url, $data, $headers);
    }

    /**
     * Send the message over http to the sentry url given
     */
    private function send_http($url, $data, $headers=array())
    {
        $new_headers = array();
        foreach($headers as $key => $value) {
            array_push($new_headers, $key .': '. $value);
        }
        $parts = parse_url($url);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $new_headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Create a signature 
     */
    private function get_signature($message, $timestamp, $key)
    {
        return hash_hmac('sha1', $timestamp .' '. $message, $key);
    }

    private function get_auth_header($signature, $timestamp, $client, 
                                     $api_key=null)
    {
        $header = array(
            "sentry_timestamp={$timestamp}",
            "sentry_signature={$signature}",
            "sentry_client={$client}",
            "sentry_version=2.0",
        );

        if ($api_key) {
            array_push($header, "sentry_key={$api_key}");
        }

        return sprintf('Sentry %s', implode(', ', $header));
    }

    /**
     * Generate an uuid4 value
     */
    private function uuid4() 
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
        return str_replace('-', '', $uuid);
    }

    /**
     * Return the URL for the current request
     */
    private function get_current_url() 
    {
        $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
            || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}


/**
 * Small helper class to inspect the stacktrace
 */
class RavenStacktrace
{
    public static function get_stack_info($stack)
    {
        $result = array();
        foreach($stack as $frame) {
            if (!isset($frame['file'])) {

                if (isset($frame['class'])) {
                    $context['line'] = sprintf('%s%s%s(%s)',
                        $frame['class'], $frame['type'], $frame['function'],
                        $frame['args']);
                }
                else {
                    $context['line'] = sprintf(
                        '%s(%s)', $frame['function'], $frame['args']);
                }
                $context['prefix'] = '';
                $context['suffix'] = '';
                $abs_path = '';
                $filename = '[Anonymous function]';
                $lineno = 0;
            }
            else {
                $context = self::read_source_file($frame['file'], $frame['line']);
                $abs_path = $frame['file'];
                $filename = basename($frame['file']);
            }

            if (isset($frame['class'])) {
                $module = basename($frame['file']) .':'. $frame['class'];
            }
            else {
                $module = '';
            }

            array_push($result, array(
                'abs_path' => $abs_path,
                'filename' => $filename,
                'lineno' => (isset($frame['line'])) ? $frame['line'] : 0,
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
        $source_lines = array(
            'prefix' => array(),
            'line' => '',
            'suffix' => array()
        );

        if ($filename === null || $lineno === null) {
            return $source_lines;
        }

        $fh = fopen($filename, 'r');
        $line = false;
        $cur_lineno = 0;

        while(!feof($fh)) {
            $cur_lineno++;
            $line = fgets($fh);

            if ($cur_lineno == $lineno) {
                $source_lines['line'] = $line;
            }
            elseif ($lineno - $cur_lineno > 0 && $lineno - $cur_lineno < 3)
            {
                $source_lines['prefix'][] = $line;
            }
            elseif ($lineno - $cur_lineno > -3 && $lineno - $cur_lineno < 0)
            {
                $source_lines['suffix'][] = $line;
            }
        }
        fclose($fh);
        return $source_lines;
    }
}
