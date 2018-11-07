<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Integration\IntegrationInterface;
use Sentry\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Options as SymfonyOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configuration container for the Sentry client.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Options
{
    /**
     * The default maximum number of breadcrumbs that will be sent with an event.
     */
    public const DEFAULT_MAX_BREADCRUMBS = 100;

    /**
     * @var array The configuration options
     */
    private $options = [];

    /**
     * @var string|null A simple server string, set to the DSN found on your Sentry settings
     */
    private $dsn;

    /**
     * @var string|null The project ID number to send to the Sentry server
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
     * Gets the character encoding detection order.
     *
     * @return string|string[]|null
     */
    public function getMbDetectOrder()
    {
        return $this->options['mb_detect_order'];
    }

    /**
     * Sets the character encoding detection order.
     *
     * @param string|string[]|null $detectOrder The detection order
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
     * Checks whether the given exception should be ignored when sending events
     * to Sentry.
     *
     * @param \Throwable|\Exception $exception The exception
     *
     * @return bool
     */
    public function isExcludedException($exception)
    {
        foreach ($this->options['excluded_exceptions'] as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
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
     * Gets the project ID number to send to the Sentry server.
     *
     * @return string|null
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Gets the project which the authenticated user is bound to.
     *
     * @return string|null
     */
    public function getProjectRoot()
    {
        return $this->options['project_root'];
    }

    /**
     * Sets the project which the authenticated user is bound to.
     *
     * @param string|null $path The path to the project root
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
     * @return string|null
     */
    public function getDsn()
    {
        return $this->dsn;
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
     * Checks whether all events or a specific event (if provided) are allowed
     * to be captured. If null is returned, event will not be sent.
     *
     * @return callable
     */
    public function getBeforeSendCallback(): callable
    {
        return $this->options['before_send'];
    }

    /**
     * Sets a callable to be called to decide whether an event should
     * be captured or not.
     *
     * @param callable $callback The callable
     */
    public function setBeforeSendCallback(callable $callback): void
    {
        $options = array_merge($this->options, ['before_send' => $callback]);

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
     * Gets the maximum number of breadcrumbs sent with events.
     *
     * @return int
     */
    public function getMaxBreadcrumbs(): int
    {
        return $this->options['max_breadcrumbs'];
    }

    /**
     * Sets the maximum number of breadcrumbs sent with events.
     *
     * @param int $maxBreadcrumbs The maximum number of breadcrumbs
     */
    public function setMaxBreadcrumbs(int $maxBreadcrumbs): void
    {
        $options = array_merge($this->options, ['max_breadcrumbs' => $maxBreadcrumbs]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets a callback that will be invoked when adding a breadcrumb.
     *
     * @return callable
     */
    public function getBeforeBreadcrumbCallback(): callable
    {
        return $this->options['before_breadcrumb'];
    }

    /**
     * Sets a callback that will be invoked when adding a breadcrumb, allowing
     * to optionally modify it before adding it to future events. Note that you
     * must return a valid breadcrumb from this callback. If you do not wish to
     * modify the breadcrumb, simply return it at the end. Returning `null` will
     * cause the breadcrumb to be dropped.
     *
     * @param callable $callback The callback
     */
    public function setBeforeBreadcrumbCallback(callable $callback): void
    {
        $options = array_merge($this->options, ['before_breadcrumb' => $callback]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * @param null|TransportInterface $transport
     */
    public function setTransport(?TransportInterface $transport): void
    {
        $options = array_merge($this->options, ['transport' => $transport]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * @return null|TransportInterface
     */
    public function getTransport(): ?TransportInterface
    {
        return $this->options['transport'];
    }

    /**
     * @param null|IntegrationInterface[] $integrations
     */
    public function setIntegrations(?array $integrations): void
    {
        $options = array_merge($this->options, ['integrations' => $integrations]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * @return null|IntegrationInterface[]
     */
    public function getIntegrations(): ?array
    {
        return $this->options['integrations'];
    }

    /**
     * Configures the options of the client.
     *
     * @param OptionsResolver $resolver The resolver for the options
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @throws \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'transport' => null,
            'integrations' => null,
            'send_attempts' => 6,
            'prefixes' => explode(PATH_SEPARATOR, get_include_path()),
            'serialize_all_object' => false,
            'sample_rate' => 1,
            'mb_detect_order' => null,
            'auto_log_stacks' => true,
            'context_lines' => 3,
            'encoding' => 'gzip',
            'current_environment' => 'default',
            'environments' => [],
            'excluded_loggers' => [],
            'excluded_exceptions' => [],
            'excluded_app_paths' => [],
            'project_root' => null,
            'logger' => 'php',
            'release' => null,
            'dsn' => isset($_SERVER['SENTRY_DSN']) ? $_SERVER['SENTRY_DSN'] : null,
            'server_name' => gethostname(),
            'before_send' => function (Event $event): ?Event {
                return $event;
            },
            'tags' => [],
            'error_types' => null,
            'max_breadcrumbs' => self::DEFAULT_MAX_BREADCRUMBS,
            'before_breadcrumb' => function (Breadcrumb $breadcrumb): ?Breadcrumb {
                return $breadcrumb;
            },
        ]);

        $resolver->setAllowedTypes('send_attempts', 'int');
        $resolver->setAllowedTypes('prefixes', 'array');
        $resolver->setAllowedTypes('serialize_all_object', 'bool');
        $resolver->setAllowedTypes('sample_rate', ['int', 'float']);
        $resolver->setAllowedTypes('mb_detect_order', ['null', 'array', 'string']);
        $resolver->setAllowedTypes('auto_log_stacks', 'bool');
        $resolver->setAllowedTypes('context_lines', 'int');
        $resolver->setAllowedTypes('encoding', 'string');
        $resolver->setAllowedTypes('current_environment', 'string');
        $resolver->setAllowedTypes('environments', 'array');
        $resolver->setAllowedTypes('excluded_loggers', 'array');
        $resolver->setAllowedTypes('excluded_exceptions', 'array');
        $resolver->setAllowedTypes('excluded_app_paths', 'array');
        $resolver->setAllowedTypes('project_root', ['null', 'string']);
        $resolver->setAllowedTypes('logger', 'string');
        $resolver->setAllowedTypes('release', ['null', 'string']);
        $resolver->setAllowedTypes('dsn', ['null', 'boolean', 'string']);
        $resolver->setAllowedTypes('server_name', 'string');
        $resolver->setAllowedTypes('before_send', ['callable']);
        $resolver->setAllowedTypes('tags', 'array');
        $resolver->setAllowedTypes('error_types', ['null', 'int']);
        $resolver->setAllowedTypes('max_breadcrumbs', 'int');
        $resolver->setAllowedTypes('before_breadcrumb', ['callable']);
        $resolver->setAllowedTypes('transport', ['null', 'Sentry\Transport\TransportInterface']);
        $resolver->setAllowedTypes('integrations', ['null', 'Sentry\Integration\IntegrationInterface']);

        $resolver->setAllowedValues('encoding', ['gzip', 'json']);
        $resolver->setAllowedValues('dsn', function ($value) {
            if (empty($value)) {
                return true;
            }

            switch (strtolower($value)) {
                case '':
                case 'false':
                case '(false)':
                case 'empty':
                case '(empty)':
                case 'null':
                case '(null)':
                    return true;
            }

            $parsed = @parse_url($value);

            if (false === $parsed) {
                return false;
            }

            if (!isset($parsed['scheme'], $parsed['user'], $parsed['host'], $parsed['path'])) {
                return false;
            }

            if (empty($parsed['user']) || (isset($parsed['pass']) && empty($parsed['pass']))) {
                return false;
            }

            if (!\in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
                return false;
            }

            return true;
        });

        $resolver->setAllowedValues('max_breadcrumbs', function ($value) {
            return $value <= self::DEFAULT_MAX_BREADCRUMBS;
        });

        $resolver->setNormalizer('dsn', function (SymfonyOptions $options, $value) {
            if (empty($value)) {
                $this->dsn = null;

                return null;
            }

            switch (strtolower($value)) {
                case '':
                case 'false':
                case '(false)':
                case 'empty':
                case '(empty)':
                case 'null':
                case '(null)':
                    $this->dsn = null;

                    return null;
            }

            $parsed = @parse_url($value);

            $this->dsn = $parsed['scheme'] . '://' . $parsed['host'];

            if (isset($parsed['port']) && ((80 !== $parsed['port'] && 'http' === $parsed['scheme']) || (443 !== $parsed['port'] && 'https' === $parsed['scheme']))) {
                $this->dsn .= ':' . $parsed['port'];
            }

            $lastSlashPosition = strrpos($parsed['path'], '/');

            if (false !== $lastSlashPosition) {
                $this->dsn .= substr($parsed['path'], 0, $lastSlashPosition);
            } else {
                $this->dsn .= $parsed['path'];
            }

            $this->publicKey = $parsed['user'];
            $this->secretKey = $parsed['pass'] ?? null;

            $parts = explode('/', $parsed['path']);

            $this->projectId = array_pop($parts);

            return $value;
        });

        $resolver->setNormalizer('project_root', function (SymfonyOptions $options, $value) {
            if (null === $value) {
                return null;
            }

            return $this->normalizeAbsolutePath($value);
        });

        $resolver->setNormalizer('prefixes', function (SymfonyOptions $options, $value) {
            return array_map([$this, 'normalizeAbsolutePath'], $value);
        });

        $resolver->setNormalizer('excluded_app_paths', function (SymfonyOptions $options, $value) {
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

        if (
            \DIRECTORY_SEPARATOR === substr($path, 0, 1)
            && \DIRECTORY_SEPARATOR !== substr($path, -1)
            && '.php' !== substr($path, -4)
        ) {
            $path .= \DIRECTORY_SEPARATOR;
        }

        return $path;
    }
}
