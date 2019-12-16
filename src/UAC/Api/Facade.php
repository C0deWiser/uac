<?php

namespace Codewiser\UAC\Api;

/**
 * Class Facade
 * @package UAC\Api
 *
 * @property User user Методы работы с прифилем пользователя
 * @property PhoneVerification phone_verification Методы для верфикации номера телефона
 */
class Facade extends Basement
{
    public function __get($name)
    {
        switch ($name) {
            case 'user':
                return new User($this->provider, $this->accessToken);
            case 'phone_verification':
                return new PhoneVerification($this->provider, $this->accessToken);
        }
    }

    protected function method()
    {
        return null;
    }
}
