<?php

namespace Raven;

use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Command\Factory\MapFactory;
use Raven\Plugin\SentryAuthPlugin;
use Raven\Request\Factory\ExceptionFactory;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * @method array capture(array $parameters = array())
 */
class Client extends GuzzleClient
{
    const VERSION = '1.0.0-dev';
    const PROTOCOL_VERSION = 4;

    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_FATAL = 'fatal';

    private $exceptionFactory;

    public function __construct(array $config = array())
    {
        $config = static::resolveAndValidateConfig($config);

        parent::__construct(
            sprintf(
                '{protocol}://{host}%s{+path}api/{project_id}/',
                isset($config['port']) ? sprintf(':%d', $config['port']) : ''
            ),
            $config
        );

        if (!$this->getDefaultOption('headers/User-Agent')) {
            $this->setDefaultOption(
                'headers/User-Agent',
                sprintf('raven-php/' . Client::VERSION)
            );
        }

        $this->setCommandFactory(new MapFactory(array(
            'capture' => 'Raven\Command\CaptureCommand',
        )));
        $this->addSubscriber(new SentryAuthPlugin(
            $this->getConfig('public_key'),
            $this->getConfig('secret_key'),
            self::PROTOCOL_VERSION,
            $this->getDefaultOption('headers/User-Agent')
        ));

        $this->exceptionFactory = isset($config['exception_factory'])
            ? $config['exception_factory']
            : new ExceptionFactory()
        ;
    }

    public static function create($config = array())
    {
        return new static($config);
    }

    private static function resolveAndValidateConfig(array $config)
    {
        $configuration = new Configuration();

        return $configuration->process($config);
    }

    public function captureException(\Exception $e, array $parameters = array())
    {
        if ($this->shouldIgnoreException($e)) {
            return null;
        }

        $exception = $this->exceptionFactory->create($e);

        $parameters['message'] = $e->getMessage();
        $parameters['sentry.interfaces.Exception'] = $exception;

        if ($e instanceof \ErrorException) {
            $parameters['level'] = $this->getSeverityLevel($e->getSeverity());
        }

        return $this->capture($parameters);
    }

    private function getSeverityLevel($severity)
    {
        switch ($severity) {
            case E_ERROR:              return self::LEVEL_ERROR;
            case E_WARNING:            return self::LEVEL_WARNING;
            case E_PARSE:              return self::LEVEL_ERROR;
            case E_NOTICE:             return self::LEVEL_INFO;
            case E_CORE_ERROR:         return self::LEVEL_ERROR;
            case E_CORE_WARNING:       return self::LEVEL_WARNING;
            case E_COMPILE_ERROR:      return self::LEVEL_ERROR;
            case E_COMPILE_WARNING:    return self::LEVEL_WARNING;
            case E_USER_ERROR:         return self::LEVEL_ERROR;
            case E_USER_WARNING:       return self::LEVEL_WARNING;
            case E_USER_NOTICE:        return self::LEVEL_INFO;
            case E_STRICT:             return self::LEVEL_INFO;
            case E_RECOVERABLE_ERROR:  return self::LEVEL_ERROR;
            case E_DEPRECATED:         return self::LEVEL_WARNING;
            case E_USER_DEPRECATED:    return self::LEVEL_WARNING;
        }

        return self::LEVEL_ERROR;
    }

    private function shouldIgnoreException(\Exception $e)
    {
        $exceptionClass = new \ReflectionClass(get_class($e));

        foreach ($this->getConfig('ignored_exceptions') as $ignoredException => $ignored) {
            if ($exceptionClass->getName() === $ignoredException || $exceptionClass->isSubclassOf($ignoredException)) {
                return false !== $ignored;
            }
        }

        return false;
    }
}
