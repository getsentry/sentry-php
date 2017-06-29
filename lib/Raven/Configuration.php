<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

use Raven\Processor\RemoveCookiesProcessor;
use Raven\Processor\RemoveHttpBodyProcessor;
use Raven\Processor\SanitizeDataProcessor;
use Raven\Processor\SanitizeHttpHeadersProcessor;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configuration container for the Sentry client.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Configuration
{
    /**
     * @var array The configuration options
     */
    private $options = [];

    /**
     * @var string A simple server string, set to the DSN found on your Sentry settings
     */
    private $server;

    /**
     * @var string The project ID number to send to the Sentry server
     */
    private $projectId;

    /**
     * @var string The public key to authenticate the SDK
     */
    private $publicKey;

    /**
     * @var string The secret key to authenticate the SDK
     */
    private $secretKey;

    /**
     * @var OptionsResolver The options resolver
     */
    private $resolver;

    /**
     * Class constructor.
     *
     * @param array $options The configuration options
     */
    public function __construct(array $options = [])
    {
        $this->resolver = new OptionsResolver();

        $this->configureOptions($this->resolver);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the number of attempts to resend an event that failed to be sent.
     *
     * @return int
     */
    public function getSendAttempts()
    {
        return $this->options['send_attempts'];
    }

    /**
     * Sets the number of attempts to resend an event that failed to be sent.
     *
     * @param int $attemptsCount The number of attempts
     */
    public function setSendAttempts($attemptsCount)
    {
        $options = array_merge($this->options, ['send_attempts' => $attemptsCount]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Checks whether the X-FORWARDED-PROTO header should be trusted.
     *
     * @return bool
     */
    public function isTrustXForwardedProto()
    {
        return $this->options['trust_x_forwarded_proto'];
    }

    /**
     * Sets whether the X-FORWARDED-PROTO header should be trusted.
     *
     * @param bool $value The value of the option
     */
    public function setIsTrustXForwardedProto($value)
    {
        $options = array_merge($this->options, ['trust_x_forwarded_proto' => $value]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the prefixes which should be stripped from filenames to create
     * relative paths.
     *
     * @return string[]
     */
    public function getPrefixes()
    {
        return $this->options['prefixes'];
    }

    /**
     * Sets the prefixes which should be stripped from filenames to create
     * relative paths.
     *
     * @param array $prefixes The prefixes
     */
    public function setPrefixes(array $prefixes)
    {
        $options = array_merge($this->options, ['prefixes' => $prefixes]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets whether all the objects should be serialized.
     *
     * @return bool
     */
    public function getSerializeAllObjects()
    {
        return $this->options['serialize_all_object'];
    }

    /**
     * Sets whether all the objects should be serialized.
     *
     * @param bool $serializeAllObjects Flag indicating if all objects should be serialized
     */
    public function setSerializeAllObjects($serializeAllObjects)
    {
        $options = array_merge($this->options, ['serialize_all_object' => $serializeAllObjects]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the options that configure the HTTP client.
     *
     * @return array
     */
    public function getHttpClientOptions()
    {
        return $this->options['http_client_options'];
    }

    /**
     * Sets the options that configure the HTTP client.
     *
     * @param array $options The options
     */
    public function setHttpClientOptions(array $options)
    {
        $options = array_merge($this->options, ['http_client_options' => $options]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the sampling factor to apply to events. A value of 0 will deny
     * sending any events, and a value of 1 will send 100% of events.
     *
     * @return float
     */
    public function getSampleRate()
    {
        return $this->options['sample_rate'];
    }

    /**
     * Sets the sampling factor to apply to events. A value of 0 will deny
     * sending any events, and a value of 1 will send 100% of events.
     *
     * @param float $sampleRate The sampling factor
     */
    public function setSampleRate($sampleRate)
    {
        $options = array_merge($this->options, ['sample_rate' => $sampleRate]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets whether the default breadcrumb handlers should be installed.
     *
     * @return bool
     */
    public function shouldInstallDefaultBreadcrumbHandlers()
    {
        return $this->options['install_default_breadcrumb_handlers'];
    }

    /**
     * Sets whether the default breadcrumb handlers should be installed.
     *
     * @param bool $installDefaultBreadcrumbHandlers Flag indicating if the default handlers should be installed
     */
    public function setInstallDefaultBreadcrumbHandlers($installDefaultBreadcrumbHandlers)
    {
        $options = array_merge($this->options, ['install_default_breadcrumb_handlers' => $installDefaultBreadcrumbHandlers]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets whether the shutdown hundler should be installed.
     *
     * @return bool
     */
    public function shouldInstallShutdownHandler()
    {
        return $this->options['install_shutdown_handler'];
    }

    /**
     * Sets whether the shutdown hundler should be installed.
     *
     * @param bool $installShutdownHandler Flag indicating if the shutdown handler should be installed
     */
    public function setInstallShutdownHandler($installShutdownHandler)
    {
        $options = array_merge($this->options, ['install_shutdown_handler' => $installShutdownHandler]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the character encoding detection order.
     *
     * @return string[]|null
     */
    public function getMbDetectOrder()
    {
        return $this->options['mb_detect_order'];
    }

    /**
     * Sets the character encoding detection order.
     *
     * @param string[]|null $detectOrder The detection order
     */
    public function setMbDetectOrder($detectOrder)
    {
        $options = array_merge($this->options, ['mb_detect_order' => $detectOrder]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets whether the stacktrace must be auto-filled.
     *
     * @return bool
     */
    public function getAutoLogStacks()
    {
        return $this->options['auto_log_stacks'];
    }

    /**
     * Sets whether the stacktrace must be auto-filled.
     *
     * @param bool $enable Flag indicating if the stacktrace must be auto-filled
     */
    public function setAutoLogStacks($enable)
    {
        $options = array_merge($this->options, ['auto_log_stacks' => $enable]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the number of lines of code context to capture, or null if none.
     *
     * @return int|null
     */
    public function getContextLines()
    {
        return $this->options['context_lines'];
    }

    /**
     * Sets the number of lines of code context to capture, or null if none.
     *
     * @param int|null $contextLines The number of lines of code
     */
    public function setContextLines($contextLines)
    {
        $options = array_merge($this->options, ['context_lines' => $contextLines]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the encoding type for event bodies (GZIP or JSON).
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->options['encoding'];
    }

    /**
     * Sets the encoding type for event bodies (GZIP or JSON).
     *
     * @param string $encoding The encoding type
     */
    public function setEncoding($encoding)
    {
        $options = array_merge($this->options, ['encoding' => $encoding]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the current environment.
     *
     * @return string
     */
    public function getCurrentEnvironment()
    {
        return $this->options['current_environment'];
    }

    /**
     * Sets the current environment.
     *
     * @param string $environment The environment
     */
    public function setCurrentEnvironment($environment)
    {
        $options = array_merge($this->options, ['current_environment' => $environment]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the whitelist of environments that will send notifications to
     * Sentry.
     *
     * @return string[]
     */
    public function getEnvironments()
    {
        return $this->options['environments'];
    }

    /**
     * Sets the whitelist of environments that will send notifications to
     * Sentry.
     *
     * @param string[] $environments The environments
     */
    public function setEnvironments(array $environments)
    {
        $options = array_merge($this->options, ['environments' => $environments]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the list of logger 'progname's to exclude from breadcrumbs.
     *
     * @return string[]
     */
    public function getExcludedLoggers()
    {
        return $this->options['excluded_loggers'];
    }

    /**
     * Sets the list of logger 'progname's to exclude from breadcrumbs.
     *
     * @param string[] $loggers The list of logger 'progname's
     */
    public function setExcludedLoggers(array $loggers)
    {
        $options = array_merge($this->options, ['excluded_loggers' => $loggers]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the list of exception classes that should be ignored when sending
     * events to Sentry.
     *
     * @return string[]
     */
    public function getExcludedExceptions()
    {
        return $this->options['excluded_exceptions'];
    }

    /**
     * Sets the list of exception classes that should be ignored when sending
     * events to Sentry.
     *
     * @param string[] $exceptions The list of exception classes
     */
    public function setExcludedExceptions(array $exceptions)
    {
        $options = array_merge($this->options, ['excluded_exceptions' => $exceptions]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the list of paths to exclude from app_path detection.
     *
     * @return string[]
     */
    public function getExcludedProjectPaths()
    {
        return $this->options['excluded_app_paths'];
    }

    /**
     * Sets the list of paths to exclude from app_path detection.
     *
     * @param array $paths The list of paths
     */
    public function setExcludedProjectPaths(array $paths)
    {
        $options = array_merge($this->options, ['excluded_app_paths' => $paths]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the custom transport set to override how Sentry events are sent
     * upstream.
     *
     * @return callable|null
     */
    public function getTransport()
    {
        return $this->options['transport'];
    }

    /**
     * Set a custom transport to override how Sentry events are sent upstream.
     * The bound function will be called with `$client` and `$data` arguments
     * and is responsible for encoding the data, authenticating, and sending
     * the data to the upstream Sentry server.
     *
     * @param callable|null $transport The callable
     */
    public function setTransport(callable $transport = null)
    {
        $options = array_merge($this->options, ['transport' => $transport]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the project ID number to send to the Sentry server.
     *
     * @return string
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Gets the project which the authenticated user is bound to.
     *
     * @return string
     */
    public function getProjectRoot()
    {
        return $this->options['project_root'];
    }

    /**
     * Sets the project which the authenticated user is bound to.
     *
     * @param string $path The path to the project root
     */
    public function setProjectRoot($path)
    {
        $options = array_merge($this->options, ['project_root' => $path]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the public key to authenticate the SDK.
     *
     * @return string|null
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Gets the secret key to authenticate the SDK.
     *
     * @return string|null
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Gets the logger used by Sentry.
     *
     * @return string
     */
    public function getLogger()
    {
        return $this->options['logger'];
    }

    /**
     * Sets the logger used by Sentry.
     *
     * @param string $logger The logger
     */
    public function setLogger($logger)
    {
        $options = array_merge($this->options, ['logger' => $logger]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the proxy information to pass to the transport adapter.
     *
     * @return array
     */
    public function getProxy()
    {
        return $this->options['proxy'];
    }

    /**
     * Sets the proxy information to pass to the transport adapter.
     *
     * @param string $proxy The proxy information
     */
    public function setProxy($proxy)
    {
        $options = array_merge($this->options, ['proxy' => $proxy]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the release tag to be passed with every event sent to Sentry.
     *
     * @return string
     */
    public function getRelease()
    {
        return $this->options['release'];
    }

    /**
     * Sets the release tag to be passed with every event sent to Sentry.
     *
     * @param string $release The release
     */
    public function setRelease($release)
    {
        $options = array_merge($this->options, ['release' => $release]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the DSN of the Sentry server the authenticated user is bound to.
     *
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Gets the name of the server the SDK is running on (e.g. the hostname).
     *
     * @return string
     */
    public function getServerName()
    {
        return $this->options['server_name'];
    }

    /**
     * Sets the name of the server the SDK is running on (e.g. the hostname).
     *
     * @param string $serverName The server name
     */
    public function setServerName($serverName)
    {
        $options = array_merge($this->options, ['server_name' => $serverName]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Checks whether all events or a specific exception or event (if provided)
     * are allowed to be captured.
     *
     * @param object|\Exception $value An optional event or exception to test
     *
     * @return bool
     */
    public function shouldCapture(&$value = null)
    {
        $result = true;

        if (!empty($this->options['environments']) && !in_array($this->options['current_environment'], $this->options['environments'])) {
            $result = false;
        }

        if (null !== $this->options['should_capture'] && null !== $value) {
            $result = $result && $this->options['should_capture']($value);
        }

        return $result;
    }

    /**
     * Sets an optional callable to be called to decide whether an event should
     * be captured or not.
     *
     * @param callable|null $callable The callable
     */
    public function setShouldCapture(callable $callable = null)
    {
        $options = array_merge($this->options, ['should_capture' => $callable]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets a list of default tags for events.
     *
     * @return string[]
     */
    public function getTags()
    {
        return $this->options['tags'];
    }

    /**
     * Sets a list of default tags for events.
     *
     * @param string[] $tags A list of tags
     */
    public function setTags(array $tags)
    {
        $options = array_merge($this->options, ['tags' => $tags]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets a bit mask for error_reporting used in {@link ErrorHandler::handleError}.
     *
     * @return int
     */
    public function getErrorTypes()
    {
        return $this->options['error_types'];
    }

    /**
     * Sets a bit mask for error_reporting used in {@link ErrorHandler::handleError}.
     *
     * @param int $errorTypes The bit mask
     */
    public function setErrorTypes($errorTypes)
    {
        $options = array_merge($this->options, ['error_types' => $errorTypes]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the list of enabled processors.
     *
     * @return string[]
     */
    public function getProcessors()
    {
        return $this->options['processors'];
    }

    /**
     * Sets the list of enabled processors.
     *
     * @param string[] $processors A list of FCQN
     */
    public function setProcessors(array $processors)
    {
        $this->options = $this->resolver->resolve(['processors' => $processors]);
    }

    /**
     * Gets the options to configure the processors.
     *
     * @return array
     */
    public function getProcessorsOptions()
    {
        return $this->options['processors_options'];
    }

    /**
     * Sets the options to configure the processors.
     *
     * @param array $options The options
     */
    public function setProcessorsOptions(array $options)
    {
        $this->options = $this->resolver->resolve(['processors_options' => $options]);
    }

    /**
     * Configures the options for this processor.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'send_attempts' => 6,
            'trust_x_forwarded_proto' => false,
            'prefixes' => explode(PATH_SEPARATOR, get_include_path()),
            'serialize_all_object' => false,
            'http_client_options' => [],
            'sample_rate' => 1,
            'install_default_breadcrumb_handlers' => true,
            'install_shutdown_handler' => true,
            'mb_detect_order' => null,
            'auto_log_stacks' => false,
            'context_lines' => 3,
            'encoding' => 'gzip',
            'current_environment' => 'default',
            'environments' => [],
            'excluded_loggers' => [],
            'excluded_exceptions' => [],
            'excluded_app_paths' => [],
            'transport' => null,
            'project_root' => null,
            'logger' => 'php',
            'proxy' => null,
            'release' => null,
            'server' => isset($_SERVER['SENTRY_DSN']) ? $_SERVER['SENTRY_DSN'] : null,
            'server_name' => gethostname(),
            'should_capture' => null,
            'tags' => [],
            'error_types' => null,
            'processors_options' => [],
            'processors' => [
                SanitizeDataProcessor::class,
                RemoveCookiesProcessor::class,
                RemoveHttpBodyProcessor::class,
                SanitizeHttpHeadersProcessor::class,
            ],
        ]);

        $resolver->setAllowedTypes('send_attempts', 'int');
        $resolver->setAllowedTypes('trust_x_forwarded_proto', 'bool');
        $resolver->setAllowedTypes('prefixes', 'array');
        $resolver->setAllowedTypes('serialize_all_object', 'bool');
        $resolver->setAllowedTypes('http_client_options', 'array');
        $resolver->setAllowedTypes('sample_rate', ['int', 'float']);
        $resolver->setAllowedTypes('install_default_breadcrumb_handlers', 'bool');
        $resolver->setAllowedTypes('install_shutdown_handler', 'bool');
        $resolver->setAllowedTypes('mb_detect_order', ['null', 'array']);
        $resolver->setAllowedTypes('auto_log_stacks', 'bool');
        $resolver->setAllowedTypes('context_lines', 'int');
        $resolver->setAllowedTypes('encoding', 'string');
        $resolver->setAllowedTypes('current_environment', 'string');
        $resolver->setAllowedTypes('environments', 'array');
        $resolver->setAllowedTypes('excluded_loggers', 'array');
        $resolver->setAllowedTypes('excluded_exceptions', 'array');
        $resolver->setAllowedTypes('excluded_app_paths', 'array');
        $resolver->setAllowedTypes('transport', ['null', 'callable']);
        $resolver->setAllowedTypes('project_root', ['null', 'string']);
        $resolver->setAllowedTypes('logger', 'string');
        $resolver->setAllowedTypes('proxy', ['null', 'string']);
        $resolver->setAllowedTypes('release', ['null', 'string']);
        $resolver->setAllowedTypes('server', ['null', 'string']);
        $resolver->setAllowedTypes('server_name', 'string');
        $resolver->setAllowedTypes('should_capture', ['null', 'callable']);
        $resolver->setAllowedTypes('tags', 'array');
        $resolver->setAllowedTypes('error_types', ['null', 'int']);
        $resolver->setAllowedTypes('processors_options', 'array');
        $resolver->setAllowedTypes('processors', 'array');

        $resolver->setAllowedValues('encoding', ['gzip', 'json']);
        $resolver->setAllowedValues('server', function ($value) {
            if (null === $value) {
                return true;
            }

            $parsed = @parse_url($value);

            if (false === $parsed) {
                return false;
            }

            if (!isset($parsed['scheme'], $parsed['user'], $parsed['pass'], $parsed['host'], $parsed['path'])) {
                return false;
            }

            if (empty($parsed['user']) || empty($parsed['pass'])) {
                return false;
            }

            if (!in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
                return false;
            }

            return true;
        });

        $resolver->setNormalizer('server', function (Options $options, $value) {
            if (null === $value) {
                return $value;
            }

            $parsed = @parse_url($value);

            $this->server = $parsed['scheme'] . '://' . $parsed['host'];

            if (isset($parsed['port']) && ((80 !== $parsed['port'] && 'http' === $parsed['scheme']) || (443 !== $parsed['port'] && 'https' === $parsed['scheme']))) {
                $this->server .= ':' . $parsed['port'];
            }

            $this->server .= substr($parsed['path'], 0, strripos($parsed['path'], '/'));
            $this->publicKey = $parsed['user'];
            $this->secretKey = $parsed['pass'];

            $parts = explode('/', $parsed['path']);

            $this->projectId = array_pop($parts);

            return $value;
        });

        $resolver->setNormalizer('project_root', function (Options $options, $value) {
            if (null === $value) {
                return null;
            }

            return $this->normalizeAbsolutePath($value);
        });

        $resolver->setNormalizer('prefixes', function (Options $options, $value) {
            return array_map([$this, 'normalizeAbsolutePath'], $value);
        });

        $resolver->setNormalizer('excluded_app_paths', function (Options $options, $value) {
            return array_map([$this, 'normalizeAbsolutePath'], $value);
        });
    }

    /**
     * Normalizes the given path as an absolute path.
     *
     * @param string $value The path
     *
     * @return string
     */
    private function normalizeAbsolutePath($value)
    {
        $path = @realpath($value);

        if (false === $path) {
            $path = $value;
        }

        if (DIRECTORY_SEPARATOR === substr($path, 0, 1) && DIRECTORY_SEPARATOR !== substr($path, -1)) {
            $path = $path . DIRECTORY_SEPARATOR;
        }

        return $path;
    }
}
