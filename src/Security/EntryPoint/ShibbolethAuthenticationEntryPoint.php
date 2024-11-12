<?php
/*
 * Copyright (c) 2024 Gauß-Allianz e. V.
 */

namespace GaussAllianz\ShibbolethAuthenticationBundle\Security\EntryPoint;

use GaussAllianz\ShibbolethAuthenticationBundle\Security\Authenticator\ShibbolethAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

readonly class ShibbolethAuthenticationEntryPoint implements AuthenticationEntryPointInterface
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
     * @inheritDoc
     */
    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse|Response
    {
        $this->logger?->debug('['. __METHOD__ .'] Shibboleth entry point start', ['target' => $this->targetPath]);
        return new RedirectResponse($this->authenticator->getLoginUrl($request, $this->targetPath));
    }
}