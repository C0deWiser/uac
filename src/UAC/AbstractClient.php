<?php

namespace Codewiser\UAC;

use Codewiser\UAC\Exception\Api\InvalidTokenException;
use Codewiser\UAC\Exception\Api\RequestException;
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

    /** @var AbstractContext */
    protected $context;

    public function __construct(Connector $connector)
    {
        $this->provider = new Server($connector->toArray(), (array)$connector->collaborators);
        $this->context = $connector->context;

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
     * Формирует адрес авторизации, запоминает контекст, возвращает url
     * @param array|string|null $scope
     * @return string
     */
    public function getAuthorizationUrl($scope = null)
    {
        $options = [];
        $options['scope'] = $scope ?: $this->defaultScopes();

        $url = $this->provider->getAuthorizationUrl($options);

        $this->context->state = $this->provider->getState();
        $this->context->response_type = 'code';

        $this->log('Prepare Authorization', ['url' => $url, 'context' => $this->context->toArray()]);

        return $url;
    }

    /**
     * Формирует адрес деавторизации, запоминает контекст, возвращает url
     * @return string
     */
    public function getDeauthorizationUrl()
    {
        $url = $this->provider->getDeauthorizationUrl();

        $this->context->state = $this->provider->getState();
        $this->context->response_type = 'leave';

        $this->log('Prepare De-Authorization', ['url' => $url, 'context' => $this->context->toArray()]);

        return $url;
    }

    /**
     * Запоминает в сессионном хранилище адрес страницы, куда нужно будет вернуть пользователя после завершения oauth-процесса
     * @param string $returnPath
     */
    public function setReturnPath($returnPath)
    {
        $this->context->return_path = $returnPath;

        $this->log("Set return_path: {$returnPath}");
    }

    /**
     * Возвращает адрес страницы, куда нужно вернуть пользователя после oauth-процесса
     *
     * @param string $finally альтернативный адрес возврата (если в истории ничего нет)
     * @return string
     */
    public function getReturnPath($finally)
    {
        $returnPath = $this->context->return_path ?: $finally;

        $this->log("Got return_path: {$returnPath}");

        return $returnPath;
    }

    /**
     * OAuth callback
     *
     * Универсальный коллбэк
     * @param array $request
     * @throws IdentityProviderException
     * @throws OauthResponseException
     */
    public function callbackController(array $request)
    {
        $this->log('Callback', ['request' => $request]);

        if (isset($request['state'])) {

            if (!$this->context->restoreContext($request['state'])) {
                // Подделка!
                $this->log("State mismatch:", ['request' => $request]);
                exit('Invalid state');
            }

            $this->log('Got context', $this->context->toArray());

            if (isset($request['error'])) {
                $this->log("Got error: {$request['error']}", [
                    'description' => @$request['error_description'],
                    'uri' => @$request['error_uri']
                ]);
                throw new OauthResponseException($request['error'], @$request['error_description'], @$request['error_uri']);
            }

            if ($this->context->response_type == 'leave') {
                // Ходили деавторизовываться на сервер, разавторизуемся и тут
                $this->log("Has response_type: leave");
                $this->unsetAccessToken();
                $this->deauthorizeResourceOwner();
                $this->log("User signed out");

            } elseif ($this->context->response_type == 'code' && isset($request['code'])) {
                // Это авторизация по коду

                $this->log("Has response_type: code");

                $this->log("Got code: {$request['code']}");
                $access_token = $this->grantAuthorizationCode($request['code']);
                $this->log("Got token: {$access_token->getToken()}");

                $this->setAccessToken($access_token);
                $resource = $this->provider->getResourceOwner($access_token);
                $this->log("Got resource owner", $resource->toArray());

                $this->authorizeResourceOwner($resource);
                $this->log("User signed in");

            } else {

                // Не должно нас тут быть...
                $this->log("I dont know what to do:", ['request' => $request, 'context' => $this->context->toArray()]);
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
        $accessToken = isset($this->context->access_token) ? unserialize($this->context->access_token) : null;

        if ($accessToken && (!is_object($accessToken) || !($accessToken instanceof AccessTokenInterface))) {
            $accessToken = null;
        }

        return $accessToken;
    }

    /**
     * Должен удалить токен из сессионного хранилища
     * @return void
     */
    public function unsetAccessToken()
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
     * Должен локально разавторизовать пользователя
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
     * Список скоупов, которые по умолчанию будут запрашиваться у сервера во время авторизации
     *
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

    /**
     * Проверяет состояние токена
     *
     * @see http://oauth.fc-zenit.ru/doc/oauth/token-introspection-endpoint/
     * @param AccessToken $access_token
     * @return TokenIntrospection
     */
    public function tokenIntrospection($access_token)
    {
        return new TokenIntrospection($this->provider->introspectToken($access_token->getToken()));
    }

    /**
     * Авторизует поступивший запрос к API
     *
     * @see http://oauth.fc-zenit.ru/doc/api/fundamentals/request-validation/
     * @param array $headers заголовки запроса (в них может быть Bearer токен)
     * @param array $parameters параметры запроса (в них может быть access_token)
     * @return TokenIntrospection
     * @throws RequestException
     */
    public function apiRequestAuthorize($headers, $parameters)
    {
        $token = null;
        if (isset($headers['Authorization'])) {
            if (strpos($headers['Authorization'], 'Bearer') === 0) {
                $token = substr($headers['Authorization'], 7);
            }
        }
        if (!$token && isset($parameters['access_token'])) {
            $token = $parameters['access_token'];
        }

        if (!$token) {
            throw new RequestException();
        }

        $info = $this->tokenIntrospection(new AccessToken(['access_token' => $token]));
        if (!$info->isActive()) {
            throw new InvalidTokenException();
        }
        return $info;
    }

    /**
     * Формирует заголовки ответа на запрос к API с ошибкой
     *
     * @param RequestException $e
     */
    public function apiRespondWithError($e)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " " . $e->getHttpCode());

        $bearer = [];
        if ($i = $e->getRealm()) {
            $bearer[] = 'realm="' . $i . '"';
        }
        if ($i = $e->getScope()) {
            $bearer[] = 'scope="' . $i . '"';
        }
        if ($i = $e->getMessage()) {
            $bearer[] = 'error="' . $i . '"';
        }
        if ($i = $e->getDescription()) {
            $bearer[] = 'error_description="' . $i . '"';
        }
        if ($i = $e->getUri()) {
            $bearer[] = 'error_uri="' . $i . '"';
        }

        $bearer = implode(', ', $bearer);

        header("WWW-Authenticate: Bearer {$bearer}");
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
     * Запоминает в сессионном хранилище, что oauth-процесс запущен в popup-окне
     * @param bool $runInPopup
     */
    public function setRunInPopup($runInPopup)
    {
        $this->context->run_in_popup = $runInPopup;

        $this->log("Set run_in_popup: {$runInPopup}");
    }

    /**
     * Закрывает popup-окно, если oauth-процесс шёл в нём. Очищает после себя сессионное хранилище.
     *
     * @return bool если возвращает false, то нужно сделать перенаправление на адрес returnPath
     * @see self::getReturnPath()
     */
    public function closePopup()
    {
        if ($this->context->run_in_popup) {
            $this->log("Closing popup");
            echo "<script>window.close();</script>";
            return true;
        } else {
            return false;
        }
    }
}
