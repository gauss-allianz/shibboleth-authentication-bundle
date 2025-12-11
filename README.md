# ShibbolethAuthenticationBundle

## Introduction
Shibboleth is a Single-Sign-On system made for web services. For more information about Shibboleth see https://www.shibboleth.net/. 

This bundle provides an authenticator to use the authentication mechanism of Symfony 5.3+. If your legacy application relies on the guard authentication mechanism, see the following repositories for more information.
- Symfony Version 4.2 you should see
https://gitlab.com/kury/ShibbolethGuardBundle/tree/symfony4 (other branch).
- Symfony Version 3.4 you should see
https://gitlab.com/kury/ShibbolethGuardBundle/tree/symfony34 (other branch).
- Symfony Version 2.8 you should see
https://github.com/roenschg/ShibbolethGuardBundle.
- Symfony previous Version 2.8 you should see
https://github.com/roenschg/ShibbolethBundle.


## Installation
1. Install this bundle by using composer

   ```
   composer require gauss-allianz/shibboleth-authentication-bundle
   ```
   
2. Create your own user provider, which implements the `ShibbolethUserProviderInterface`
   ```
   namespace App\Security\User;
   use GaussAllianz\ShibbolethAuthenticationBundle\Security\User\ShibbolethUserProviderInterface;
   
   readonly class UserProvider implements ShibbolethUserProviderInterface { ... }
   ```
3. (Optionally) Create and register an Exception Listener

## Configuration

1. Configure the Shibboleth authentication bundle to your needs at `config/packages/shibboleth_authentication.yaml`
    ```
    shibboleth_authentication:
       username_attribute: "%shibboleth_username_attribute%"    # required, the Shibboleth user name attribute 
       logout_route: '_logout_main'                             # required, the name of the logout route, depends your firewall configuration, defaults to '/logout'
       session_key: "%shibboleth_session_key%                   # (optional), name of the environment variable holding the Shibboleth session, defaults to 'Shib-Session-ID'
       redirect_target: '%env(resolve:LOGIN_REDIRECT_TARGET)%'  # (optional), target where to redirect after successful authentication, defaults to null
       attribute_definitions:                                   # (optional), mapping table from Shibboleth attributes to user attributes  
           <shibboleth attribute>: <user property>              # key:value pair, e.g. mail: email
       success_handler: <authentication_success_handler>        # (optional), a reference to an authentication handler that should be called within on successful authentication, defaults to null
       failure_handler: <authentication_failure_handler>        # (optional), a reference to an authentication handler that should be called on authentication failure (optional) 
       logout_handler:                                                         
          redirect_target: "%logout_redirect_target%"           # (optional), a target where to redirect after logout if the requests provides no logout URL, defaults to null
    ```
2. Add service declaration for your user provider in `config/services.yaml`
    ```
    services:
        shibboleth_authentication.user_provider:
           class: App\Security\User\UserProvider
    ```
3. Configure your firewall to your needs at `config/packages/security.yaml`
    ```
    security:
       firewalls:
          main:
             logout:
                path: /logout
                target: /
             custom_authenticators:
                - shibboleth_authenticator
             entry_point: shibboleth_authentication.entrypoint
    ```

## How to test Shibboleth authentication locally in your development environment
### Service Provider for dev environment 
    1. Register your (test) Service Provider within your AAI/at your Identity Provider
    2. Configure your web server to use Shibboleth authentication
    3. Configure Shibboleth
    4. Create certificates for your domain (and configure it in the web server) and the shibboleth communication
    5. (Add entry for your SP domain in your '/etc/hosts' (Linux)) 

Setting up a local IdP/SP (test) infrastructure can therefore be a very time-consuming job, but you can test and debug the interaction with your identity provider. For more information about the Shibboleth configuration, see Shibboleth Service provider documentation https://shibboleth.atlassian.net/wiki/spaces/SP3/overview.  

### Simulate shibboleth authentication within your web server
An easier way to test your functionality on your local system is to simulate the shibboleth authentication. If you know which information is usually sent from your identity provider to the Shibboleth daemon, then you can use Apaches `Setenv` directive or Nginx `fastcgi_param` to adopt this.

```
  location ~ \.php(/|$) {
        internal;

        fastcgi_param Shib-Identity-Provider "myProvider";
        fastcgi_param mail "user@company.com";
        fastcgi_param Shib-Session-ID "SessionABC123";
        fastcgi_param Shib-Application-ID "nameOfYourShibbolethApplication";
  }
```