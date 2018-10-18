<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Context;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class is a specialized implementation of the {@see Context} class that
 * stores information about the operating system of the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ServerOsContext extends OptionsResolverContext
{
    /**
     * Gets the name of the operating system.
     *
     * @return string
     */
    public function getName()
    {
        return $this->data['name'];
    }

    /**
     * Sets the name of the operating system.
     *
     * @param string $name The name
     */
    public function setName($name)
    {
        $this->offsetSet('name', $name);
    }

    /**
     * Gets the version of the operating system.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->data['version'];
    }

    /**
     * Sets the version of the operating system.
     *
     * @param string $version The version
     */
    public function setVersion($version)
    {
        $this->offsetSet('version', $version);
    }

    /**
     * Gets the build of the operating system.
     *
     * @return string
     */
    public function getBuild()
    {
        return $this->data['build'];
    }

    /**
     * Sets the build of the operating system.
     *
     * @param string $build The build
     */
    public function setBuild($build)
    {
        $this->offsetSet('build', $build);
    }

    /**
     * Gets the version of the kernel of the operating system.
     *
     * @return string
     */
    public function getKernelVersion()
    {
        return $this->data['kernel_version'];
    }

    /**
     * Sets the version of the kernel of the operating system.
     *
     * @param string $kernelVersion The kernel version
     */
    public function setKernelVersion($kernelVersion)
    {
        $this->offsetSet('kernel_version', $kernelVersion);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'name' => php_uname('s'),
            'version' => php_uname('r'),
            'build' => php_uname('v'),
            'kernel_version' => php_uname('a'),
        ]);

        $resolver->setAllowedTypes('name', 'string');
        $resolver->setAllowedTypes('version', 'string');
        $resolver->setAllowedTypes('build', 'string');
        $resolver->setAllowedTypes('kernel_version', 'string');
    }
}
