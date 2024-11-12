<?php
/*
 * Copyright (c) 2024 Gauß-Allianz e. V.
 */

namespace GaussAllianz\ShibbolethAuthenticationBundle\Security\User;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 *
 */
interface ShibbolethUserProviderInterface extends UserProviderInterface
{
    /**
     * Creates a user object.
     *
     * It is up to the implementation to decide whether the object
     * should be persisted or not.
     *
     * @param array $credentials
     *
     * @return UserInterface
     */
    public function createUser(array $credentials): UserInterface;

    /**
     * Updates user object based on the given credentials.
     *
     * It is up to the implementation to how to update the user data
     * and if the data should be persisted.
     *
     * @param array $credentials Array of user credentials.
     *
     *                           Possible keys:
     *                           - 'username': (string) The username.
     *                           - 'email': (string) The email address.
     *
     * @param UserInterface|null $user
     *
     * @return void
     *
     * @throws BadCredentialsException
     * @throws UserNotFoundException
     */
    public function updateUserData(array $credentials, ?UserInterface $user = null): void;
}
