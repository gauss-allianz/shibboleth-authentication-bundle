<?php
/*
 * Copyright (c) 2024 Gauß-Allianz e. V.
 */

namespace GaussAllianz\ShibbolethAuthenticationBundle\Security\Authenticator;

use GaussAllianz\ShibbolethAuthenticationBundle\Security\Exception\ShibbolethCredentialsNotGivenException;
use GaussAllianz\ShibbolethAuthenticationBundle\Security\User\ShibbolethUserProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Psr\Log\LoggerInterface;
use Exception;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ShibbolethAuthenticator
 */
class ShibbolethAuthenticator extends AbstractAuthenticator
{

    /**
     * The constructor
     *
     * @param ShibbolethUserProviderInterface $userProvider
     * @param RouterInterface $router
     * @param LoggerInterface|null $logger
     * @param TranslatorInterface|null $translator
     * @param string $logoutRoute
     * @param string $handlerPath
     * @param string $sessionInitiatorPath
     * @param string $usernameAttribute
     * @param string $sessionKey
     * @param string|null $redirectTarget
     * @param array $attributeDefinitions
     * @param AuthenticationSuccessHandlerInterface|null $successHandler
     * @param AuthenticationFailureHandlerInterface|null $failureHandler
     */
    public function __construct(
        private readonly ShibbolethUserProviderInterface $userProvider,
        private readonly RouterInterface $router,
        private readonly ?LoggerInterface $logger,
        private readonly ?TranslatorInterface $translator,
        private readonly string $logoutRoute,
        private readonly string $handlerPath = '/Shibboleth.sso',
        private readonly string $sessionInitiatorPath = '/Login',
        private readonly string $usernameAttribute = 'Shib-Person-uid',
        private readonly string $sessionKey = 'Shib-Session-ID',
        private readonly ?string $redirectTarget = null,
        private array $attributeDefinitions = [],
        private readonly ?AuthenticationSuccessHandlerInterface $successHandler = null,
        private readonly ?AuthenticationFailureHandlerInterface $failureHandler = null )
    {
        // At least we need the information from the Shibboleth attributes ´Shib-Session-ID´ and ´Shib-Application-ID´ and the username attribute
        $this->attributeDefinitions = array_merge(
            ['Shib-Identity-Provider' => 'identityProvider', 'Shib-Session-ID' => 'sessionId', 'Shib-Application-ID' => 'applicationId'],
            [$this->usernameAttribute => 'username'],
            $this->attributeDefinitions
        );
        $this->logger?->debug('['. __METHOD__ .'] Created Shibboleth authenticator');
    }

    /**
     * @inheritdoc
     *
     * @throws ShibbolethCredentialsNotGivenException
     */
    public function authenticate(Request $request): Passport
    {
        $this->logger?->debug('['. __METHOD__ . '] Start to authenticate the request.');
        try {
            $credentials = $this->getCredentials($request);
        } catch (Exception $exception) {
            $this->logger?->debug('['. __METHOD__ . '] '.$exception->getMessage());

            throw $exception;
        }

        $usernameFromShibboleth = $credentials[$this->attributeDefinitions[$this->usernameAttribute]];

        $userBadge = new UserBadge(
            $usernameFromShibboleth,
            function ($usernameFromShibboleth) use ($credentials) {
                try {
                    return $this->userProvider->loadUserByIdentifier($usernameFromShibboleth);

                }
                catch (UserNotFoundException){
                    try {
                        return $this->userProvider->createUser($credentials);
                    } catch (Exception $exception) {
                        $this->logger?->debug('['. __METHOD__ . '] '.$exception->getMessage());
                        throw $exception;
                    }
                }
                catch (Exception $exception) {
                    $this->logger?->debug('['. __METHOD__ . '] '.$exception->getMessage());
                    throw $exception;
                }
            }
        );

        try {
            $customCredentials = new CustomCredentials(
                function ($credentials, UserInterface $user) {
                    return method_exists(
                        $user,
                        'getUserIdentifier'
                    ) && $user->getUserIdentifier() === $credentials[$this->attributeDefinitions[$this->usernameAttribute]];
                },
                $credentials
            );
        } catch (BadCredentialsException $exception) {
            throw new CustomUserMessageAuthenticationException("User is null or the credentials are invalid.", [], 0, $exception);
        }

        $passport = new Passport($userBadge, $customCredentials);

        $this->logger?->debug('['. __METHOD__ .'] Passport authenticated to Shibboleth Guard Service.', ['userIdentifier' => $passport->getUser()->getUserIdentifier()]);

        return $passport;
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param $firewallName
     *
     * @return Response|null
     *
     * @throws ShibbolethCredentialsNotGivenException
     *
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName): ?Response
    {

        $this->logger?->debug('['. __METHOD__ . ']');
        $credentials = $this->getCredentials($request);
        $user = $token->getUser();

        if (null !== $user->getUserIdentifier()) {
            try {
                $this->updateUserData($credentials, $user);
            } catch (Exception $exception) {
                $this->logger?->error('['. __METHOD__ . '] update user data exception: '.$exception->getMessage());
            }
        }

        if(null !== $this->successHandler) {
            $response =  $this->successHandler->onAuthenticationSuccess($request, $token);
            $this->logger?->debug('['. __METHOD__ .'] '.$response->headers);

            return $this->redirectTarget ? new RedirectResponse($this->redirectTarget, 302, $response->headers->all()) : $response;
        }

        // on success, let the request continue
        $targetUrl = $request->request->get('target') ?? $this->redirectTarget;
        if ($targetUrl) {
            try {
                $url = $this->router->generate($targetUrl);

                return new RedirectResponse($url);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }
    /**
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger?->debug('['. __METHOD__ .'] '. $exception->getMessage());

        if ($this->failureHandler) {
            return $this->failureHandler->onAuthenticationFailure($request, $exception);
        }

        $content =  $this->translator?->trans($exception->getMessageKey(), $exception->getMessageData()) ?? $exception->getMessageKey();
        return new Response($content, Response::HTTP_FORBIDDEN);
    }

    /**
     * @inheritdoc
     */
    public function supports(Request $request): bool
    {
        // Bypass support for the logout route
        if ($request->attributes->get('_route') === $this->logoutRoute) {
            return false;
        }

        $supports = $this->hasRequestSessionKey($request);

        $this->logger?->debug(
                '['. __METHOD__ .'] ' . ($supports ? 'Supports this request.': 'Does not support this request.')
        );

        return $supports;

    }

    /**
     * Returns URL to initiate login session. After successfully login, the user will be redirected
     * to the optional target page. The target can be an absolute or relative URL.
     *
     * @param Request     $request
     * @param string|null $targetUrl URL to redirect to after successful login. Defaults to the current request URL.
     *
     * @return string           The absolute URL to initiate a session
     */
    public function getLoginUrl(Request $request, string $targetUrl = null): string
    {
        // convert to absolute URL if not yet absolute.
        if (empty($targetUrl)) {
            $targetUrl = $request->getUri();
        }
        $this->logger?->debug('['. __METHOD__ .'] '. $targetUrl . ' scheme: '. $request->getSchemeAndHttpHost());
        return $this->getHandlerURL($request).$this->getSessionInitiatorPath().'?target='.urlencode($targetUrl);
    }

    /**
     *
     *
     * @param Request $request
     * @param $return
     *
     * @return string
     */
    public function getLogoutUrl(Request $request, $return = null): string
    {
        $logoutRedirect = $this->getAttribute($request, 'logoutURL');
        if (!empty($logoutRedirect)) {
            return $this->getHandlerUrl($request).'/Logout?return='.urlencode($logoutRedirect
            .(empty($return) ? '' : '?return='.$return));
        }

        if (!empty($return)) {
            return $this->getHandlerUrl($request).'/Logout?return='.urlencode($return);
        }

        return $this->getHandlerUrl($request).'/Logout';
    }

    /**
     * Checks if the specified `$request` object has a session key.
     *
     * @param Request $request The request object to check.
     *
     * @return bool True if the request has the session key, false otherwise.
     */
    public function hasRequestSessionKey(Request $request): bool
    {
        return $this->hasAttribute($request, $this->sessionKey);
    }


    /**
     * Returns the value for the requested attribute key from the server environment variables
     *
     * @param Request $request
     * @param string  $attributeId
     *
     * @return mixed
     */
    private function getAttribute(Request $request, string $attributeId): mixed
    {
        $value = $request->server->get($attributeId, null);
        if (null === $value) {
            $value = $request->server->get(str_replace('-', '_', $attributeId), null);
        }

        // if the requested attribute delivers multiple values, then return only the first one
        return is_array($value) ? $value [0] : $value;
    }

    /**
     * Get shibboleth credentials from the request.
     *
     * @param Request $request
     *
     * @return array|null
     * @throws ShibbolethCredentialsNotGivenException|BadCredentialsException
     */
    private function getCredentials(Request $request): ?array
    {
        $credentials = array();
        $this->logger?->debug('['. __METHOD__ .'] Start');

        // get all required attributes and deliver them with the credentials
        foreach ($this->attributeDefinitions as $attributeDefinition => $alias) {
            $attributeValue = $this->getAttribute($request, $attributeDefinition);
            if (empty($attributeValue)) {
                throw new ShibbolethCredentialsNotGivenException(
                    sprintf(
                         'Required shibboleth attribute %s is not provided by your IdP %s.',
                        $attributeDefinition,
                        $credentials['identityProvider'] ?? ''
                    )
                );
            }
            $credentials[$alias] = $attributeValue;
        }

        $usernameAttribute = $credentials[$this->attributeDefinitions[$this->usernameAttribute]];
        $lengthOfUsername = strlen($usernameAttribute);
        // If the username is empty or larger than the internal security limit throw an exception
        if ($lengthOfUsername < 1 || $lengthOfUsername > UserBadge::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException(sprintf("Invalid username provided by attribute %s.", $this->usernameAttribute));
        }

        $this->logger?->debug('['. __METHOD__ .'] End ');

        return $credentials;
    }

    /**
     * Returns the TLS forced Handler URL
     *
     * @param Request $request
     *
     * @return string
     */
    private function getHandlerUrl(Request $request): string
    {
        return 'https://'.$request->getHost().$this->handlerPath;
    }

    /**
     * @return string
     */
    private function getSessionInitiatorPath(): string
    {
        return $this->sessionInitiatorPath;
    }

    /**
     * @param Request $request
     * @param string  $attributeId
     *
     * @return bool
     */
    private function hasAttribute(Request $request, string $attributeId): bool
    {
        return $request->server->has($attributeId) && $request->server->get($attributeId) !== "";
    }

    /**
     * @param array $credentials
     * @param UserInterface $user
     * @return void
     *
     * @throws Exception
     */
    private function updateUserData(array $credentials, UserInterface $user): void
    {
        $this->logger?->debug('['. __METHOD__ .'] Start');

        try {
            $this->userProvider->updateUserData($credentials, $user);
            $this->logger?->debug('['. __METHOD__ .'] success '.$credentials[$this->attributeDefinitions[$this->usernameAttribute]]);
        } catch (Exception $exception) {
            $this->logger?->critical('['. __METHOD__ .']] update user data exception '.$exception->getMessage());
            throw $exception;
        }

        $this->logger?->debug('['. __METHOD__ .'] End');
    }


}
