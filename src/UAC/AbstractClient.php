<?php

namespace Codewiser\UAC;

use Codewiser\UAC\Exception\Api\InvalidTokenException;
use Codewiser\UAC\Exception\Api\RequestException;
use Codewiser\UAC\Model\UserOffice;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Codewiser\UAC\Api\Facade;
use Codewiser\UAC\Exception\OauthResponseException;
use Codewiser\UAC\Model\ResourceOwner;
use Psr\Log\LoggerInterface;

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

    /** @var AbstractCache|null */
    protected $cache;

    protected $options = [];

    protected $make_redirect_when_invalid_state = true;

    /**
     * Языковая версия (для вещей, где это имеет значение
     * @var string
     */
    protected $locale = 'ru';

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    public function __construct(Connector $connector, $logger = null)
    {
        $this->provider = new Server($connector->toArray(), (array)$connector->collaborators);
        $this->context = $connector->context;
        $this->cache = $connector->cache;
        $this->logger = $logger;
        $this->provider->setLogger($logger);
        $this->provider->setLocale($this->locale);

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
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $options = array_merge(
            [
                'scope' => $this->defaultScopes(),
                'authorization_hint' => $this->defaultAuthorizationHint()
            ],
            (array)$this->options
        );

        $url = $this->provider->getAuthorizationUrl($options);

        $this->context->state = $this->provider->getState();
        $this->context->response_type = 'code';
        $this->context->oauth2pkceCode = $this->provider->getPkceCode();

        if ($this->logger) {
            $this->logger->info('UAC Prepare Authorization', ['url' => $url, 'context' => $this->context->toArray()]);
        }

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

        if ($this->logger) {
            $this->logger->info('UAC Prepare De-Authorization', ['url' => $url, 'context' => $this->context->toArray()]);
        }

        return $url;
    }

    /**
     * Запоминает в сессионном хранилище адрес страницы, куда нужно будет вернуть пользователя после завершения oauth-процесса
     * @param string $returnPath
     * @return AbstractClient
     */
    public function setReturnPath($returnPath)
    {
        $this->context->return_path = $returnPath;

        if ($this->logger) {
            $this->logger->debug("UAC Set return_path: {$returnPath}");
        }

        return $this;
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

        if ($this->logger) {
            $this->logger->debug("UAC Got return_path: {$returnPath}");
        }

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
        if ($this->logger) {
            $this->logger->info('UAC Callback', ['request' => $request]);
        }

        if (isset($request['state'])) {

            if (!$this->context->restoreContext($request['state'])) {
                // Подделка!
                if ($this->logger) {
                    $this->logger->warning("UAC Invalid state:", ['request' => $request]);
                }
                if ($this->make_redirect_when_invalid_state) {
                    header('Location: /?InvalidState');
                }
                exit('Invalid state');
            }

            $this->provider->setPkceCode($this->context->oauth2pkceCode);

            if ($this->logger) {
                $this->logger->debug('UAC Got context', $this->context->toArray());
            }

            if (isset($request['error'])) {
                if ($this->logger) {
                    $this->logger->error("UAC Got error: {$request['error']}", [
                        'description' => @$request['error_description'],
                        'uri' => @$request['error_uri']
                    ]);
                }
                throw new OauthResponseException($request['error'], @$request['error_description'], @$request['error_uri']);
            }

            if ($this->context->response_type == 'leave') {
                // Ходили деавторизовываться на сервер, разавторизуемся и тут
                if ($this->logger) {
                    $this->logger->debug("UAC Has response_type: leave");
                }
                $this->unsetAccessToken();
                $this->deauthorizeResourceOwner();
                if ($this->logger) {
                    $this->logger->debug("UAC User signed out");
                }

            } elseif ($this->context->response_type == 'code' && isset($request['code'])) {
                // Это авторизация по коду

                if ($this->logger) {
                    $this->logger->debug("UAC Has response_type: code");
                    $this->logger->debug("UAC Got code: {$request['code']}");
                }

                $access_token = $this->grantAuthorizationCode($request['code']);
                if ($this->logger) {
                    $this->logger->debug("UAC Got token: {$access_token->getToken()}");
                }

                $this->setAccessToken($access_token);
                $resource = $this->provider->getResourceOwner($access_token);
                if ($this->logger) {
                    $this->logger->debug("UAC Got resource owner", $resource->toArray());
                }

                $this->authorizeResourceOwner($resource);
                if ($this->logger) {
                    $this->logger->debug("UAC User signed in");
                }

            } else {

                // Не должно нас тут быть...
                if ($this->logger) {
                    $this->logger->warning("UAC I dont know what to do:", ['request' => $request, 'context' => $this->context->toArray()]);
                }
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
     * @param ResourceOwner|ResourceOwnerInterface $user
     */
    abstract protected function authorizeResourceOwner($user);

    /**
     * Должен локально разавторизовать пользователя
     */
    abstract protected function deauthorizeResourceOwner();

    /**
     * Лог
     *
     * @return LoggerInterface|null
     */
    public function logger()
    {
        return $this->logger;
    }

    /**
     * Список скоупов, которые по умолчанию будут запрашиваться у сервера во время авторизации
     *
     * @return string|array|null
     */
    abstract public function defaultScopes();

    /**
     * Заголовок, который будет показан пользователю во время процесса авторизации
     *
     * @return string|null
     */
    public function defaultAuthorizationHint()
    {
        return null;
    }

    /**
     * Возвращает профиль авторизованного пользователя.
     *
     * @return ResourceOwner|ResourceOwnerInterface
     */
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
    public function introspectToken($access_token)
    {
        return new TokenIntrospection($this->provider->introspectToken($access_token->getToken()));
    }

    /**
     * Входящий запрос к API
     *
     * @param array $headers заголовки запроса (в них может быть Bearer токен)
     * @param array $parameters параметры запроса (в них может быть access_token)
     * @return ApiRequest
     */
    public function apiRequest($headers, $parameters)
    {
        return new ApiRequest($this->provider, $headers, $parameters, $this->cache);
    }

    /**
     * Авторизует поступивший запрос к API
     *
     * @see http://oauth.fc-zenit.ru/doc/api/fundamentals/request-validation/
     * @param array $headers заголовки запроса (в них может быть Bearer токен)
     * @param array $parameters параметры запроса (в них может быть access_token)
     * @return TokenIntrospection
     * @throws RequestException
     * @deprecated use apiRequest()
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

        $info = $this->introspectToken(new AccessToken(['access_token' => $token]));
        if (!$info->isActive()) {
            throw new InvalidTokenException();
        }
        return $info;
    }

    /**
     * Формирует заголовки ответа на запрос к API с ошибкой
     *
     * @param RequestException $e
     * @deprecated use apiRequest()
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
     * Установить список scope для следующего процесса авторизации
     * @param string|array $scope
     * @return static
     */
    public function setScope($scope)
    {
        $this->options['scope'] = $scope;

        if ($this->logger)
            $this->logger->debug("Set scope: {$scope}");

        return $this;
    }

    /**
     * Установить заголовок процесса авторизации
     * @param string $hint
     * @return static
     */
    public function setAuthorizationHint($hint)
    {
        $this->options['authorization_hint'] = $hint;

        if ($this->logger)
            $this->logger->debug("Set authorization_hint: {$hint}");

        return $this;
    }

    /**
     * Установить адрес вебхука (oauth-сервер будет уведомлять о событиях).
     *
     * @param $webhook
     * @return $this
     */
    public function setWebhook($webhook)
    {
        $this->options['webhook_uri'] = $webhook;

        if ($this->logger)
            $this->logger->debug("Set webhook_uri: {$webhook}");

        return $this;
    }

    /**
     * Устанавливает значение аргумента `prompt`
     * @see https://openid.net/specs/openid-connect-core-1_0.html#AuthRequest
     * @param string $prompt
     * @return static
     */
    public function setPrompt($prompt)
    {
        $this->options['prompt'] = $prompt;

        if ($this->logger)
            $this->logger->debug("Set prompt: {$prompt}");

        return $this;
    }

    /**
     * Запоминает в сессионном хранилище, что oauth-процесс запущен в popup-окне
     * @param bool $runInPopup
     * @return static
     */
    public function setRunInPopup($runInPopup)
    {
        $this->context->run_in_popup = $runInPopup;

        if ($this->logger)
            $this->logger("Set run_in_popup: {$runInPopup}");

        return $this;
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
            if ($this->logger) {
                $this->logger->debug("Closing popup");
            }
            echo "<script>window.close();</script>";
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $locale
     * @return static
     */
    public function setLocale($locale)
    {
        $this->options['locale'] = $locale;
        $this->context->locale = $locale;
        $this->locale = $locale;
        $this->provider->setLocale($this->locale);
        return $this;
    }

    /**
     * Возвращает личный кабинет авторизованного пользователя: html, стили и скрипты.
     *
     * Полученные данные нужно вставить на страницу с адресом /elk !!!
     *
     * Стили и скрипты встроить в подвал.
     *
     * Требуется подключенный jQuery ($)
     *
     * @param null|string $logout_url локальный роут для деавторизации пользователя
     * @param null|string $tickets_endpoint полный адрес эндопоинта api билетов
     * @return UserOffice
     * @throws IdentityProviderException
     */
    public function getOnlineOffice($logout_url = null, $tickets_endpoint = null)
    {
        $html = $this->provider->getOnlineOfficeHtml($this->getAccessToken(), $this->locale, $logout_url, $tickets_endpoint);

        return new UserOffice(
            $html,
            $this->provider->getOnlineOfficeCss($this->locale),
            $this->provider->getOnlineOfficeJs($this->locale)
        );
    }

    public function setResourceOwnerWith(string $with): self
    {
        $this->provider->setResourceOwnerWith($with);

        return $this;
    }

    /**
     * @param bool $make_redirect_when_invalid_state
     */
    public function setMakeRedirectWhenInvalidState(bool $make_redirect_when_invalid_state)
    {
        $this->make_redirect_when_invalid_state = $make_redirect_when_invalid_state;
    }
}
