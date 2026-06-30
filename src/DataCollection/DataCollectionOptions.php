<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * Configuration for data automatically collected by the SDK.
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

    /**
     * Terms used to identify sensitive key-value data which must be filtered.
     */
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

    /**
     * Additional deny terms users may opt into for user-identifying values.
     */
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
     * @phpstan-var array{
     *     user_info: bool,
     *     cookies: array{mode: string, terms: string[]},
     *     http_headers: array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}},
     *     http_bodies: string[],
     *     query_params: array{mode: string, terms: string[]},
     *     gen_ai: array{inputs: bool, outputs: bool},
     *     stack_frame_variables: bool,
     *     frame_context_lines: int
     * }
     */
    private $options;

    /**
     * @param array<string, mixed> $options
     *
     * @phpstan-param array{
     *     user_info: bool,
     *     cookies: array{mode: string, terms: string[]},
     *     http_headers: array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}},
     *     http_bodies: string[],
     *     query_params: array{mode: string, terms: string[]},
     *     gen_ai: array{inputs: bool, outputs: bool},
     *     stack_frame_variables: bool,
     *     frame_context_lines: int
     * } $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public static function default(): self
    {
        return new self([
            'user_info' => true,
            'cookies' => self::getDefaultKeyValueCollection(),
            'http_headers' => [
                'request' => self::getDefaultKeyValueCollection(),
                'response' => self::getDefaultKeyValueCollection(),
            ],
            'http_bodies' => self::HTTP_BODY_TYPES,
            'query_params' => self::getDefaultKeyValueCollection(),
            'gen_ai' => [
                'inputs' => true,
                'outputs' => true,
            ],
            'stack_frame_variables' => true,
            'frame_context_lines' => 5,
        ]);
    }

    /**
     * @return array{mode: string, terms: string[]}
     */
    public static function getDefaultKeyValueCollection(): array
    {
        return [
            'mode' => 'denyList',
            'terms' => [],
        ];
    }

    public function shouldCollectUserInfo(): bool
    {
        return $this->options['user_info'];
    }

    public function setUserInfo(bool $userInfo): self
    {
        $this->options['user_info'] = $userInfo;

        return $this;
    }

    /**
     * @return array{mode: string, terms: string[]}
     */
    public function getCookies(): array
    {
        return $this->options['cookies'];
    }

    /**
     * @param array{mode: string, terms: string[]} $cookies
     */
    public function setCookies(array $cookies): self
    {
        $this->options['cookies'] = $cookies;

        return $this;
    }

    /**
     * @return array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}}
     */
    public function getHttpHeaders(): array
    {
        return $this->options['http_headers'];
    }

    /**
     * @param array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}} $httpHeaders
     */
    public function setHttpHeaders(array $httpHeaders): self
    {
        $this->options['http_headers'] = $httpHeaders;

        return $this;
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
        $this->options['http_bodies'] = $httpBodies;

        return $this;
    }

    /**
     * @return array{mode: string, terms: string[]}
     */
    public function getQueryParams(): array
    {
        return $this->options['query_params'];
    }

    /**
     * @param array{mode: string, terms: string[]} $queryParams
     */
    public function setQueryParams(array $queryParams): self
    {
        $this->options['query_params'] = $queryParams;

        return $this;
    }

    /**
     * @return array{inputs: bool, outputs: bool}
     */
    public function getGenAi(): array
    {
        return $this->options['gen_ai'];
    }

    /**
     * @param array{inputs: bool, outputs: bool} $genAi
     */
    public function setGenAi(array $genAi): self
    {
        $this->options['gen_ai'] = $genAi;

        return $this;
    }

    public function shouldCollectStackFrameVariables(): bool
    {
        return $this->options['stack_frame_variables'];
    }

    public function setStackFrameVariables(bool $stackFrameVariables): self
    {
        $this->options['stack_frame_variables'] = $stackFrameVariables;

        return $this;
    }

    public function getFrameContextLines(): int
    {
        return $this->options['frame_context_lines'];
    }

    public function setFrameContextLines(int $frameContextLines): self
    {
        $this->options['frame_context_lines'] = $frameContextLines;

        return $this;
    }

    /**
     * @return array<string, mixed>
     *
     * @phpstan-return array{
     *     user_info: bool,
     *     cookies: array{mode: string, terms: string[]},
     *     http_headers: array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}},
     *     http_bodies: string[],
     *     query_params: array{mode: string, terms: string[]},
     *     gen_ai: array{inputs: bool, outputs: bool},
     *     stack_frame_variables: bool,
     *     frame_context_lines: int
     * }
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
