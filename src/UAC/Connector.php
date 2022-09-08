<?php
/**
 * Created by PhpStorm.
 * User: pm
 * Date: 2019-09-26
 * Time: 18:27
 */

namespace Codewiser\UAC;

use Codewiser\UAC\Contracts\CacheContract;

/**
 * Класс для конфигурирования OAuth-клиента.
 *
 * @property-read string $urlAuthorize Адрес страницы запроса авторизации.
 * @property-read string $urlAccessToken Адрес ресурса выдачи токенов доступа.
 * @property-read string $urlResourceOwnerDetails Адрес ресурса получения профиля пользователя.
 * @property-read string $urlTokenIntrospection Адрес ресурса проверки токенов.
 */
class Connector
{
    /**
     * Идентификатор приложения.
     */
    public string $clientId;

    /**
     * Секретный ключ приложения.
     */
    public string $clientSecret;

    /**
     * Адрес перенаправления.
     */
    public string $redirectUri;

    /**
     * Адрес сервера авторизации.
     */
    public string $urlServer;

    /**
     * Адрес старого сервера авторизации (со старым л/к).
     */
    public ?string $urlLegacyServer = null;

    /**
     * Collaborators passes directly to the Server object.
     *
     * @var array
     */
    public array $collaborators = [];

    public ContextManager $contextManager;

    public ?CacheContract $cache;

    /**
     * Verify server ssl.
     */
    public bool $verify = true;

    /**
     * @param string $urlServer Адрес сервера авторизации.
     * @param string $clientId Идентификатор приложения.
     * @param string $clientSecret Секретный ключ приложения.
     * @param string $redirectUri Адрес перенаправления.
     * @param CacheContract $context Драйвер контекста.
     * @param CacheContract|null $cache
     */
    public function __construct(string $urlServer, string $clientId, string $clientSecret, string $redirectUri, CacheContract $context, CacheContract $cache = null)
    {
        $this->urlServer = $urlServer;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->contextManager = new ContextManager($context);
        $this->cache = $cache;
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
            case 'urlTokenIntrospection':
                return $this->urlServer.'/token_info';
        }
    }

    public function toArray(): array
    {
        return [
            'clientId'                => $this->clientId,
            'clientSecret'            => $this->clientSecret,
            'redirectUri'             => $this->redirectUri,
            'urlServer'               => $this->urlServer,
            'urlLegacyServer'         => $this->urlLegacyServer,
            'urlAuthorize'            => $this->urlAuthorize,
            'urlAccessToken'          => $this->urlAccessToken,
            'urlResourceOwnerDetails' => $this->urlResourceOwnerDetails,
            'urlTokenIntrospection'   => $this->urlTokenIntrospection,
            'scopeSeparator'          => ' ',
            'verify'                  => $this->verify,
        ];
    }

}
