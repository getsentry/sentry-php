<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Context;

use Raven\Util\PHPVersion;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class is a specialized implementation of the {@see Context} class that
 * stores information about the current runtime.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class RuntimeContext extends Context
{
    /**
     * @var OptionsResolver The options resolver
     */
    private $resolver;

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
     * Gets the name of the runtime.
     *
     * @return string
     */
    public function getName()
    {
        return $this->data['name'];
    }

    /**
     * Sets the name of the runtime.
     *
     * @param string $name The name
     */
    public function setName($name)
    {
        $this->offsetSet('name', $name);
    }

    /**
     * Gets the version of the runtime.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->data['version'];
    }

    /**
     * Sets the version of the runtime.
     *
     * @param string $version The version
     */
    public function setVersion($version)
    {
        $this->offsetSet('version', $version);
    }

    /**
     * Configures the options of the context.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'name' => 'php',
            'version' => PHPVersion::parseVersion(),
        ]);

        $resolver->setAllowedTypes('name', 'string');
        $resolver->setAllowedTypes('version', 'string');
    }
}
