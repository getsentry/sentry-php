<?php

namespace Raven;

class PSR3Client implements \Psr\Log\LoggerInterface
{
    /**
     * @var Client
     */
    protected $_client;

    /**
     * PSR3Client constructor.
     *
     * @param array|Client $inner
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function __construct($inner = [])
    {
        if (is_object($inner)) {
            if (!($inner instanceof \Raven\Client)) {
                throw new \Psr\Log\InvalidArgumentException('Object is not a Raven Client');
            }
            $this->_client = $inner;
        } elseif (is_array($inner)) {
            $this->_client = new Client(null, $inner);
        } else {
            throw new \Psr\Log\InvalidArgumentException('Object is not a Raven Client');
        }
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param object|string $message
     * @param array         $context
     *
     * @return string
     * @doc http://www.php-fig.org/psr/psr-3/
     */
    public static function interpolate($message, array $context = [])
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{'.$key.'}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * RFC 5424 log level to Sentry log level
     *
     * @var string $level
     * @return string|null
     * @doc http://tools.ietf.org/html/rfc5424
     */
    public static function getSentryLogLevel($level)
    {
        switch ($level) {
            case \Psr\Log\LogLevel::EMERGENCY:
            case \Psr\Log\LogLevel::CRITICAL:
                return Client::FATAL;
            case \Psr\Log\LogLevel::ERROR:
            case \Psr\Log\LogLevel::ALERT:
                return Client::ERROR;
            case \Psr\Log\LogLevel::WARNING:
                return Client::WARNING;
            case \Psr\Log\LogLevel::NOTICE:
            case \Psr\Log\LogLevel::INFO:
                return Client::INFO;
            case \Psr\Log\LogLevel::DEBUG:
                return Client::DEBUG;
            default:
                return null;
        }
    }

    /**
     * System is unusable.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(\Psr\Log\LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed         $level
     * @param string|object $message
     * @param array         $context
     *
     * @return void
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, $message, array $context = [])
    {
        $sentry_log = self::getSentryLogLevel($level);
        if (is_null($sentry_log)) {
            // Users SHOULD NOT use a custom level without knowing
            // for sure the current implementation supports it
            throw new \Psr\Log\InvalidArgumentException('Malformed level');
        }
        if (is_scalar($message)) {
            $string = strval($message);
        } elseif (is_object($message) and method_exists($message, '__toString')) {
            $string = $message->__toString();
        } else {
            throw new \Psr\Log\InvalidArgumentException('Malformed message');
        }
        $full_message = self::interpolate($string, $context);

        $this->_client->captureMessage($full_message, [], ['level' => $sentry_log,]);
    }
}
