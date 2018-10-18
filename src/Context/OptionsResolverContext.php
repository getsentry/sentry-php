<?php

namespace Sentry\Context;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class OptionsResolverContext extends Context
{
    /**
     * @var OptionsResolver The options resolver
     */
    protected $resolver;

    /**
     * {@inheritdoc}
     *
     * @throws UndefinedOptionsException If any of the options are not supported
     *                                   by the context
     * @throws InvalidOptionsException   If any of the options don't fulfill the
     *                                   specified validation rules
     */
    public function __construct(array $data = [])
    {
        $this->resolver = new OptionsResolver();

        $this->configureOptions($this->resolver);

        parent::__construct($this->resolver->resolve($data));
    }

    /**
     * {@inheritdoc}
     *
     * @throws UndefinedOptionsException If any of the options are not supported
     *                                   by the context
     * @throws InvalidOptionsException   If any of the options don't fulfill the
     *                                   specified validation rules
     */
    public function merge(array $data, $recursive = false)
    {
        $data = $this->resolver->resolve($data);

        parent::merge($data, $recursive);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UndefinedOptionsException If any of the options are not supported
     *                                   by the context
     * @throws InvalidOptionsException   If any of the options don't fulfill the
     *                                   specified validation rules
     */
    public function setData(array $data)
    {
        $data = $this->resolver->resolve($data);

        parent::setData($data);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UndefinedOptionsException If any of the options are not supported
     *                                   by the context
     * @throws InvalidOptionsException   If any of the options don't fulfill the
     *                                   specified validation rules
     */
    public function replaceData(array $data)
    {
        $data = $this->resolver->resolve($data);

        parent::replaceData($data);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UndefinedOptionsException If any of the options are not supported
     *                                   by the context
     * @throws InvalidOptionsException   If any of the options don't fulfill the
     *                                   specified validation rules
     */
    public function offsetSet($offset, $value)
    {
        $data = $this->resolver->resolve([$offset => $value]);

        parent::offsetSet($offset, $data[$offset]);
    }

    /**
     * Configures the options of the context.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    abstract protected function configureOptions(OptionsResolver $resolver);
}
