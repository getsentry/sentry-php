<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\OptionsResolver;

/**
 * @phpstan-type KeyValueCollectionBehavior array{mode: 'off'|'denyList'|'allowList', terms: string[]}
 * @phpstan-type HttpHeaders array{request: KeyValueCollectionBehavior, response: KeyValueCollectionBehavior}
 * @phpstan-type GenAi array{inputs: bool, outputs: bool}
 * @phpstan-type ResolvedDataCollectionOptions array{
 *     user_info: bool,
 *     cookies: KeyValueCollectionBehavior,
 *     http_headers: HttpHeaders,
 *     http_bodies: string[],
 *     query_params: KeyValueCollectionBehavior,
 *     gen_ai: GenAi,
 *     stack_frame_variables: bool,
 *     frame_context_lines: int
 * }
 *
 * @phpstan-implements \ArrayAccess<
 *     key-of<ResolvedDataCollectionOptions>,
 *     value-of<ResolvedDataCollectionOptions>
 * >
 *
 * @mago-ignore analysis:missing-template-parameter
 */
final class DataCollectionOptions implements \ArrayAccess
{
    private const COLLECTION_MODES = [
        'off',
        'denyList',
        'allowList',
    ];

    private const COLLECTION_DEFAULT = [
        'mode' => 'denyList',
        'terms' => [],
    ];

    /**
     * @internal
     */
    public const HTTP_BODY_TYPES = [
        'incomingRequest',
        'outgoingRequest',
        'incomingResponse',
        'outgoingResponse',
    ];

    private const DEFAULTS = [
        'user_info' => true,
        'cookies' => self::COLLECTION_DEFAULT,
        'http_headers' => [
            'request' => self::COLLECTION_DEFAULT,
            'response' => self::COLLECTION_DEFAULT,
        ],
        'http_bodies' => self::HTTP_BODY_TYPES,
        'query_params' => self::COLLECTION_DEFAULT,
        'gen_ai' => [
            'inputs' => true,
            'outputs' => true,
        ],
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
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->resolver = new OptionsResolver();
        $this->configureOptions($this->resolver);

        /** @var ResolvedDataCollectionOptions $resolvedOptions */
        $resolvedOptions = $this->resolver->resolve($options);
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

    /**
     * @phpstan-return KeyValueCollectionBehavior
     */
    public function getCookies(): array
    {
        return $this->options['cookies'];
    }

    /**
     * @param array<string, mixed> $cookies
     *
     * @phpstan-param array{mode?: 'off'|'denyList'|'allowList', terms?: string[]} $cookies
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
     * @phpstan-param array{
     *     mode?: 'off'|'denyList'|'allowList',
     *     terms?: string[],
     *     request?: array{mode?: 'off'|'denyList'|'allowList', terms?: string[]},
     *     response?: array{mode?: 'off'|'denyList'|'allowList', terms?: string[]}
     * } $httpHeaders
     */
    public function setHttpHeaders(array $httpHeaders): self
    {
        return $this->updateOptions(['http_headers' => $httpHeaders]);
    }

    /**
     * @return string[]
     */
    public function getHttpBodies(): array
    {
        return $this->options['http_bodies'];
    }

    /**
     * @param string[] $httpBodies
     */
    public function setHttpBodies(array $httpBodies): self
    {
        return $this->updateOptions(['http_bodies' => $httpBodies]);
    }

    /**
     * @phpstan-return KeyValueCollectionBehavior
     */
    public function getQueryParams(): array
    {
        return $this->options['query_params'];
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @phpstan-param array{mode?: 'off'|'denyList'|'allowList', terms?: string[]} $queryParams
     */
    public function setQueryParams(array $queryParams): self
    {
        return $this->updateOptions(['query_params' => $queryParams]);
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

    public function shouldCollectStackFrameVariables(): bool
    {
        return $this->options['stack_frame_variables'];
    }

    public function setStackFrameVariables(bool $stackFrameVariables): self
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

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return \is_string($offset) && \array_key_exists($offset, $this->options);
    }

    /**
     * @phpstan-template TKey of key-of<ResolvedDataCollectionOptions>
     *
     * @param mixed $offset
     *
     * @return mixed
     *
     * @phpstan-param TKey $offset
     * @phpstan-return ResolvedDataCollectionOptions[TKey]
     *
     * @mago-ignore analysis:incompatible-parameter-type
     * @mago-ignore analysis:invalid-return-statement
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            /** @phpstan-ignore-next-line Runtime access to unknown offsets is intentionally non-throwing. */
            return null;
        }

        return $this->options[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (!\is_string($offset)) {
            return;
        }

        $this->updateOptions([$offset => $value]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        if (!\is_string($offset) || !\array_key_exists($offset, self::DEFAULTS)) {
            return;
        }

        /** @var mixed $default */
        $default = self::DEFAULTS[$offset];
        $this->updateOptions([$offset => $default]);
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setAllowedTypes('user_info', 'bool');
        $resolver->setAllowedTypes('cookies', 'array');
        $resolver->setAllowedTypes('cookies.mode', 'string');
        $resolver->setAllowedTypes('cookies.terms', 'string[]');
        $resolver->setAllowedTypes('http_headers', 'array');
        $resolver->setAllowedTypes('http_headers.request', 'array');
        $resolver->setAllowedTypes('http_headers.request.mode', 'string');
        $resolver->setAllowedTypes('http_headers.request.terms', 'string[]');
        $resolver->setAllowedTypes('http_headers.response', 'array');
        $resolver->setAllowedTypes('http_headers.response.mode', 'string');
        $resolver->setAllowedTypes('http_headers.response.terms', 'string[]');
        $resolver->setAllowedTypes('http_bodies', 'string[]');
        $resolver->setAllowedTypes('query_params', 'array');
        $resolver->setAllowedTypes('query_params.mode', 'string');
        $resolver->setAllowedTypes('query_params.terms', 'string[]');
        $resolver->setAllowedTypes('gen_ai', 'array');
        $resolver->setAllowedTypes('gen_ai.inputs', 'bool');
        $resolver->setAllowedTypes('gen_ai.outputs', 'bool');
        $resolver->setAllowedTypes('stack_frame_variables', 'bool');
        $resolver->setAllowedTypes('frame_context_lines', 'int');

        $resolver->setAllowedValues('cookies.mode', self::COLLECTION_MODES);
        $resolver->setAllowedValues('http_headers.request.mode', self::COLLECTION_MODES);
        $resolver->setAllowedValues('http_headers.response.mode', self::COLLECTION_MODES);
        $resolver->setAllowedValues('query_params.mode', self::COLLECTION_MODES);
        $resolver->setAllowedValues('http_bodies', static function (array $value): bool {
            return array_diff($value, self::HTTP_BODY_TYPES) === [];
        });
        $resolver->setAllowedValues('frame_context_lines', static function (int $value): bool {
            return $value >= 0;
        });

        $resolver->setNormalizer('http_headers', static function (array $value): array {
            if (!\array_key_exists('request', $value) && !\array_key_exists('response', $value)) {
                return [
                    'request' => $value,
                    'response' => $value,
                ];
            }

            return $value;
        });
        $resolver->setDefaults(self::DEFAULTS);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function updateOptions(array $override): self
    {
        $resolved = $this->resolver->resolveOnly($override, $this->options);
        /** @var ResolvedDataCollectionOptions $options */
        $options = array_merge($this->options, $resolved);
        $this->options = $options;

        return $this;
    }
}
