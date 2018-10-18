<?php

namespace Sentry\Context;

use Sentry\Util\PHPVersion;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class is a specialized implementation of the {@see Context} class that
 * stores information about the current runtime.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RuntimeContext extends OptionsResolverContext
{
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
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'name' => 'php',
            'version' => PHPVersion::parseVersion(),
        ]);

        $resolver->setAllowedTypes('name', 'string');
        $resolver->setAllowedTypes('version', 'string');
    }
}
