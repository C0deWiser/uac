<?php

namespace Codewiser\UAC;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Codewiser\UAC\Api\Facade;
use Codewiser\UAC\Exception\OauthResponseException;
use Codewiser\UAC\Model\User;

/**
 * OAuth-клиент
 * @package UAC
 *
 * @property Facade api Доступ к серверному API
 * @property Server provider
 */
abstract class AbstractClient
{
    /** @var Server $provider */
    protected $provider;

    protected $is_debug = true;

    public function __construct(Connector $connector, $options = [])
    {
        $this->provider = new Server($connector->toArray(), (array)$connector->collaborators);

        $this->is_debug = $options['is_debug'] ?? true;

        if ($this->hasAccessToken() && $this->getAccessToken()->getExpires() && $this->getAccessToken()->hasExpired()) {
            try {
                $access_token = $this->grantRefreshToken($this->getAccessToken());
                $this->setAccessToken($access_token);
            } catch (IdentityProviderException $e) {
                $this->unsetAccessToken();
            }
        }
    }

    /**
     * Запускает процесс авторизации пользователя, если это требуется. После завершения авторизации возвращает пользователя на страницу $returnPath
     * @param string|array|null $scope
     * @param string|null $returnPath
     */
    public function requireAuthorization($returnPath = null, $scope = null)
    {
        if (!$this->hasAccessToken()) {
            $this->startAuthorization($returnPath ?: $_SERVER['REQUEST_URI'], $scope);
        }
    }

    /**
     * Отправляет пользователя на сервер авторизации за кодом доступа
     * @param array|string|null $scope
     * @param string|null $returnPath
     */
    public function startAuthorization($returnPath = null, $scope = null)
    {
        $options = [];
        $options['scope'] = $scope ?: $this->defaultScopes();

        $authorizationUrl = $this->provider->getAuthorizationUrl($options);

        $context = StateContext::getInstance();

        $context->setState($this->provider->getState());
        $context->setResponseTypeCode();
        $context->setReturnPath($returnPath);

        $this->log('Start Authorization', ['url' => $authorizationUrl, 'context' => $context->toArray()]);

        header('Location: ' . $authorizationUrl);
        exit;
    }

    /**
     * Отправляет пользователя на сервер авторизации, чтобы разлогиниться
     * @param string|null $returnPath потому надо вернуть пользователя на эту страницу
     */
    public function startDeauthorization($returnPath = null)
    {
        $url = $this->provider->getDeauthorizationUrl();

        $context = StateContext::getInstance();

        $context->setState($this->provider->getState());
        $context->setResponseTypeLeave();
        $context->setReturnPath($returnPath ?: $_SERVER['REQUEST_URI']);

        $this->log('Start De-Authorization', ['url' => $url, 'context' => $context->toArray()]);

        $this->unsetAccessToken();
        $this->deauthorizeResourceOwner();

        header('Location: ' . $url);
        exit;
    }

    /**
     * Возвращает авторизованного пользователя на страницу, откуда была потребована авторизация
     *
     * @param string $finally альтернативный адрес возврата (если в истории ничего нет)
     * @return void если метод завершился, значит перенаправление не состоялось
     */
    public function finishOauthProcess($finally = null)
    {
        $context = StateContext::getInstance();

        if ($context->isPopup()) {
            echo "<script>window.close();</script>";
            StateContext::forget();
            die();
        } else {
            $return = $context->getReturnPath() ?: $finally;
            StateContext::forget();

            $this->log("return path: {$return}");

            if ($return) {
                header("Location: {$return}");
                exit();
            }
        }
    }

    /**
     * OAuth callback
     *
     * Универсальный коллбэк
     * @param array $request
     * @param array|string|null $scope
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function callbackController(array $request, $scope = null)
    {
        // Поднимем сохраненный в сессии контекст
        $context = StateContext::getInstance();

        $this->log('Callback', ['request' => $request, 'context' => $context->toArray()]);

        // Сразу обработаем ошибку
        if (isset($request['error'])) {
            throw new OauthResponseException($request['error'], @$request['error_description'], @$request['error_uri']);
        }

        if (isset($request['state'])) {

            if (!$context->is($request['state'])) {
                // Подделка!
                StateContext::forget();
                exit('Invalid state');
            }

            if ($context->isResponseTypeLeave()) {
                // Ходили деавторизовываться на сервер, разавторизуемся и тут
                $this->log("De-Authorization");

            } elseif ($context->isResponseTypeCode() && isset($request['code'])) {
                // Это авторизация по коду

                $this->log("Got code: {$request['code']}");
                $access_token = $this->grantAuthorizationCode($request['code']);
                $this->log("Got token: {$access_token->getToken()}");

                $this->setAccessToken($access_token);
                $this->authorizeResourceOwner($resource = $this->provider->getResourceOwner($access_token));

            } else {

                // Не должно нас тут быть...
                StateContext::forget();
                exit('Invalid request');
            }
        }

        if (!isset($request['code']) && !isset($request['state'])) {
            // Отправил пользователя авторизоваться
            $this->startAuthorization(null, $scope);
        }
    }

    /**
     * Должен сохранить токен в сессионном хранилище
     * @param AccessToken|AccessTokenInterface $accessToken
     */
    abstract protected function setAccessToken(AccessTokenInterface $accessToken);

    /**
     * Должен достать токен из сессионного хранилища
     * @return AccessToken|AccessTokenInterface|null $accessToken
     */
    abstract public function getAccessToken();

    /**
     * Должен удалить токен из сессионного хранилища
     * @return void
     */
    abstract protected function unsetAccessToken();

    /**
     * Проверяет, получен ли токен доступа
     * @return bool
     */
    public function hasAccessToken()
    {
        return !!$this->getAccessToken();
    }

    /**
     * Должен локально авторизовать пользователя.
     *
     * Ищем пользователя у себя в базе данных. Если не находим, то добавляем. Если находим, то обновляем. Потом авторизуем.
     *
     * @param User|ResourceOwnerInterface $user
     */
    abstract protected function authorizeResourceOwner($user);

    /**
     * Должен локально разавторизовать пользователя.
     */
    abstract protected function deauthorizeResourceOwner();

    /**
     * Записать событие в лог
     *
     * @param $message
     * @param array $context
     * @return mixed
     */
    abstract public function log($message, array $context = []);

    /**
     * @return string|array|null
     */
    abstract public function defaultScopes();

    public function getResourceOwner()
    {
        return $this->provider->getResourceOwner($this->getAccessToken());
    }

    /**
     * Разменивает код авторизации на токен доступа
     *
     * @param string $code код авторизации
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function grantAuthorizationCode($code)
    {
        return $this->provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);
    }

    /**
     * Возобновляет токен доступа
     *
     * @param AccessToken $access_token старый токен
     * @return AccessToken|AccessTokenInterface новый токен
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function grantRefreshToken($access_token)
    {
        return $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $access_token->getRefreshToken()
        ]);
    }

    /**
     * Получает токен доступа для личного кабинета. Полученный токен надо сразу использовать для авторизации пользователя в его личном кабинете.
     *
     * @param AccessToken $access_token действующий токен
     * @return AccessToken|AccessTokenInterface токен доступа к личному кабинету
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function grantUserOffice($access_token)
    {
        return $this->provider->getAccessToken('user_office', [
            'token' => $access_token->getToken()
        ]);
    }

    /**
     * Получает токен доступа по логину и паролю
     *
     * @param string $username логин
     * @param string $password пароль
     * @param string|array|null $scope
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function grantPassword($username, $password, $scope = null)
    {
        $options = [
            'username' => $username,
            'password' => $password
        ];
        $options['scope'] = $scope;
        return $this->provider->getAccessToken('password', $options);
    }

    /**
     * Получает токен доступа для приложения. Полученный токен доступа вы должны сохранить где-нибудь в базе данных на веки вечные, и не забывать возобновлять его по необходимости.
     *
     * @param string|array|null $scope
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws \UAC\Exception\IdentityProviderException
     */
    public function grantClientCredentials($scope = null)
    {
        $options = [];
        $options['scope'] = $scope;
        return $this->provider->getAccessToken('client_credentials', $options);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'api':
                return new Api\Facade($this->provider, $this->getAccessToken());
            case 'provider':
                return $this->provider;
        }
        return null;
    }

    /**
     * @param bool $runInPopup
     */
    public function setRunInPopup($runInPopup)
    {
        StateContext::getInstance()->setPopup($runInPopup);
    }
}
