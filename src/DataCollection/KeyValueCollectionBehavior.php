<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class KeyValueCollectionBehavior
{
    public const MODE_OFF = 'off';
    public const MODE_DENY_LIST = 'denyList';
    public const MODE_ALLOW_LIST = 'allowList';

    /**
     * @var string
     *
     * @phpstan-var self::MODE_*
     */
    private $mode;

    /**
     * @var string[]
     */
    private $terms;

    /**
     * @param string[] $terms
     *
     * @phpstan-param self::MODE_* $mode
     */
    private function __construct(string $mode, array $terms = [])
    {
        $this->mode = $mode;
        $this->terms = $terms;
    }

    public static function off(): self
    {
        return new self(self::MODE_OFF);
    }

    /**
     * @param string[] $terms
     */
    public static function denyList(array $terms = []): self
    {
        return new self(self::MODE_DENY_LIST, $terms);
    }

    /**
     * @param string[] $terms
     */
    public static function allowList(array $terms = []): self
    {
        return new self(self::MODE_ALLOW_LIST, $terms);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @phpstan-param array{mode: string, terms?: string[]} $config
     */
    public static function fromArray(array $config): self
    {
        $terms = $config['terms'] ?? [];

        switch ($config['mode']) {
            case self::MODE_OFF:
                return self::off();
            case self::MODE_DENY_LIST:
                return self::denyList($terms);
            case self::MODE_ALLOW_LIST:
                return self::allowList($terms);
        }

        throw new \InvalidArgumentException(\sprintf('Invalid collection mode "%s".', $config['mode']));
    }

    /**
     * @phpstan-return self::MODE_*
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return string[]
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function isOff(): bool
    {
        return $this->mode === self::MODE_OFF;
    }
}
