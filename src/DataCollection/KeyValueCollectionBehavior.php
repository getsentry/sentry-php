<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-type KeyValueCollection array{mode: "off"|"denyList"|"allowList", terms: string[]}
 */
final class KeyValueCollectionBehavior
{
    /**
     * @var OptionsResolver|null
     */
    private static $resolver;

    /**
     * @var array<string, mixed>
     *
     * @phpstan-var KeyValueCollection
     */
    private $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->update($options);
    }

    /**
     * @param mixed[] $options
     */
    public function update(array $options): self
    {
        $this->options = self::resolveOptions($options);

        return $this;
    }

    public function getMode(): string
    {
        return $this->options['mode'];
    }

    public function setMode(string $mode): self
    {
        $options = array_merge($this->options, ['mode' => $mode]);

        $this->options = self::resolveOptions($options);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTerms(): array
    {
        return $this->options['terms'];
    }

    /**
     * @param string[] $terms
     */
    public function setTerms(array $terms): self
    {
        $options = array_merge($this->options, ['terms' => $terms]);

        $this->options = self::resolveOptions($options);

        return $this;
    }

    /**
     * @param mixed[] $options
     *
     * @return array<string, mixed>
     *
     * @phpstan-return KeyValueCollection
     */
    private static function resolveOptions(array $options): array
    {
        /** @var KeyValueCollection $resolvedOptions */
        $resolvedOptions = self::getResolverInstance()->resolve($options);

        return $resolvedOptions;
    }

    private static function getResolverInstance(): OptionsResolver
    {
        if (self::$resolver === null) {
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'mode' => 'denyList',
                'terms' => [],
            ]);
            $resolver->setAllowedTypes('mode', 'string');
            $resolver->setAllowedTypes('terms', 'string[]');
            $resolver->setAllowedValues('mode', [
                'off',
                'denyList',
                'allowList',
            ]);

            self::$resolver = $resolver;
        }

        return self::$resolver;
    }
}
