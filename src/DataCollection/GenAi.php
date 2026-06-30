<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Symfony\Component\OptionsResolver\OptionsResolver;

class GenAi
{
    /**
     * @var OptionsResolver|null
     */
    private static $resolver;

    /**
     * @var bool
     */
    private $inputs;

    /**
     * @var bool
     */
    private $outputs;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        /** @var array{inputs: bool, outputs: bool} $opts */
        $opts = self::getResolverInstance()->resolve($options);
        $this->inputs = $opts['inputs'];
        $this->outputs = $opts['outputs'];
    }

    public function setInputs(bool $value): self
    {
        $this->inputs = $value;

        return $this;
    }

    public function getInputs(): bool
    {
        return $this->inputs;
    }

    public function setOutputs(bool $outputs): self
    {
        $this->outputs = $outputs;

        return $this;
    }

    public function getOutputs(): bool
    {
        return $this->outputs;
    }

    private static function getResolverInstance(): OptionsResolver
    {
        if (self::$resolver === null) {
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'inputs' => true,
                'outputs' => true,
            ]);
            $resolver->setAllowedTypes('inputs', 'bool');
            $resolver->setAllowedTypes('outputs', 'bool');

            self::$resolver = $resolver;
        }

        return self::$resolver;
    }
}
