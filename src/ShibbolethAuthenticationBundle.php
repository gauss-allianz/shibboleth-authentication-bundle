<?php
/*
 * Copyright (c) 2025 Gauß-Allianz e. V.
 */

/**
 *
 */

namespace GaussAllianz\ShibbolethAuthenticationBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Class ShibbolethAuthenticationBundle
 */
class ShibbolethAuthenticationBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
       $definition->rootNode()
           ->children()
                ->scalarNode('logout_route')->cannotBeEmpty()->defaultValue('/logout')->end()
                ->scalarNode('handler_path')->cannotBeEmpty()->defaultValue('/Shibboleth.sso')->end()
                ->scalarNode('session_initiator_path')->cannotBeEmpty()->defaultValue('/Login')->end()
                ->scalarNode('username_attribute')->cannotBeEmpty()->defaultValue('name')->end()
                ->scalarNode('redirect_target')->defaultNull()->end()
                ->scalarNode('session_key')->cannotBeEmpty()->defaultValue('Shib-Session-ID')->end()
                ->scalarNode('success_handler')->defaultNull()->end()
                ->scalarNode('failure_handler')->defaultNull()->end()
           ->end()
           ->children()
                ->arrayNode('attribute_definitions')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->arrayNode('entry_point')
                    ->children()
                        ->scalarNode('redirect_target')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('logout_handler')
                    ->children()
                        ->scalarNode('redirect_target')->defaultNull()->end()
                    ->end()
                ->end()
           ;
    }
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Use PHP service configuration (XML is deprecated since Symfony 7.4)
        $container->import('../config/services.php');

        $container->services()
            ->get('shibboleth_authentication')
            ->arg('$logoutRoute', $config['logout_route'])
            ->arg('$handlerPath', $config['handler_path'])
            ->arg('$sessionInitiatorPath', $config['session_initiator_path'])
            ->arg('$usernameAttribute', $config['username_attribute'])
            ->arg('$sessionKey', $config['session_key'])
            ->arg('$redirectTarget', $config['redirect_target'])
            ->arg('$attributeDefinitions', $config['attribute_definitions'])
            ;
        // Get services from container and set them
        if (isset($config['success_handler'])) {
            $container->services()
                ->get('shibboleth_authentication')
                ->arg('$successHandler', new Reference($config['success_handler']));
        }

        if (isset($config['failure_handler'])) {
            $container->services()
                ->get('shibboleth_authentication')
                ->arg('$failureHandler', new Reference($config['failure_handler']));
        }

        $container->services()
            ->get('shibboleth_authentication.entrypoint');

        if (isset($config['entry_point']['redirect_target'])) {
            $container->services()
                ->get('shibboleth_authentication.entrypoint')
                ->arg('$targetPath', $config['entry_point']['redirect_target']);
        }

        if (isset($config['logout_handler']['redirect_target'])) {
            $container->services()
                ->get('shibboleth_authentication.logout_listener')
                ->arg('$targetPath', $config['logout_handler']['redirect_target']);
        }
    }
}
