<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-import-type KeyValueCollection from KeyValueCollectionBehavior
 *
 * @phpstan-type HttpHeadersArray array{request: KeyValueCollection, response: KeyValueCollection}
 */
final class HttpHeaders
{
    /**
     * @var OptionsResolver|null
     */
    private static $resolver;

    /**
     * @var KeyValueCollectionBehavior
     */
    private $request;

    /**
     * @var KeyValueCollectionBehavior
     */
    private $response;

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
    public function update(array $options = []): self
    {
        $resolver = self::getResolverInstance();

        if (!\array_key_exists('request', $options) && !\array_key_exists('response', $options)) {
            $options = [
                'request' => $options,
                'response' => $options,
            ];
        }

        /** @var array{request: array<string, mixed>|KeyValueCollectionBehavior, response: array<string, mixed>|KeyValueCollectionBehavior} $options */
        $options = $resolver->resolve($options);

        $request = $options['request'];
        if ($request instanceof KeyValueCollectionBehavior) {
            $this->request = $request;
        } else {
            $this->request = new KeyValueCollectionBehavior($request);
        }

        $response = $options['response'];
        if ($response instanceof KeyValueCollectionBehavior) {
            $this->response = $response;
        } else {
            $this->response = new KeyValueCollectionBehavior($response);
        }

        return $this;
    }

    public function getRequest(): KeyValueCollectionBehavior
    {
        return $this->request;
    }

    /**
     * @param mixed $value
     */
    public function setRequest($value): self
    {
        return $this->update([
            'request' => $value,
            'response' => $this->response,
        ]);
    }

    public function getResponse(): KeyValueCollectionBehavior
    {
        return $this->response;
    }

    /**
     * @param mixed $value
     */
    public function setResponse($value): self
    {
        return $this->update([
            'request' => $this->request,
            'response' => $value,
        ]);
    }

    private static function getResolverInstance(): OptionsResolver
    {
        if (self::$resolver === null) {
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'request' => [],
                'response' => [],
            ]);
            $resolver->setAllowedTypes('request', ['array', KeyValueCollectionBehavior::class]);
            $resolver->setAllowedTypes('response', ['array', KeyValueCollectionBehavior::class]);
            self::$resolver = $resolver;
        }

        return self::$resolver;
    }
}
