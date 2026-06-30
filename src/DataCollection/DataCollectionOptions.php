<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Symfony\Component\OptionsResolver\Options as SymfonyOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-import-type KeyValueCollection from KeyValueCollectionBehavior
 *
 * @phpstan-type HttpHeadersOption KeyValueCollection|array{request?: KeyValueCollection|KeyValueCollectionBehavior, response?: KeyValueCollection|KeyValueCollectionBehavior}
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
 */
final class DataCollectionOptions
{
    /**
     * @internal
     */
    public const HTTP_BODY_TYPES = [
        'incomingRequest',
        'outgoingRequest',
        'incomingResponse',
        'outgoingResponse',
    ];

    public const SENSITIVE_DEFAULTS = [
        'auth',
        'token',
        'secret',
        'password',
        'passwd',
        'pwd',
        'key',
        'jwt',
        'bearer',
        'sso',
        'saml',
        'csrf',
        'xsrf',
        'credentials',
        'session',
        'sid',
        'identity',
    ];

    public const EXTENDED_DENY_TERMS = [
        'forwarded',
        '-ip',
        'remote-',
        'via',
        '-user',
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

        $this->resolveOptions($options);
    }

    public static function default(): self
    {
        return new self();
    }

    public function shouldCollectUserInfo(): bool
    {
        return $this->options['user_info'];
    }

    public function setUserInfo(bool $userInfo): self
    {
        return $this->resolveOptions(array_merge($this->options, ['user_info' => $userInfo]));
    }

    public function getCookies(): KeyValueCollectionBehavior
    {
        return $this->options['cookies'];
    }

    /**
     * @param KeyValueCollection|KeyValueCollectionBehavior $cookies
     */
    public function setCookies($cookies): self
    {
        return $this->resolveOptions(array_merge($this->options, ['cookies' => $cookies]));
    }

    public function getHttpHeaders(): HttpHeaders
    {
        return $this->options['http_headers'];
    }

    /**
     * @param array<string, mixed>|HttpHeaders $httpHeaders
     *
     * @phpstan-param HttpHeadersOption|HttpHeaders $httpHeaders
     */
    public function setHttpHeaders($httpHeaders): self
    {
        return $this->resolveOptions(array_merge($this->options, ['http_headers' => $httpHeaders]));
    }

    /**
     * @return string[]
     */
    public function getHttpBodies(): array
    {
        return $this->options['http_bodies'];
    }

    /**
     * @param string[]|null $httpBodies
     */
    public function setHttpBodies(?array $httpBodies): self
    {
        return $this->resolveOptions(array_merge($this->options, ['http_bodies' => $httpBodies]));
    }

    public function getQueryParams(): KeyValueCollectionBehavior
    {
        return $this->options['query_params'];
    }

    /**
     * @param KeyValueCollection|KeyValueCollectionBehavior $queryParams
     */
    public function setQueryParams($queryParams): self
    {
        return $this->resolveOptions(array_merge($this->options, ['query_params' => $queryParams]));
    }

    public function getGenAi(): GenAi
    {
        return $this->options['gen_ai'];
    }

    /**
     * @param array{inputs: bool, outputs: bool} $genAi
     */
    public function setGenAi(array $genAi): self
    {
        return $this->resolveOptions(array_merge($this->options, ['gen_ai' => $genAi]));
    }

    public function shouldCollectStackFrameVariables(): bool
    {
        return $this->options['stack_frame_variables'];
    }

    public function setStackFrameVariables(bool $stackFrameVariables): self
    {
        return $this->resolveOptions(array_merge($this->options, ['stack_frame_variables' => $stackFrameVariables]));
    }

    public function getFrameContextLines(): int
    {
        return $this->options['frame_context_lines'];
    }

    public function setFrameContextLines(int $frameContextLines): self
    {
        return $this->resolveOptions(array_merge($this->options, ['frame_context_lines' => $frameContextLines]));
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'user_info' => true,
            'cookies' => new KeyValueCollectionBehavior(),
            'http_headers' => new HttpHeaders(),
            'http_bodies' => self::HTTP_BODY_TYPES,
            'query_params' => new KeyValueCollectionBehavior(),
            'gen_ai' => new GenAi(),
            'stack_frame_variables' => true,
            'frame_context_lines' => 5,
        ]);
        $resolver->setAllowedTypes('user_info', 'bool');
        $resolver->setAllowedTypes('cookies', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('http_headers', ['array', HttpHeaders::class]);
        $resolver->setAllowedTypes('http_bodies', ['null', 'string[]']);
        $resolver->setAllowedTypes('query_params', ['array', KeyValueCollectionBehavior::class]);
        $resolver->setAllowedTypes('gen_ai', ['array', GenAi::class]);
        $resolver->setAllowedTypes('stack_frame_variables', 'bool');
        $resolver->setAllowedTypes('frame_context_lines', 'int');
        $resolver->setAllowedValues('http_bodies', static function (?array $value): bool {
            if ($value === null) {
                return true;
            }

            /** @var string[] $value */
            return \count(array_diff($value, self::HTTP_BODY_TYPES)) === 0;
        });
        $resolver->setAllowedValues('frame_context_lines', static function (int $value): bool {
            return $value >= 0;
        });
        $resolver->setNormalizer('cookies', \Closure::fromCallable([self::class, 'normalizeKeyValueCollection']));
        $resolver->setNormalizer('http_headers', \Closure::fromCallable([self::class, 'normalizeHttpHeaders']));
        $resolver->setNormalizer('http_bodies', static function (SymfonyOptions $options, ?array $value): array {
            return $value ?? self::HTTP_BODY_TYPES;
        });
        $resolver->setNormalizer('query_params', \Closure::fromCallable([self::class, 'normalizeKeyValueCollection']));
        $resolver->setNormalizer('gen_ai', \Closure::fromCallable([self::class, 'normalizeGenAi']));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveOptions(array $options): self
    {
        /** @var ResolvedDataCollectionOptions $resolvedOptions */
        $resolvedOptions = $this->resolver->resolve($options);

        $this->options = $resolvedOptions;

        return $this;
    }

    /**
     * @param array<string, mixed>|HttpHeaders $value
     */
    private static function normalizeHttpHeaders(SymfonyOptions $options, $value): HttpHeaders
    {
        if ($value instanceof HttpHeaders) {
            return $value;
        }

        return new HttpHeaders($value);
    }

    /**
     * @param array<string, mixed>|KeyValueCollectionBehavior $value
     */
    private static function normalizeKeyValueCollection(SymfonyOptions $options, $value): KeyValueCollectionBehavior
    {
        if ($value instanceof KeyValueCollectionBehavior) {
            return $value;
        }

        return new KeyValueCollectionBehavior($value);
    }

    /**
     * @param array<string, mixed>|GenAi $value
     */
    private static function normalizeGenAi(SymfonyOptions $options, $value): GenAi
    {
        if ($value instanceof GenAi) {
            return $value;
        }

        return new GenAi($value);
    }
}
