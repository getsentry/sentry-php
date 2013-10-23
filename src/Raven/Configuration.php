<?php

namespace Raven;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Configuration
{
    /**
     * @var DsnParser
     */
    private $dsnParser;

    public function __construct(DsnParser $dsnParser = null)
    {
        $this->dsnParser = $dsnParser ?: new DsnParser();
    }

    public function process(array $config)
    {
        $rootNode = $this->getRootNode();
        $config = $rootNode->normalize($config);
        $config = $rootNode->finalize($config);

        if (isset($config['request']['options'])) {
            $config[Client::REQUEST_OPTIONS] = $config['request']['options'];
            unset($config['request']['options']);
        }
        if (isset($config['curl']['options'])) {
            $config[Client::CURL_OPTIONS] = $config['curl']['options'];
            unset($config['curl']['options']);
        }
        if (isset($config['command']['params'])) {
            $config[Client::COMMAND_PARAMS] = $config['command']['params'];
            unset($config['command']['params']);
        }

        return $config;
    }

    private function getRootNode()
    {
        $nodeBuilder = new NodeBuilder();
        $rootNode = $nodeBuilder->arrayNode(null);

        $this->addConfiguration($rootNode);

        return $rootNode->getNode();
    }

    public function addConfiguration(ArrayNodeDefinition $rootNode)
    {
        $dsnParser = $this->dsnParser;
        $rootNode
            ->beforeNormalization()
                ->always(function($config) use ($dsnParser) {
                    if (isset($config['dsn'])) {
                        $config = array_merge($config, $dsnParser->parse($config['dsn']));
                    }

                    if (isset($config[Client::REQUEST_OPTIONS])) {
                        $config['request']['options'] = $config[Client::REQUEST_OPTIONS];
                        unset($config[Client::REQUEST_OPTIONS]);
                    }
                    if (isset($config[Client::CURL_OPTIONS])) {
                        $config['curl']['options'] = $config[Client::CURL_OPTIONS];
                        unset($config[Client::CURL_OPTIONS]);
                    }
                    if (isset($config[Client::COMMAND_PARAMS])) {
                        $config['command']['params'] = $config[Client::COMMAND_PARAMS];
                        unset($config[Client::COMMAND_PARAMS]);
                    }

                    return $config;
                })
            ->end()
            ->children()
                ->scalarNode('public_key')
                    ->isRequired()
                ->end()
                ->scalarNode('secret_key')
                    ->isRequired()
                ->end()
                ->scalarNode('project_id')
                    ->isRequired()
                ->end()
                ->enumNode('protocol')
                    ->defaultValue('https')
                    ->values(array('http', 'https'))
                ->end()
                ->scalarNode('host')
                    ->defaultValue('app.getsentry.com')
                ->end()
                ->scalarNode('path')
                    ->defaultValue('/')
                ->end()
                ->scalarNode('port')
                    ->defaultNull()
                ->end()
                ->scalarNode('dsn')
                ->end()
                ->variableNode('exception_factory')->end()
                ->arrayNode('request')
                    ->children()
                        ->arrayNode('options')
                            ->prototype('variable')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('curl')
                    ->children()
                        ->arrayNode('options')
                            ->prototype('variable')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('command')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('params')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('level')->end()
                                ->scalarNode('logger')->end()
                                ->scalarNode('platform')->end()
                                ->scalarNode('tags')->end()
                                ->scalarNode('server_name')->defaultValue(gethostname())->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('extra')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('ignored_exceptions')
                    ->beforeNormalization()
                        ->always(function (array $ignoredExceptions) {
                            $newIgnoredExceptions = array();
                            foreach ($ignoredExceptions as $class => $ignored) {
                                if (!is_bool($ignored)) {
                                    $class = $ignored;
                                    $ignored = true;
                                }

                                $newIgnoredExceptions[$class] = $ignored;
                            }

                            return $newIgnoredExceptions;
                        })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }
}
