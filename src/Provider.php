<?php

namespace SocialiteProviders\VersionOne;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use Guzzle\Http\Exception\BadResponseException;

class Provider extends AbstractProvider implements ProviderInterface
{
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
            'body' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        try {
            $client = $this->getHttpClient();

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

            $response = $client->post(
                'https://www11.v1host.com/V1Integrations/query.v1', $requestOptions
            );
        } catch (BadResponseException $e) {
            echo $e->getMessage().PHP_EOL;
        }

        return $response->json();
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
