<?php


/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Raven PHP Client
 *
 * @package raven
 */

class Raven_Client
{
    const VERSION = '0.5.1';

    const DEBUG = 'debug';
    const INFO = 'info';
    const WARN = 'warning';
    const WARNING = 'warning';
    const ERROR = 'error';
    const FATAL = 'fatal';

    var $severity_map;

    var $store_errors_for_bulk_send = false;

    public function __construct($options_or_dsn=null, $options=array())
    {
        if (is_null($options_or_dsn) && !empty($_SERVER['SENTRY_DSN'])) {
            // Read from environment
            $options_or_dsn = $_SERVER['SENTRY_DSN'];
        }
        if (!is_array($options_or_dsn)) {
            if (!empty($options_or_dsn)) {
                // Must be a valid DSN
                $options_or_dsn = self::parseDSN($options_or_dsn);
            } else {
                $options_or_dsn = array();
            }
        }
        $options = array_merge($options_or_dsn, $options);

        $this->logger = Raven_Util::get($options, 'logger', 'php');
        $this->servers = Raven_Util::get($options, 'servers');
        $this->secret_key = Raven_Util::get($options, 'secret_key');
        $this->public_key = Raven_Util::get($options, 'public_key');
        $this->project = Raven_Util::get($options, 'project', 1);
        $this->auto_log_stacks = (bool) Raven_Util::get($options, 'auto_log_stacks', false);
        $this->name = Raven_Util::get($options, 'name', Raven_Compat::gethostname());
        $this->site = Raven_Util::get($options, 'site', $this->_server_variable('SERVER_NAME'));
        $this->tags = Raven_Util::get($options, 'tags', array());
        $this->trace = (bool) Raven_Util::get($options, 'trace', true);
        $this->timeout = Raven_Util::get($options, 'timeout', 5);
        $this->exclude = Raven_Util::get($options, 'exclude', array());
        $this->severity_map = NULL;
        $this->shift_vars = (bool) Raven_Util::get($options, 'shift_vars', true);

        // XXX: Signing is disabled by default as it is no longer required by modern versions of Sentrys
        $this->signing = (bool) Raven_Util::get($options, 'signing', false);

        $this->processors = array();
        foreach (Raven_util::get($options, 'processors', $this->getDefaultProcessors()) as $processor) {
            $this->processors[] = new $processor($this);
        }

        $this->_lasterror = null;
    }

    public static function getDefaultProcessors()
    {
        return array(
            'Raven_SanitizeDataProcessor',
        );
    }

    /**
     * Parses a Raven-compatible DSN and returns an array of its values.
     */
    public static function parseDSN($dsn)
    {
        $url = parse_url($dsn);
        $scheme = (isset($url['scheme']) ? $url['scheme'] : '');
        if (!in_array($scheme, array('http', 'https', 'udp'))) {
            throw new InvalidArgumentException('Unsupported Sentry DSN scheme: ' . (!empty($scheme) ? $scheme : '<not set>'));
        }
        $netloc = (isset($url['host']) ? $url['host'] : null);
        $netloc.= (isset($url['port']) ? ':'.$url['port'] : null);
        $rawpath = (isset($url['path']) ? $url['path'] : null);
        if ($rawpath) {
            $pos = strrpos($rawpath, '/', 1);
            if ($pos !== false) {
                $path = substr($rawpath, 0, $pos);
                $project = substr($rawpath, $pos + 1);
            } else {
                $path = '';
                $project = substr($rawpath, 1);
            }
        } else {
            $project = null;
            $path = '';
        }
        $username = (isset($url['user']) ? $url['user'] : null);
        $password = (isset($url['pass']) ? $url['pass'] : null);
        if (empty($netloc) || empty($project) || empty($username) || empty($password)) {
            throw new InvalidArgumentException('Invalid Sentry DSN: ' . $dsn);
        }

        return array(
            'servers'    => array(sprintf('%s://%s%s/api/store/', $scheme, $netloc, $path)),
            'project'    => $project,
            'public_key' => $username,
            'secret_key' => $password,
        );
    }

    public function getLastError()
    {
        return $this->_lasterror;
    }

    /**
     * Given an identifier, returns a Sentry searchable string.
     */
    public function getIdent($ident)
    {
        // XXX: We dont calculate checksums yet, so we only have the ident.
        return $ident;
    }

    /**
     * Deprecated
     */
    public function message($message, $params=array(), $level=self::INFO,
                            $stack=false, $vars = null)
    {
        return $this->captureMessage($message, $params, $level, $stack, $vars);
    }

    /**
     * Deprecated
     */
    public function exception($exception)
    {
        return $this->captureException($exception);
    }

    /**
     * Log a message to sentry
     */
    public function captureMessage($message, $params=array(), $level_or_options=array(),
                            $stack=false, $vars = null)
    {
        // Gracefully handle messages which contain formatting characters, but were not
        // intended to be used with formatting.
        if (!empty($params)) {
            $formatted_message = vsprintf($message, $params);
        } else {
            $formatted_message = $message;
        }

        if ($level_or_options === null) {
            $data = array();
        } else if (!is_array($level_or_options)) {
            $data = array(
                'level' => $level_or_options,
            );
        } else {
            $data = $level_or_options;
        }

        $data['message'] = $formatted_message;
        $data['sentry.interfaces.Message'] = array(
            'message' => $message,
            'params' => $params,
        );

        return $this->capture($data, $stack, $vars);
    }

    /**
     * Log an exception to sentry
     */
    public function captureException($exception, $culprit_or_options=null, $logger=null, $vars=null)
    {
        if (in_array(get_class($exception), $this->exclude)) {
            return null;
        }

        $exc_message = $exception->getMessage();
        if (empty($exc_message)) {
            $exc_message = '<unknown exception>';
        }

        if (!is_array($culprit_or_options)) {
            $data = array();
            if ($culprit_or_options !== null) {
                $data['culprit'] = $culprit_or_options;
            }
        } else {
            $data = $culprit_or_options;
        }

        $data['message'] = $exc_message;
        $data['sentry.interfaces.Exception'] = array(
            'value' => $exc_message,
            'type' => get_class($exception),
            'module' => $exception->getFile() .':'. $exception->getLine(),
        );

        if ($logger !== null) {
            $data['logger'] = $logger;
        }

        if (empty($data['level'])) {
            if (method_exists($exception, 'getSeverity')) {
                $data['level'] = $this->translateSeverity($exception->getSeverity());
            } else {
                $data['level'] = self::ERROR;
            }
        }

        /**'sentry.interfaces.Exception'
         * Exception::getTrace doesn't store the point at where the exception
         * was thrown, so we have to stuff it in ourselves. Ugh.
         */
        $trace = $exception->getTrace();
        $frame_where_exception_thrown = array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        );

        array_unshift($trace, $frame_where_exception_thrown);

        return $this->capture($data, $trace, $vars);
    }

    /**
     * Log an query to sentry
     */
    public function captureQuery($query, $level=self::INFO, $engine = '')
    {
        $data = array(
            'message' => $query,
            'level' => $level,
            'sentry.interfaces.Query' => array(
                'query' => $query
            )
        );

        if ($engine !== '') {
            $data['sentry.interfaces.Query']['engine'] = $engine;
        }
        return $this->capture($data, false);
    }

    private function is_http_request()
    {
        return isset($_SERVER['REQUEST_METHOD']);
    }

    protected function get_http_data()
    {
        $env = $headers = array();

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                if (in_array($key, array('HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH'))) {
                    continue;
                }
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            } elseif (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH'))) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))))] = $value;
            } else {
                $env[$key] = $value;
            }
        }

        return array(
            'sentry.interfaces.Http' => array(
                'method' => $this->_server_variable('REQUEST_METHOD'),
                'url' => $this->get_current_url(),
                'query_string' => $this->_server_variable('QUERY_STRING'),
                'data' => $_POST,
                'cookies' => $_COOKIE,
                'headers' => $headers,
                'env' => $env,
            )
        );
    }

    protected function get_user_data()
    {
        return array(
            'sentry.interfaces.User' => array(
                'is_authenticated' => isset($_SESSION) && count($_SESSION) ? true : false,
                'id' => session_id(),
                'data' => isset($_SESSION) ? $_SESSION : null,
            )
        );
    }

    protected function get_extra_data()
    {
        return array();
    }

    public function get_default_data()
    {
        return array(
            'server_name' => $this->name,
            'project' => $this->project,
            'site' => $this->site,
            'logger' => $this->logger,
            'tags' => $this->tags,
            'platform' => 'php',
        );
    }

    public function capture($data, $stack, $vars = null)
    {
        $event_id = $this->uuid4();

        if (!isset($data['timestamp'])) $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        if (!isset($data['level'])) $data['level'] = self::ERROR;

        $data = array_merge($data, $this->get_default_data());
        $data['event_id'] = $event_id;

        if ($this->is_http_request()) {
            $data = array_merge($data, $this->get_http_data());
            $data = array_merge($data, $this->get_user_data());
        }

        if ((!$stack && $this->auto_log_stacks) || $stack === True) {
            $stack = debug_backtrace();

            // Drop last stack
            array_shift($stack);
        }

        if (!empty($stack)) {
            if (!isset($data['sentry.interfaces.Stacktrace'])) {
                $data['sentry.interfaces.Stacktrace'] = array(
                    'frames' => Raven_Stacktrace::get_stack_info($stack, $this->trace, $this->shift_vars, $vars),
                );
            }
        }

        if ($extra = $this->get_extra_data()) {
            $data["extra"] = $extra;
        }

        $this->sanitize($data);
        $this->process($data);
        
        if(!$this->store_errors_for_bulk_send){
            $this->send($data);
        }else{
            if(empty($this->error_data)){
                $this->error_data = array();
            }
            $this->error_data[] = $data;
        }

        return $event_id;
    }

    public function sanitize(&$data)
    {
        $data = Raven_Serializer::serialize($data);
    }

    public function process(&$data)
    {
        foreach ($this->processors as $processor) {
            $processor->process($data);
        }
    }
    
    public function sendUnsentErrors(){
        if(!empty($this->error_data)){
            foreach($this->error_data as $data){
                $this->send($data);
            }
            unset($this->error_data);
        }
        if($this->store_errors_for_bulk_send){
            //incase an error occurs after this is called, on shutdown, send any new errors.
            $this->store_errors_for_bulk_send = !defined('RAVEN_CLIENT_END_REACHED'); 
        }
    }

    public function send($data)
    {
        $message = Raven_Compat::json_encode($data);
        
        if (function_exists("gzcompress")) {
            $message = base64_encode(gzcompress($message));
        }
        
        foreach ($this->servers as $url) {
            $client_string = 'raven-php/' . self::VERSION;
            $timestamp = microtime(true);
            if ($this->signing) {
                $signature = $this->get_signature(
                    $message, $timestamp, $this->secret_key);
            } else {
                $signature = null;
            }

            $headers = array(
                'User-Agent' => $client_string,
                'X-Sentry-Auth' => $this->get_auth_header(
                    $signature, $timestamp, $client_string, $this->public_key),
                'Content-Type' => 'application/octet-stream'
            );

            $this->send_remote($url, $message, $headers);
        }
    }

    private function send_remote($url, $data, $headers=array())
    {
        $parts = parse_url($url);
        $parts['netloc'] = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : null);

        if ($parts['scheme'] === 'udp')
            return $this->send_udp($parts['netloc'], $data, $headers['X-Sentry-Auth']);

        return $this->send_http($url, $data, $headers);
    }

    private function send_udp($netloc, $data, $headers)
    {
        list($host, $port) = explode(':', $netloc);
        $raw_data = $headers."\n\n".$data;

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_sendto($sock, $raw_data, strlen($raw_data), 0, $host, $port);
        socket_close($sock);

        return true;
    }

    /**
     * Send the message over http to the sentry url given
     */
    private function send_http($url, $data, $headers=array())
    {
        $new_headers = array();
        foreach ($headers as $key => $value) {
            array_push($new_headers, $key .': '. $value);
        }
        $parts = parse_url($url);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $new_headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        $ret = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $success = ($code == 200);
        curl_close($curl);
        if (!$success) {
            // It'd be nice just to raise an exception here, but it's not very PHP-like
            $this->_lasterror = $ret;
        } else {
            $this->_lasterror = null;
        }

        return $success;
    }

    /**
     * Create a signature
     */
    private function get_signature($message, $timestamp, $key)
    {
        return Raven_Compat::hash_hmac('sha1', sprintf('%F', $timestamp) .' '. $message, $key);
    }

    private function get_auth_header($signature, $timestamp, $client,
                                     $api_key=null)
    {
        $header = array(
            sprintf("sentry_timestamp=%F", $timestamp),
            "sentry_client={$client}",
            "sentry_version=2.0",
        );
        if (!empty($signature)) {
            $header[] = "sentry_signature={$signature}";
        }

        if ($api_key) {
            $header[] = "sentry_key={$api_key}";
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
        // When running from commandline the REQUEST_URI is missing.
        if ($this->_server_variable('REQUEST_URI') === '') {
            return null;
        }

        $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        return $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    private function _server_variable($key)
    {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return '';
    }

    public function translateSeverity($severity) {
        if (is_array($this->severity_map) && isset($this->severity_map[$severity])) {
            return $this->severity_map[$severity];
        }
        switch ($severity) {
            case E_ERROR:              return Raven_Client::ERROR;
            case E_WARNING:            return Raven_Client::WARN;
            case E_PARSE:              return Raven_Client::ERROR;
            case E_NOTICE:             return Raven_Client::INFO;
            case E_CORE_ERROR:         return Raven_Client::ERROR;
            case E_CORE_WARNING:       return Raven_Client::WARN;
            case E_COMPILE_ERROR:      return Raven_Client::ERROR;
            case E_COMPILE_WARNING:    return Raven_Client::WARN;
            case E_USER_ERROR:         return Raven_Client::ERROR;
            case E_USER_WARNING:       return Raven_Client::WARN;
            case E_USER_NOTICE:        return Raven_Client::INFO;
            case E_STRICT:             return Raven_Client::INFO;
            case E_RECOVERABLE_ERROR:  return Raven_Client::ERROR;
        }
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
          switch ($severity) {
            case E_DEPRECATED:         return Raven_Client::WARN;
            case E_USER_DEPRECATED:    return Raven_Client::WARN;
          }
        }
        return Raven_Client::ERROR;
    }

    public function registerSeverityMap($map) {
        $this->severity_map = $map;
    }
}
