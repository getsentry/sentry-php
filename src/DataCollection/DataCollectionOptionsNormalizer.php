<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @internal
 */
final class DataCollectionOptionsNormalizer
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{mode: string, terms: string[]}
     */
    public static function normalizeKeyValueCollection(array $value): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(DataCollectionOptions::getDefaultKeyValueCollection());
        $resolver->setAllowedTypes('mode', 'string');
        $resolver->setAllowedTypes('terms', 'string[]');
        $resolver->setAllowedValues('mode', [
            'off',
            'denyList',
            'allowList',
        ]);

        /** @var array{mode: string, terms: string[]} $resolvedOptions */
        $resolvedOptions = $resolver->resolve($value);

        return $resolvedOptions;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{request: array{mode: string, terms: string[]}, response: array{mode: string, terms: string[]}}
     */
    public static function normalizeHttpHeaders(array $value): array
    {
        if (!isset($value['request']) && !isset($value['response'])) {
            $headers = self::normalizeKeyValueCollection($value);

            return [
                'request' => $headers,
                'response' => $headers,
            ];
        }

        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'request' => [],
            'response' => [],
        ]);
        $resolver->setAllowedTypes('request', 'array');
        $resolver->setAllowedTypes('response', 'array');

        /** @var array{request: array<string, mixed>, response: array<string, mixed>} $resolvedOptions */
        $resolvedOptions = $resolver->resolve($value);

        return [
            'request' => self::normalizeKeyValueCollection($resolvedOptions['request']),
            'response' => self::normalizeKeyValueCollection($resolvedOptions['response']),
        ];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{inputs: bool, outputs: bool}
     */
    public static function normalizeGenAi(array $value): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'inputs' => true,
            'outputs' => true,
        ]);
        $resolver->setAllowedTypes('inputs', 'bool');
        $resolver->setAllowedTypes('outputs', 'bool');

        /** @var array{inputs: bool, outputs: bool} $resolvedOptions */
        $resolvedOptions = $resolver->resolve($value);

        return $resolvedOptions;
    }
}
