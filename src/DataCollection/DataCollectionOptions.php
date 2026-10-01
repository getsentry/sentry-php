<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Log\LoggerInterface;
use Sentry\OptionsResolver;

/**
 * @phpstan-type KeyValueCollectionConfig array{mode?: 'off'|'denyList'|'allowList', terms?: string[]}
 * @phpstan-type HttpHeaders array{request: KeyValueCollectionBehavior, response: KeyValueCollectionBehavior}
 * @phpstan-type GenAi array{inputs: bool, outputs: bool}
 * @phpstan-type ResolvedDataCollectionOptions array{
 *     user_info: bool,
 *     cookies: KeyValueCollectionBehavior,
 *     http_headers: HttpHeaders,
 *     http_bodies: HttpMessageType[],
 *     url_query_params: KeyValueCollectionBehavior,
 *     gen_ai: GenAi,
 *     database_query_data: bool,
 *     queues: bool,
 *     stack_frame_variables: KeyValueCollectionBehavior,
 *     frame_context_lines: int
 * }
 */
final class DataCollectionOptions
{
    private const COLLECTION_MODES = [
        KeyValueCollectionBehavior::MODE_OFF,
        KeyValueCollectionBehavior::MODE_DENY_LIST,
        KeyValueCollectionBehavior::MODE_ALLOW_LIST,
    ];

    private const KEY_VALUE_COLLECTION_DEFAULT = [
        'mode' => KeyValueCollectionBehavior::MODE_DENY_LIST,
        'terms' => [],
    ];

    private const DEFAULTS = [
        'user_info' => true,
        'cookies' => self::KEY_VALUE_COLLECTION_DEFAULT,
        'http_headers' => [
            'request' => self::KEY_VALUE_COLLECTION_DEFAULT,
            'response' => self::KEY_VALUE_COLLECTION_DEFAULT,
        ],
        'http_bodies' => HttpMessageType::TYPES,
        'url_query_params' => self::KEY_VALUE_COLLECTION_DEFAULT,
        'gen_ai' => [
            'inputs' => true,
            'outputs' => true,
        ],
        'database_query_data' => true,
        'queues' => true,
        'stack_frame_variables' => true,
        'frame_context_lines' => 5,
    ];

    /**
     * @var array<string, mixed>
     *
     * @phpstan-var ResolvedDataCollectionOptions
     */
    private $options;

    /**
     * @var OptionsResolver
     */
    private $resolver;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->resolver = new OptionsResolver();
        $this->configureOptions($this->resolver);

        /** @var ResolvedDataCollectionOptions $resolvedOptions */
        $resolvedOptions = $this->resolver->resolve($options, $this->logger);
        $this->options = $resolvedOptions;
    }

    public function shouldCollectUserInfo(): bool
    {
        return $this->options['user_info'];
    }

    public function setUserInfo(bool $userInfo): self
    {
        return $this->updateOptions(['user_info' => $userInfo]);
    }

    public function getCookies(): KeyValueCollectionBehavior
    {
        return $this->options['cookies'];
    }

    /**
     * @param array<string, mixed> $cookies
     *
     * @phpstan-param KeyValueCollectionConfig $cookies
     */
    public function setCookies(array $cookies): self
    {
        return $this->updateOptions(['cookies' => $cookies]);
    }

    /**
     * @phpstan-return HttpHeaders
     */
    public function getHttpHeaders(): array
    {
        return $this->options['http_headers'];
    }

    /**
     * @param array<string, mixed> $httpHeaders
     *
     * @phpstan-param KeyValueCollectionConfig|array{
     *     request?: KeyValueCollectionConfig,
     *     response?: KeyValueCollectionConfig
     * } $httpHeaders
     */
    public function setHttpHeaders(array $httpHeaders): self
    {
        return $this->updateOptions(['http_headers' => $httpHeaders]);
    }

    /**
     * @return HttpMessageType[]
     */
    public function getHttpBodies(): array
    {
        return $this->options['http_bodies'];
    }

    public function shouldCollectHttpBody(HttpMessageType $messageType): bool
    {
        return \in_array($messageType, $this->options['http_bodies'], true);
    }

    /**
     * @param string[]|HttpMessageType[] $httpBodies
     */
    public function setHttpBodies(array $httpBodies): self
    {
        return $this->updateOptions(['http_bodies' => $httpBodies]);
    }

    public function getUrlQueryParams(): KeyValueCollectionBehavior
    {
        return $this->options['url_query_params'];
    }

    /**
     * @param array<string, mixed> $urlQueryParams
     *
     * @phpstan-param KeyValueCollectionConfig $urlQueryParams
     */
    public function setUrlQueryParams(array $urlQueryParams): self
    {
        return $this->updateOptions(['url_query_params' => $urlQueryParams]);
    }

    /**
     * @phpstan-return GenAi
     */
    public function getGenAi(): array
    {
        return $this->options['gen_ai'];
    }

    /**
     * @param array<string, mixed> $genAi
     *
     * @phpstan-param array{inputs?: bool, outputs?: bool} $genAi
     */
    public function setGenAi(array $genAi): self
    {
        return $this->updateOptions(['gen_ai' => $genAi]);
    }

    public function shouldCollectDatabaseQueryData(): bool
    {
        return $this->options['database_query_data'];
    }

    public function setDatabaseQueryData(bool $databaseQueryData): self
    {
        return $this->updateOptions(['database_query_data' => $databaseQueryData]);
    }

    public function shouldCollectQueues(): bool
    {
        return $this->options['queues'];
    }

    public function setQueues(bool $queues): self
    {
        return $this->updateOptions(['queues' => $queues]);
    }

    public function getStackFrameVariables(): KeyValueCollectionBehavior
    {
        return $this->options['stack_frame_variables'];
    }

    /**
     * @param bool|array<string, mixed> $stackFrameVariables `true` collects all variables, `false` none
     *
     * @phpstan-param bool|KeyValueCollectionConfig $stackFrameVariables
     */
    public function setStackFrameVariables($stackFrameVariables): self
    {
        return $this->updateOptions(['stack_frame_variables' => $stackFrameVariables]);
    }

    public function getFrameContextLines(): int
    {
        return $this->options['frame_context_lines'];
    }

    public function setFrameContextLines(int $frameContextLines): self
    {
        return $this->updateOptions(['frame_context_lines' => $frameContextLines]);
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setAllowedTypes('user_info', 'bool');
        $resolver->setAllowedTypes('cookies', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('http_headers', 'array');
        $resolver->setAllowedTypes('http_headers.request', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('http_headers.response', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('http_bodies', ['string[]', HttpMessageType::class . '[]']);
        $resolver->setAllowedTypes('url_query_params', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('gen_ai', 'array');
        $resolver->setAllowedTypes('gen_ai.inputs', 'bool');
        $resolver->setAllowedTypes('gen_ai.outputs', 'bool');
        $resolver->setAllowedTypes('database_query_data', 'bool');
        $resolver->setAllowedTypes('queues', 'bool');
        $resolver->setAllowedTypes('stack_frame_variables', ['bool', 'array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('frame_context_lines', 'int');

        $isValidKeyValueCollection = \Closure::fromCallable([$this, 'isValidKeyValueCollection']);
        $resolver->setAllowedValues('cookies', $isValidKeyValueCollection);
        $resolver->setAllowedValues('http_headers.request', $isValidKeyValueCollection);
        $resolver->setAllowedValues('http_headers.response', $isValidKeyValueCollection);
        $resolver->setAllowedValues('url_query_params', $isValidKeyValueCollection);
        $resolver->setAllowedValues('stack_frame_variables', $isValidKeyValueCollection);
        $resolver->setAllowedValues('http_bodies', static function (array $value): bool {
            return array_diff($value, HttpMessageType::TYPES) === [];
        });
        $resolver->setAllowedValues('frame_context_lines', static function (int $value): bool {
            return $value >= 0;
        });

        $normalizeKeyValueCollection = \Closure::fromCallable([$this, 'normalizeKeyValueCollection']);
        $resolver->setNormalizer('cookies', $normalizeKeyValueCollection);
        $resolver->setNormalizer('http_headers.request', $normalizeKeyValueCollection);
        $resolver->setNormalizer('http_headers.response', $normalizeKeyValueCollection);
        $resolver->setNormalizer('url_query_params', $normalizeKeyValueCollection);
        $resolver->setNormalizer('http_headers', static function (array $value): array {
            if (!\array_key_exists('request', $value) && !\array_key_exists('response', $value)) {
                return [
                    'request' => $value,
                    'response' => $value,
                ];
            }

            return $value;
        });
        $resolver->setNormalizer(
            'http_bodies',
            \Closure::fromCallable([$this, 'normalizeHttpBodies'])
        );
        $resolver->setNormalizer(
            'stack_frame_variables',
            \Closure::fromCallable([$this, 'normalizeStackFrameVariables'])
        );
        $resolver->setDefaults(self::DEFAULTS);
    }

    /**
     * @param string[]|HttpMessageType[] $value
     *
     * @return HttpMessageType[]
     */
    private function normalizeHttpBodies(array $value): array
    {
        $messageTypes = [];
        foreach ($value as $key => $messageType) {
            $messageTypes[$key] = $messageType instanceof HttpMessageType ? $messageType : HttpMessageType::from($messageType);
        }

        return $messageTypes;
    }

    /**
     * @param bool|KeyValueCollectionBehavior|array<string, mixed> $value
     *
     * @phpstan-param bool|KeyValueCollectionBehavior|KeyValueCollectionConfig $value
     */
    private function normalizeStackFrameVariables($value): KeyValueCollectionBehavior
    {
        if (\is_bool($value)) {
            return $value ? KeyValueCollectionBehavior::denyList() : KeyValueCollectionBehavior::off();
        }

        return $this->normalizeKeyValueCollection($value);
    }

    /**
     * @param KeyValueCollectionBehavior|array<string, mixed> $value
     *
     * @phpstan-param KeyValueCollectionBehavior|KeyValueCollectionConfig $value
     */
    private function normalizeKeyValueCollection($value): KeyValueCollectionBehavior
    {
        if ($value instanceof KeyValueCollectionBehavior) {
            return $value;
        }

        return KeyValueCollectionBehavior::fromArray($value + ['mode' => KeyValueCollectionBehavior::MODE_DENY_LIST]);
    }

    /**
     * An omitted mode defaults to `denyList`. Unknown keys, modes or terms that are not strings
     * make the whole value invalid.
     *
     * @param mixed $value
     */
    private function isValidKeyValueCollection($value): bool
    {
        if (!\is_array($value)) {
            return true;
        }

        if (array_diff(array_keys($value), ['mode', 'terms']) !== []) {
            return false;
        }

        if (\array_key_exists('mode', $value) && !\in_array($value['mode'], self::COLLECTION_MODES, true)) {
            return false;
        }

        if (!\array_key_exists('terms', $value)) {
            return true;
        }

        if (!\is_array($value['terms'])) {
            return false;
        }

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($value['terms'] as $term) {
            if (!\is_string($term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $override
     */
    private function updateOptions(array $override): self
    {
        $resolved = $this->resolver->resolveOnly($override, $this->options, $this->logger);
        /** @var ResolvedDataCollectionOptions $options */
        $options = array_merge($this->options, $resolved);
        $this->options = $options;

        return $this;
    }
}
