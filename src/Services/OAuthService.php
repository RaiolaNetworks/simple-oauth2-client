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

        /** @var string $baseUrl */
        $baseUrl = config('oauth.base_url');

        /** @var string $appUrl */
        $appUrl = config('app.url');

        /** @var string $callback */
        $callback = config('oauth.callback');

        $options = array_merge($options, [
            'clientId'                => config('oauth.client_id'),
            'clientSecret'            => config('oauth.client_secret'),
            'redirectUri'             => $appUrl . $callback,
            'urlAuthorize'            => $baseUrl . '/application/o/authorize/',
            'urlAccessToken'          => $baseUrl . '/application/o/token/',
            'urlResourceOwnerDetails' => $baseUrl . '/application/o/userinfo/',
            'pkceMethod'              => GenericProvider::PKCE_METHOD_S256,
            'scopes'                  => $scopes,
            'responseResourceOwnerId' => 'sub',
            'prompt'                  => 'consent',
        ]);

        parent::__construct($options, $collaborators);
    }
}
