<?php
/*
 * Copyright (c) 2025 Gauß-Allianz e. V.
 */

use GaussAllianz\ShibbolethAuthenticationBundle\Security\Authenticator\ShibbolethAuthenticator;
use GaussAllianz\ShibbolethAuthenticationBundle\Security\EntryPoint\ShibbolethAuthenticationEntryPoint;
use GaussAllianz\ShibbolethAuthenticationBundle\Security\EventListener\ShibbolethLogoutListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // Shibboleth Authentication Service
    $services
        ->set('shibboleth_authentication', ShibbolethAuthenticator::class)
        ->autowire(true)
        ->autoconfigure(true)
        // Preserve XML on-invalid behavior for constructor service args
        ->arg('$userProvider', service('shibboleth_authentication.user_provider')->nullOnInvalid())
        ->arg('$router', service(RouterInterface::class)) // exception if missing (matches XML)
        ->arg('$logger', service('logger')->nullOnInvalid())
        // Use service id to keep translator truly optional (no hard class reference)
        ->arg('$translator', service('translator')->nullOnInvalid())
        // Keep explicit reference for BC where consumer defines this id
        // Other scalar arguments are configured in the bundle's loadExtension()
        // via $container->services()->get('shibboleth_authentication')->arg(...)
    ;

    // Shibboleth Authentication Entry Point
    $services
        ->set('shibboleth_authentication.entrypoint', ShibbolethAuthenticationEntryPoint::class)
        ->autowire(true)
        ->autoconfigure(true)
        ->arg('$authenticator', service('shibboleth_authentication')) // exception if missing (matches XML)
        ->arg('$logger', service('logger')->nullOnInvalid())
    ;

    // Shibboleth Logout Event Listener
    $services
        ->set('shibboleth_authentication.logout_listener', ShibbolethLogoutListener::class)
        ->autowire(true)
        ->autoconfigure(true)
        ->arg('$authenticator', service('shibboleth_authentication')) // exception if missing (matches XML)
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->tag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onSecurityLogout'])
    ;
};
