<?php
/*
 * Copyright (c) 2024 Gauß-Allianz e. V.
 */

namespace GaussAllianz\ShibbolethAuthenticationBundle\Security\EventListener;

use GaussAllianz\ShibbolethAuthenticationBundle\Security\Authenticator\ShibbolethAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Class ShibbolethLogoutListener
 */
readonly class ShibbolethLogoutListener
{
    /**
     * @param ShibbolethAuthenticator $authenticator
     * @param LoggerInterface|null $logger
     * @param string|null $targetPath
     */
    public function __construct(private ShibbolethAuthenticator $authenticator, private ?LoggerInterface $logger = null, private ?string $targetPath = null)
    {
    }

    /**
     * @param LogoutEvent $event
     * @return void
     */
    public function onSecurityLogout(LogoutEvent $event): void
    {
        $this->logger?->debug('['. __METHOD__ .'] Shibboleth logout event start', []);

        if ($this->authenticator->hasRequestSessionKey($event->getRequest())) {
            $this->logger?->debug('['. __METHOD__ .'] Shibboleth logout event redirect to Shibboleth logout handler', ['target' => $this->targetPath, 'redirectTo' => $this->authenticator->getLogoutUrl($event->getRequest(), $this->targetPath)]);
            $response = new RedirectResponse($this->authenticator->getLogoutUrl($event->getRequest(), $this->targetPath));
            $event->setResponse($response);
        }
    }
}