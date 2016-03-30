<?php

namespace SocialiteProviders\VersionOne;

use Guzzle\Http\Exception\BadResponseException;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'VERSIONONE';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['apiv1 query-api-1.0'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www11.v1host.com/V1Integrations/oauth.v1/auth', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www11.v1host.com/V1Integrations/oauth.v1/token';
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody()->getContents());
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        try {
            $data = json_encode([
                'from' => 'Member',
                'select' => ['Name', 'Username', 'Email', 'Avatar.Content'],
                'where' => ['IsSelf' => 'true'],
            ]);

            $requestOptions = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
                'body' => $data,
            ];

            $response = $this->getHttpClient()->post(
                'https://www11.v1host.com/V1Integrations/query.v1', $requestOptions
            );
        } catch (BadResponseException $e) {
            echo $e->getMessage().PHP_EOL;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        if (empty($user[0][0])) {
            echo 'Error response user data';
        }

        $user = $user[0][0];

        return (new User())->setRaw($user)->map([
            'id' => str_replace('Member:', '', $user['_oid']),
            'nickname' => $user['Username'], 'name' => $user['Name'],
            'email' => $user['Email'], 'avatar' => array_get($user, 'Avatar.Content'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
