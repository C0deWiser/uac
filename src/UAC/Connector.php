<?php
/**
 * Created by PhpStorm.
 * User: pm
 * Date: 2019-09-26
 * Time: 18:27
 */

namespace Codewiser\UAC;

/**
 * Класс для конфигурирования OAuth-клиента
 * @package UAC
 *
 * @property-read string urlAuthorize Адрес страницы запроса авторизации
 * @property-read string urlAccessToken Адрес эндпоинта выдачи токенов доступа
 * @property-read string urlResourceOwnerDetails Адрес эндпоинта получения профиля пользователя
 */
class Connector
{
    /**
     * Идентификатор приложения
     * @var string
     */
    public $clientId;

    /**
     * Секретный ключ приложения
     * @var string
     */
    public $clientSecret;

    /**
     * Адрес перенаправления
     * @var string
     */
    public $redirectUri;

    /**
     * Адрес сервера авторизации
     * @var string
     */
    public $urlServer;

    public $collaborators;

    /**
     * @var AbstractContext
     */
    public $context;

    /**
     * Connector constructor
     * @param string $urlServer Адрес сервера авторизации
     * @param string $clientId Идентификатор приложения
     * @param string $clientSecret Секретный ключ приложения
     * @param string $redirectUri Адрес перенаправления
     * @param AbstractContext $context
     */
    public function __construct($urlServer, $clientId, $clientSecret, $redirectUri, $context)
    {
        $this->urlServer = $urlServer;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->context = $context;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'urlAuthorize':
                return $this->urlServer.'/auth/';
            case 'urlAccessToken':
                return $this->urlServer.'/oauth/token/';
            case 'urlResourceOwnerDetails':
                return $this->urlServer.(new Api\User())->endpoint();
        }
    }

    public function toArray()
    {
        return [
            'clientId'                => $this->clientId,
            'clientSecret'            => $this->clientSecret,
            'redirectUri'             => $this->redirectUri,
            'urlServer'               => $this->urlServer,
            'urlAuthorize'            => $this->urlAuthorize,
            'urlAccessToken'          => $this->urlAccessToken,
            'urlResourceOwnerDetails' => $this->urlResourceOwnerDetails,
            'scopeSeparator'          => ' '
        ];
    }

}
