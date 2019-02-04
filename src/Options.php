<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Integration\IntegrationInterface;
use Symfony\Component\OptionsResolver\Options as SymfonyOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configuration container for the Sentry client.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Options
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
    public function getSendAttempts(): int
    {
        return $this->options['send_attempts'];
    }

    /**
     * Sets the number of attempts to resend an event that failed to be sent.
     *
     * @param int $attemptsCount The number of attempts
     */
    public function setSendAttempts(int $attemptsCount): void
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
    public function getPrefixes(): array
    {
        return $this->options['prefixes'];
    }

    /**
     * Sets the prefixes which should be stripped from filenames to create
     * relative paths.
     *
     * @param array $prefixes The prefixes
     */
    public function setPrefixes(array $prefixes): void
    {
        $options = array_merge($this->options, ['prefixes' => $prefixes]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the sampling factor to apply to events. A value of 0 will deny
     * sending any events, and a value of 1 will send 100% of events.
     *
     * @return float
     */
    public function getSampleRate(): float
    {
        return $this->options['sample_rate'];
    }

    /**
     * Sets the sampling factor to apply to events. A value of 0 will deny
     * sending any events, and a value of 1 will send 100% of events.
     *
     * @param float $sampleRate The sampling factor
     */
    public function setSampleRate(float $sampleRate): void
    {
        $options = array_merge($this->options, ['sample_rate' => $sampleRate]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets whether the stacktrace will be attached on captureMessage.
     *
     * @return bool
     */
    public function shouldAttachStacktrace(): bool
    {
        return $this->options['attach_stacktrace'];
    }

    /**
     * Sets whether the stacktrace will be attached on captureMessage.
     *
     * @param bool $enable Flag indicating if the stacktrace will be attached to captureMessage calls
     */
    public function setAttachStacktrace(bool $enable): void
    {
        $options = array_merge($this->options, ['attach_stacktrace' => $enable]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the number of lines of code context to capture, or null if none.
     *
     * @return int|null
     */
    public function getContextLines(): ?int
    {
        return $this->options['context_lines'];
    }

    /**
     * Sets the number of lines of code context to capture, or null if none.
     *
     * @param int|null $contextLines The number of lines of code
     */
    public function setContextLines(?int $contextLines): void
    {
        $options = array_merge($this->options, ['context_lines' => $contextLines]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Returns whether the requests should be compressed using GZIP or not.
     *
     * @return bool
     */
    public function isCompressionEnabled(): bool
    {
        return $this->options['enable_compression'];
    }

    /**
     * Sets whether the request should be compressed using JSON or not.
     *
     * @param bool $enabled Flag indicating whether the request should be compressed
     */
    public function setEnableCompression(bool $enabled): void
    {
        $options = array_merge($this->options, ['enable_compression' => $enabled]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the environment.
     *
     * @return string|null
     */
    public function getEnvironment(): ?string
    {
        return $this->options['environment'];
    }

    /**
     * Sets the environment.
     *
     * @param string $environment The environment
     */
    public function setEnvironment(string $environment): void
    {
        $options = array_merge($this->options, ['environment' => $environment]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the list of exception classes that should be ignored when sending
     * events to Sentry.
     *
     * @return string[]
     */
    public function getExcludedExceptions(): array
    {
        return $this->options['excluded_exceptions'];
    }

    /**
     * Sets the list of exception classes that should be ignored when sending
     * events to Sentry.
     *
     * @param string[] $exceptions The list of exception classes
     */
    public function setExcludedExceptions(array $exceptions): void
    {
        $options = array_merge($this->options, ['excluded_exceptions' => $exceptions]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Checks whether the given exception should be ignored when sending events
     * to Sentry.
     *
     * @param \Throwable $exception The exception
     *
     * @return bool
     */
    public function isExcludedException(\Throwable $exception): bool
    {
        foreach ($this->options['excluded_exceptions'] as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the list of paths to exclude from in_app detection.
     *
     * @return string[]
     */
    public function getInAppExcludedPaths(): array
    {
        return $this->options['in_app_exclude'];
    }

    /**
     * Sets the list of paths to exclude from in_app detection.
     *
     * @param array $paths The list of paths
     */
    public function setInAppExcludedPaths(array $paths): void
    {
        $options = array_merge($this->options, ['in_app_exclude' => $paths]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the project ID number to send to the Sentry server.
     *
     * @return string|null
     */
    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    /**
     * Gets the project which the authenticated user is bound to.
     *
     * @return string|null
     */
    public function getProjectRoot(): ?string
    {
        return $this->options['project_root'];
    }

    /**
     * Sets the project which the authenticated user is bound to.
     *
     * @param string|null $path The path to the project root
     */
    public function setProjectRoot(?string $path): void
    {
        $options = array_merge($this->options, ['project_root' => $path]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the public key to authenticate the SDK.
     *
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Gets the secret key to authenticate the SDK.
     *
     * @return string|null
     */
    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    /**
     * Gets the logger used by Sentry.
     *
     * @return string
     */
    public function getLogger(): string
    {
        return $this->options['logger'];
    }

    /**
     * Sets the logger used by Sentry.
     *
     * @param string $logger The logger
     */
    public function setLogger(string $logger): void
    {
        $options = array_merge($this->options, ['logger' => $logger]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the release tag to be passed with every event sent to Sentry.
     *
     * @return string
     */
    public function getRelease(): ?string
    {
        return $this->options['release'];
    }

    /**
     * Sets the release tag to be passed with every event sent to Sentry.
     *
     * @param string $release The release
     */
    public function setRelease(?string $release): void
    {
        $options = array_merge($this->options, ['release' => $release]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets the DSN of the Sentry server the authenticated user is bound to.
     *
     * @return string|null
     */
    public function getDsn(): ?string
    {
        return $this->dsn;
    }

    /**
     * Gets the name of the server the SDK is running on (e.g. the hostname).
     *
     * @return string
     */
    public function getServerName(): string
    {
        return $this->options['server_name'];
    }

    /**
     * Sets the name of the server the SDK is running on (e.g. the hostname).
     *
     * @param string $serverName The server name
     */
    public function setServerName(string $serverName): void
    {
        $options = array_merge($this->options, ['server_name' => $serverName]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets a callback that will be invoked before an event is sent to the server.
     * If `null` is returned it won't be sent.
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
    public function getTags(): array
    {
        return $this->options['tags'];
    }

    /**
     * Sets a list of default tags for events.
     *
     * @param string[] $tags A list of tags
     */
    public function setTags(array $tags): void
    {
        $options = array_merge($this->options, ['tags' => $tags]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Gets a bit mask for error_reporting used in {@link ErrorHandler::handleError}.
     *
     * @return int
     */
    public function getErrorTypes(): int
    {
        return $this->options['error_types'];
    }

    /**
     * Sets a bit mask for error_reporting used in {@link ErrorHandler::handleError}.
     *
     * @param int $errorTypes The bit mask
     */
    public function setErrorTypes(int $errorTypes): void
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
     * Set integrations that will be used by the created client.
     *
     * @param IntegrationInterface[] $integrations The integrations
     */
    public function setIntegrations(array $integrations): void
    {
        $options = array_merge($this->options, ['integrations' => $integrations]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Returns all configured integrations that will be used by the Client.
     *
     * @return IntegrationInterface[]
     */
    public function getIntegrations(): array
    {
        return $this->options['integrations'];
    }

    /**
     * Should default PII be sent by default.
     *
     * @return bool
     */
    public function shouldSendDefaultPii(): bool
    {
        return $this->options['send_default_pii'];
    }

    /**
     * Sets if default PII should be sent with every event (if possible).
     *
     * @param bool $enable Flag indicating if default PII will be sent
     */
    public function setSendDefaultPii(bool $enable): void
    {
        $options = array_merge($this->options, ['send_default_pii' => $enable]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Returns whether the default integrations are enabled.
     *
     * @return bool
     */
    public function hasDefaultIntegrations(): bool
    {
        return $this->options['default_integrations'];
    }

    /**
     * Sets whether the default integrations are enabled.
     *
     * @param bool $enable Flag indicating whether the default integrations should be enabled
     */
    public function setDefaultIntegrations(bool $enable): void
    {
        $options = array_merge($this->options, ['default_integrations' => $enable]);

        $this->options = $this->resolver->resolve($options);
    }

    /**
     * Configures the options of the client.
     *
     * @param OptionsResolver $resolver The resolver for the options
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @throws \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'integrations' => [],
            'default_integrations' => true,
            'send_attempts' => 6,
            'prefixes' => explode(PATH_SEPARATOR, get_include_path()),
            'sample_rate' => 1,
            'attach_stacktrace' => false,
            'context_lines' => 3,
            'enable_compression' => true,
            'environment' => null,
            'project_root' => null,
            'logger' => 'php',
            'release' => null,
            'dsn' => $_SERVER['SENTRY_DSN'] ?? null,
            'server_name' => gethostname(),
            'before_send' => function (Event $event): ?Event {
                return $event;
            },
            'tags' => [],
            'error_types' => E_ALL,
            'max_breadcrumbs' => self::DEFAULT_MAX_BREADCRUMBS,
            'before_breadcrumb' => function (Breadcrumb $breadcrumb): ?Breadcrumb {
                return $breadcrumb;
            },
            'excluded_exceptions' => [],
            'in_app_exclude' => [],
            'send_default_pii' => false,
        ]);

        $resolver->setAllowedTypes('send_attempts', 'int');
        $resolver->setAllowedTypes('prefixes', 'array');
        $resolver->setAllowedTypes('sample_rate', ['int', 'float']);
        $resolver->setAllowedTypes('attach_stacktrace', 'bool');
        $resolver->setAllowedTypes('context_lines', 'int');
        $resolver->setAllowedTypes('enable_compression', 'bool');
        $resolver->setAllowedTypes('environment', ['null', 'string']);
        $resolver->setAllowedTypes('excluded_exceptions', 'array');
        $resolver->setAllowedTypes('in_app_exclude', 'array');
        $resolver->setAllowedTypes('project_root', ['null', 'string']);
        $resolver->setAllowedTypes('logger', 'string');
        $resolver->setAllowedTypes('release', ['null', 'string']);
        $resolver->setAllowedTypes('dsn', ['null', 'boolean', 'string']);
        $resolver->setAllowedTypes('server_name', 'string');
        $resolver->setAllowedTypes('before_send', ['callable']);
        $resolver->setAllowedTypes('tags', 'array');
        $resolver->setAllowedTypes('error_types', ['int']);
        $resolver->setAllowedTypes('max_breadcrumbs', 'int');
        $resolver->setAllowedTypes('before_breadcrumb', ['callable']);
        $resolver->setAllowedTypes('integrations', 'array');
        $resolver->setAllowedTypes('send_default_pii', 'bool');
        $resolver->setAllowedTypes('default_integrations', 'bool');

        $resolver->setAllowedValues('dsn', \Closure::fromCallable([$this, 'validateDsnOption']));
        $resolver->setAllowedValues('integrations', \Closure::fromCallable([$this, 'validateIntegrationsOption']));
        $resolver->setAllowedValues('max_breadcrumbs', \Closure::fromCallable([$this, 'validateMaxBreadcrumbsOptions']));

        $resolver->setNormalizer('dsn', \Closure::fromCallable([$this, 'normalizeDsnOption']));
        $resolver->setNormalizer('project_root', function (SymfonyOptions $options, $value) {
            if (null === $value) {
                return null;
            }

            return $this->normalizeAbsolutePath($value);
        });

        $resolver->setNormalizer('prefixes', function (SymfonyOptions $options, $value) {
            return array_map([$this, 'normalizeAbsolutePath'], $value);
        });

        $resolver->setNormalizer('in_app_exclude', function (SymfonyOptions $options, $value) {
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

        return $path;
    }

    /**
     * Normalizes the DSN option by parsing the host, public and secret keys and
     * an optional path.
     *
     * @param SymfonyOptions $options The configuration options
     * @param mixed          $dsn     The actual value of the option to normalize
     *
     * @return string|null
     */
    private function normalizeDsnOption(SymfonyOptions $options, $dsn): ?string
    {
        if (empty($dsn)) {
            return null;
        }

        switch (strtolower($dsn)) {
            case '':
            case 'false':
            case '(false)':
            case 'empty':
            case '(empty)':
            case 'null':
            case '(null)':
                return null;
        }

        $parsed = @parse_url($dsn);

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

        return $dsn;
    }

    /**
     * Validates the DSN option ensuring that all required pieces are set and
     * that the URL is valid.
     *
     * @param string|null $dsn The value of the option
     *
     * @return bool
     */
    private function validateDsnOption(?string $dsn): bool
    {
        if (null === $dsn) {
            return true;
        }

        switch (strtolower($dsn)) {
            case '':
            case 'false':
            case '(false)':
            case 'empty':
            case '(empty)':
            case 'null':
            case '(null)':
                return true;
        }

        $parsed = @parse_url($dsn);

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
    }

    /**
     * Validates that the elements of this option are all class instances that
     * implements the {@see IntegrationInterface} interface.
     *
     * @param array $integrations The value to validate
     *
     * @return bool
     */
    private function validateIntegrationsOption(array $integrations): bool
    {
        foreach ($integrations as $integration) {
            if (!$integration instanceof IntegrationInterface) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates if the value of the max_breadcrumbs option is in range.
     *
     * @param int $value The value to validate
     *
     * @return bool
     */
    private function validateMaxBreadcrumbsOptions(int $value): bool
    {
        return $value >= 0 && $value <= self::DEFAULT_MAX_BREADCRUMBS;
    }
}
