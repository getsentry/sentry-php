<?php namespace Raven;

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Raven\Util\Options;
use Raven\Util\Dsn;

/**
 * Raven PHP Client
 *
 * @package raven
 */
class Client
{
    /**
     * Store errors for bulk sending?
     *
     * @var bool
     */
    public $store = false;

    /**
     * Sentry DSN
     *
     * @var \Raven\Util\Dsn
     */
    protected $dsn;

    /**
     * Options object
     *
     * @var \Raven\Util\Options
     */
    protected $options;

    protected $lastError;

    /**
     * The user associated with this request.
     *
     * @var Context
     */
    protected $context;

    /**
     * Make a Raven client
     *
     * @param \Raven\Util\Dsn|string|null $dsn
     * @param \Raven\Util\Options|array   $options
     * @throws \InvalidArgumentException
     */
    public function __construct($dsn = null, $options = array())
    {
        $this->dsn = $this->parseDsn($dsn);
        $this->options = $this->parseOptions($options);
        $this->setContext($this->options);
        $this->setHandler($this->options->handler);
    }

    /**
     * Parse a DSN and return a structured object.
     *
     * @param \Raven\Util\Dsn|string|null $dsn
     * @return \Raven\Util\Dsn
     */
    protected function parseDsn($dsn)
    {
        if ($dsn instanceof Dsn)
        {
            return $dsn;
        }

        if (is_null($dsn) and \Raven\get($_SERVER, "SENTRY_DSN"))
        {
            $dsn = $_SERVER['SENTRY_DSN'];
        }

        return new Dsn($dsn);
    }

    /**
     * Parse options, and return
     *
     * @param \Raven\Util\Options|array $options
     * @return \Raven\Util\Options
     */
    protected function parseOptions($options)
    {
        if ($options instanceof Options)
        {
            return $options;
        }

        return new Options($options);
    }

    /**
     * Get the last error seen
     *
     * @return mixed
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Given an identifier, returns a Sentry searchable string.
     *
     * @return string
     */
    public function getIdent($ident)
    {
        // We don't calculate checksums yet, so we only have the ident.
        return $ident;
    }

    /**
     * Log a message to sentry
     */
    public function captureMessage(
        $message,
        $params = array(),
        $level_or_options = array(),
        $stack = false,
        $vars = null
    ) {
        // Gracefully handle messages which contain formatting characters, but were not
        // intended to be used with formatting.
        if ( ! empty($params)) {
            $formatted_message = vsprintf($message, $params);
        } else {
            $formatted_message = $message;
        }

        if ($level_or_options === null) {
            $data = array();
        } else {
            if ( ! is_array($level_or_options)) {
                $data = array(
                    'level' => $level_or_options,
                );
            } else {
                $data = $level_or_options;
            }
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
    public function captureException($exception, $culprit_or_options = null, $logger = null, $vars = null)
    {
        if (in_array(get_class($exception), $this->exclude)) {
            return null;
        }

        if ( ! is_array($culprit_or_options)) {
            $data = array();
            if ($culprit_or_options !== null) {
                $data['culprit'] = $culprit_or_options;
            }
        } else {
            $data = $culprit_or_options;
        }

        // TODO(dcramer): DRY this up
        $message = $exception->getMessage();
        if (empty($message)) {
            $message = get_class($exception);
        }

        $exc = $exception;
        do {
            $exc_data = array(
                'value' => $exc->getMessage(),
                'type' => get_class($exc),
                'module' => $exc->getFile() . ':' . $exc->getLine(),
            );

            /**
             * 'sentry.interfaces.Exception'
             * Exception::getTrace doesn't store the point at where the exception
             * was thrown, so we have to stuff it in ourselves. Ugh.
             */
            $trace = $exc->getTrace();
            $frame_where_exception_thrown = array(
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
            );

            array_unshift($trace, $frame_where_exception_thrown);

            $exc_data['stacktrace'] = array(
                'frames' => Raven_Stacktrace::get_stack_info(
                    $trace, $this->trace, $this->shift_vars, $vars, $this->message_limit
                ),
            );

            $exceptions[] = $exc_data;

        } while ($has_chained_exceptions && $exc = $exc->getPrevious());

        $data['message'] = $message;
        $data['sentry.interfaces.Exception'] = array(
            'values' => array_reverse($exceptions),
        );
        if ($logger !== null) {
            $data['logger'] = $logger;
        }

        if (empty($data['level'])) {
            if (method_exists($exception, 'getSeverity')) {
                $data['level'] = $this->translateSeverity($exception->getSeverity());
            } else {
                $data['level'] = Raven::ERROR;
            }
        }

        return $this->capture($data, $trace, $vars);
    }

    /**
     * Log a query to sentry
     */
    public function captureQuery($query, $level = Raven::INFO, $engine = '')
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

    public function capture($data, $stack, $vars = null)
    {
        if ( ! isset($data['timestamp'])) {
            $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        }
        if ( ! isset($data['level'])) {
            $data['level'] = self::ERROR;
        }
        if ( ! isset($data['tags'])) {
            $data['tags'] = array();
        }
        if ( ! isset($data['extra'])) {
            $data['extra'] = array();
        }
        if ( ! isset($data['event_id'])) {
            $data['event_id'] = $this->uuid4();
        }

        if (isset($data['message'])) {
            $data['message'] = substr($data['message'], 0, $this->message_limit);
        }

        $data = array_merge($this->get_default_data(), $data);

        if ($this->is_http_request()) {
            $data = array_merge($this->get_http_data(), $data);
        }

        $data = array_merge($this->get_user_data(), $data);

        if ($this->release) {
            $data['release'] = $this->release;
        }

        $data['tags'] = array_merge(
            $this->tags,
            $this->context->tags,
            $data['tags']);

        $data['extra'] = array_merge(
            $this->get_extra_data(),
            $this->context->extra,
            $data['extra']);

        if (( ! $stack && $this->auto_log_stacks) || $stack === true) {
            $stack = debug_backtrace();

            // Drop last stack
            array_shift($stack);
        }

        if ( ! empty($stack)) {
            // manually trigger autoloading, as it's not done in some edge cases due to PHP bugs (see #60149)
            if ( ! class_exists('Raven_Stacktrace')) {
                spl_autoload_call('Raven_Stacktrace');
            }

            if ( ! isset($data['sentry.interfaces.Stacktrace'])) {
                $data['sentry.interfaces.Stacktrace'] = array(
                    'frames' => Raven_Stacktrace::get_stack_info(
                        $stack, $this->trace, $this->shift_vars, $vars, $this->message_limit
                    ),
                );
            }
        }


        if ( ! $this->store_errors_for_bulk_send) {
            $this->send($data);
        } else {
            if (empty($this->error_data)) {
                $this->error_data = array();
            }
            $this->error_data[] = $data;
        }

        return $data['event_id'];
    }

    /**
     * Translate a PHP Error constant into a Sentry log level group
     *
     * @param string $severity PHP E_$x error constant
     * @return string           Sentry log level group
     */
    public function translateSeverity($severity)
    {
        if (is_array($this->severity_map) && isset($this->severity_map[$severity])) {
            return $this->severity_map[$severity];
        }
        switch ($severity) {
            case E_ERROR:
                return Raven_Client::ERROR;
            case E_WARNING:
                return Raven_Client::WARN;
            case E_PARSE:
                return Raven_Client::ERROR;
            case E_NOTICE:
                return Raven_Client::INFO;
            case E_CORE_ERROR:
                return Raven_Client::ERROR;
            case E_CORE_WARNING:
                return Raven_Client::WARN;
            case E_COMPILE_ERROR:
                return Raven_Client::ERROR;
            case E_COMPILE_WARNING:
                return Raven_Client::WARN;
            case E_USER_ERROR:
                return Raven_Client::ERROR;
            case E_USER_WARNING:
                return Raven_Client::WARN;
            case E_USER_NOTICE:
                return Raven_Client::INFO;
            case E_STRICT:
                return Raven_Client::INFO;
            case E_RECOVERABLE_ERROR:
                return Raven_Client::ERROR;
        }
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            switch ($severity) {
                case E_DEPRECATED:
                    return Raven_Client::WARN;
                case E_USER_DEPRECATED:
                    return Raven_Client::WARN;
            }
        }
        return Raven_Client::ERROR;
    }

    /**
     * Provide a map of PHP Error constants to Sentry logging groups to use instead
     * of the defaults in translateSeverity()
     *
     * @param array $map
     */
    public function registerSeverityMap($map)
    {
        $this->severity_map = $map;
    }

    /**
     * Convenience function for setting a user's ID and Email
     *
     * @param string      $id    User's ID
     * @param string|null $email User's email
     * @param array       $data  Additional user data
     */
    public function set_user_data($id, $email = null, $data = array())
    {
        $this->user_context(array_merge(array(
            'id' => $id,
            'email' => $email,
        ), $data));
    }

    /**
     * Sets user context.
     *
     * @param array $data Associative array of user data
     */
    public function user_context($data)
    {
        $this->context->user = $data;
    }

    /**
     * Appends tags context.
     *
     * @param array $data Associative array of tags
     */
    public function tags_context($data)
    {
        $this->context->tags = array_merge($this->context->tags, $data);
    }

    /**
     * Appends additional context.
     *
     * @param array $data Associative array of extra data
     */
    public function extra_context($data)
    {
        $this->context->extra = array_merge($this->context->extra, $data);
    }

    /**
     * @param array $processors
     */
    public function setProcessors(array $processors)
    {
        $this->processors = $processors;
    }
}
