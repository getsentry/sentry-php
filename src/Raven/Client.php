<?php

namespace Raven;

use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Command\Factory\MapFactory;
use Raven\Plugin\SentryAuthPlugin;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Client extends GuzzleClient
{
    const VERSION = '1.0.0-dev';
    const PROTOCOL_VERSION = 4;

    public function __construct(array $config = array())
    {
        parent::__construct(
            sprintf(
                '{protocol}://{host}%s{+path}api/{project_id}/',
                isset($config['port']) ? sprintf(':%d', $config['port']) : ''
            ),
            $config
        );

        if (!$this->getDefaultOption('headers/User-Agent')) {
            $this->setDefaultOption(
                'headers/User-Agent',
                sprintf('raven-php/' . Client::VERSION)
            );
        }

        $this->setCommandFactory(new MapFactory(array(
            'capture' => 'Raven\Command\CaptureCommand',
        )));
        $this->addSubscriber(new SentryAuthPlugin(
            $this->getConfig('public_key'),
            $this->getConfig('secret_key'),
            self::PROTOCOL_VERSION,
            $this->getDefaultOption('headers/User-Agent')
        ));
    }

    public static function create($config = array())
    {
        return new static(static::resolveAndValidateConfig($config));
    }

    private static function resolveAndValidateConfig(array $config)
    {
        $dsnParser = new DsnParser();
        if (isset($config['dsn'])) {
            $config = array_merge($config, $dsnParser->parse($config['dsn']));
        }

        $resolver = new OptionsResolver();
        $resolver->setRequired(array(
            'public_key',
            'secret_key',
            'project_id',
            'protocol',
            'host',
            'path',
            'port',
        ));
        $resolver->setOptional(array(
            'dsn',
        ));

        $resolver->setDefaults(array(
            'protocol' => 'https',
            'host' => 'app.getsentry.com',
            'path' => '/',
            'port' => null,
        ));

        $resolver->setAllowedTypes(array(
            'public_key' => 'string',
            'secret_key' => 'string',
            'project_id' => 'string',
            'protocol' => 'string',
            'host' => 'string',
            'path' => 'string',
            'port' => array('null', 'integer'),
        ));
        $resolver->setAllowedValues(array(
            'protocol' => array('https', 'http'),
        ));

        $config = $resolver->resolve($config);

        return $config;
    }
}
