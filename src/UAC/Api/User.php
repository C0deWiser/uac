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
     * Обновление профиля пользователя
     * @param array $data
     * @return array
     */
    public function POST($data)
    {
        return $this->provider->fetchResource('POST', $this->endpoint($data), $this->accessToken);
    }


}
