<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Services;

use League\OAuth2\Client\Provider\GenericProvider;

class OAuthService extends GenericProvider
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $collaborators
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $scopes = 'openid profile email';

        if (config('oauth.offline_access') === true) {
            $scopes .= ' offline_access';
        }

        $options = array_merge($options, [
            'clientId'                => config('oauth.client_id'),
            'clientSecret'            => config('oauth.client_secret'),
            'redirectUri'             => config('app.url') . config('oauth.callback'),
            'urlAuthorize'            => config('oauth.base_url') . '/application/o/authorize/',
            'urlAccessToken'          => config('oauth.base_url') . '/application/o/token/',
            'urlResourceOwnerDetails' => config('oauth.base_url') . '/application/o/userinfo/',
            'pkceMethod'              => GenericProvider::PKCE_METHOD_S256,
            'scopes'                  => $scopes,
            'responseResourceOwnerId' => 'sub',
            'prompt'                  => 'consent',
        ]);

        parent::__construct($options, $collaborators);
    }
}
