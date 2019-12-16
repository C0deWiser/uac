<?php

namespace Codewiser\UAC\Api;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class User extends Basement
{
    protected function method()
    {
        return 'user';
    }
    /**
     * Получение профиля пользователя
     * @return array
     * @throws IdentityProviderException
     */
    public function GET()
    {
        return $this->provider->fetchResource('GET', $this->endpoint(), $this->accessToken);
    }

    /**
     * Обновление профиля пользователя
     * @param array $data
     * @return array
     * @throws IdentityProviderException
     */
    public function POST($data)
    {
        return $this->provider->fetchResource('POST', $this->endpoint($data), $this->accessToken);
    }


}
