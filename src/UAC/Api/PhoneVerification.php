<?php

namespace Codewiser\UAC\Api;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;

class PhoneVerification extends Basement
{
    protected function method()
    {
        return 'phone_verification';
    }

    /**
     * Запрос на подтверждение телефона
     * @param string $phone номер телефона
     * @param AccessToken $accessToken токен доступа вместо текущего пользовательского
     * @return array
     * @throws IdentityProviderException
     */
    public function POST($phone, AccessToken $accessToken = null)
    {
        return $this->provider->fetchResource(
            'POST',
            $this->endpoint(['phone' => $phone]),
            $accessToken ? $accessToken : $this->accessToken
        );
    }

    /**
     * Подтверждение номера телефона
     *
     * @param  string  $phone  номер телефона
     * @param  string  $code  проверочный код
     * @param  AccessToken|null  $accessToken  токен доступа вместо текущего пользовательского
     *
     * @throws \Codewiser\UAC\Exception\IdentityProviderException
     */
    public function PUT($phone, $code, AccessToken $accessToken = null)
    {
        return $this->provider->fetchResource(
            'PUT',
            $this->endpoint(['phone' => $phone, 'code' => $code]),
            $accessToken ? $accessToken : $this->accessToken
        );
    }
}
