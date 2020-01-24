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
    /** @var Server */
    protected $provider;

    /** @var ContextManager */
    protected $context;

    protected $is_debug = true;

    public function __construct(Connector $connector, $options = [])
    {
        $this->provider = new Server($connector->toArray(), (array)$connector->collaborators);
        $this->context = $connector->context;

        $this->is_debug = @$options['is_debug'] ? true : false;

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
     * @param string $returnPath
     */
    public function requireAuthorization($returnPath, $scope = null)
    {
        if (!$this->hasAccessToken()) {
            $this->startAuthorization($returnPath, $scope);
        }
    }

    /**
     * Формирует адрес авторизации, запоминает контекст, возвращает url
     * @param string $returnPath
     * @param array|string|null $scope
     * @return string
     */
    public function getAuthorizationUrl($returnPath, $scope = null)
    {
        $options = [];
        $options['scope'] = $scope ?: $this->defaultScopes();

        $url = $this->provider->getAuthorizationUrl($options);

        $this->context->state = $this->provider->getState();
        $this->context->response_type = 'code';
        $this->context->return_path = $returnPath;

        $this->log('Prepare Authorization', ['url' => $url, 'context' => $this->context->toArray()]);

        return $url;
    }

    /**
     * Формирует адрес деавторизации, запоминает контекст, возвращает url
     * @param $returnPath
     * @return string
     */
    public function getDeauthorizationUrl($returnPath)
    {
        $url = $this->provider->getDeauthorizationUrl();

        $this->context->state = $this->provider->getState();
        $this->context->response_type = 'leave';
        $this->context->return_path = $returnPath;

        $this->log('Prepare De-Authorization', ['url' => $url, 'context' => $this->context->toArray()]);

        $this->unsetAccessToken();
        $this->deauthorizeResourceOwner();

        return $url;
    }

    /**
     * Возвращает авторизованного пользователя на страницу, откуда была потребована авторизация
     *
     * @param string $finally альтернативный адрес возврата (если в истории ничего нет)
     * @return void если метод завершился, значит перенаправление не состоялось
     */
    public function finishOauthProcess($finally)
    {
        if (@$this->context->run_in_popup) {
            echo "<script>window.close();</script>";
            $this->context->clear();
            die();
        } else {
            $return = @$this->context->return_path ?: $finally;
            $this->context->clear();

            $this->log("return path: {$return}");

            header("Location: {$return}");
            exit();
        }
    }

    /**
     * OAuth callback
     *
     * Универсальный коллбэк
     * @param array $request
     * @param array|string|null $scope
     * @throws IdentityProviderException
     * @throws OauthResponseException
     */
    public function callbackController(array $request, $scope = null)
    {

        $this->log('Callback', ['request' => $request, 'context' => $this->context->toArray()]);

        // Сразу обработаем ошибку
        if (isset($request['error'])) {
            throw new OauthResponseException($request['error'], @$request['error_description'], @$request['error_uri']);
        }

        if (isset($request['state'])) {

            if (@$this->context->state != $request['state']) {
                // Подделка!
                $this->context->clear();
                exit('Invalid state');
            }

            if (@$this->context->response_type == 'leave') {
                // Ходили деавторизовываться на сервер, разавторизуемся и тут
                $this->log("De-Authorization");
                $this->deauthorizeResourceOwner();

            } elseif (@$this->context->response_type == 'code' && isset($request['code'])) {
                // Это авторизация по коду

                $this->log("Got code: {$request['code']}");
                $access_token = $this->grantAuthorizationCode($request['code']);
                $this->log("Got token: {$access_token->getToken()}");

                $this->setAccessToken($access_token);
                $this->authorizeResourceOwner($resource = $this->provider->getResourceOwner($access_token));

            } else {

                // Не должно нас тут быть...
                $this->context->clear();
                exit('Invalid request');
            }
        }
    }

    /**
     * Должен сохранить токен в сессионном хранилище
     * @param AccessToken|AccessTokenInterface $accessToken
     */
    protected function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->context->access_token = serialize($accessToken);
    }

    /**
     * Должен достать токен из сессионного хранилища
     * @return AccessToken|AccessTokenInterface|null $accessToken
     */
    public function getAccessToken()
    {
        return isset($this->context->access_token) ? unserialize($this->context->access_token) : null;
    }

    /**
     * Должен удалить токен из сессионного хранилища
     * @return void
     */
    protected function unsetAccessToken()
    {
        if (isset($this->context->access_token)) {
            unset($this->context->access_token);
        }
    }

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
     * @throws IdentityProviderException
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
     * @throws IdentityProviderException
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
     * @throws IdentityProviderException
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
     * @throws IdentityProviderException
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
     * @throws IdentityProviderException
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
        $this->context->run_in_popup = $runInPopup;
    }
}
