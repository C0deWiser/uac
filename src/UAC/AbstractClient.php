<?php

namespace Codewiser\UAC;

use Codewiser\UAC\Contracts\CacheContract;
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
 * @property-read Facade api Доступ к серверному API
 * @property-read Server provider
 */
abstract class AbstractClient
{
    protected Server $provider;

    /**
     * Session store.
     *
     * @var CacheContract
     */
    public CacheContract $session;

    /**
     * True cache.
     *
     * @var CacheContract
     */
    public CacheContract $cache;

    protected array $options = [];

    protected bool $make_redirect_when_invalid_state = true;

    /**
     * Языковая версия (для вещей, где это имеет значение).
     */
    protected string $locale = 'ru';

    protected ?LoggerInterface $logger;

    protected ContextManager $context;

    public function __construct(Connector $connector, LoggerInterface $logger = null)
    {
        $this->provider = new Server($connector->toArray(), $connector->collaborators);
        $this->session = $connector->session;
        $this->cache = $connector->cache;
        $this->logger = $logger;
        $this->provider->setLogger($logger);
        $this->provider->setLocale($this->locale);

        /*
         * Keep state in absolute cache
         */
        $this->context = new ContextManager($this->cache);

        $access_token = $this->getAccessToken();

        if ($access_token) {
            $access_token = $this->refreshAccessTokenIfRequired($access_token);
            if ($access_token) {
                $this->setAccessToken($access_token);
            } else {
                $this->unsetAccessToken();
            }
        }
    }

    /**
     * Формирует адрес авторизации, запоминает контекст, возвращает url.
     */
    public function getAuthorizationUrl(): string
    {
        $options = array_merge(
            [
                'scope' => $this->defaultScopes(),
                'authorization_hint' => $this->defaultAuthorizationHint()
            ],
            $this->options
        );

        $url = $this->provider->getAuthorizationUrl($options);

        $this->context->response_type = 'code';
        $this->context->state = $this->provider->getState();

        if ($this->logger) {
            $this->logger->info('UAC Prepare Authorization', ['url' => $url, 'context' => $this->context->toArray()]);
        }

        return $url;
    }

    /**
     * Формирует адрес деавторизации, запоминает контекст, возвращает url.
     */
    public function getDeauthorizationUrl(): string
    {
        $url = $this->provider->getDeauthorizationUrl();

        $this->context->response_type = 'leave';
        $this->context->state = $this->provider->getState();

        if ($this->logger) {
            $this->logger->info('UAC Prepare De-Authorization', ['url' => $url, 'context' => $this->context->toArray()]);
        }

        return $url;
    }

    /**
     * Запоминает в сессионном хранилище адрес страницы, куда нужно будет вернуть пользователя после завершения oauth-процесса.
     */
    public function setReturnPath(string $returnPath): self
    {
        $this->context->return_path = $returnPath;

        if ($this->logger) {
            $this->logger->debug("UAC Set return_path: {$returnPath}");
        }

        return $this;
    }

    /**
     * Возвращает адрес страницы, куда нужно вернуть пользователя после oauth-процесса.
     *
     * @param string $finally Альтернативный адрес возврата (если в истории ничего нет)
     * @return string
     */
    public function getReturnPath(string $finally): string
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
     * Должен сохранить токен в сессионном хранилище.
     */
    protected function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->session->set('access_token', $accessToken);
    }

    /**
     * Должен достать токен из сессионного хранилища.
     */
    public function getAccessToken(): ?AccessTokenInterface
    {
        $accessToken = $this->session->get('access_token');

        if ($accessToken && (!is_object($accessToken) || !($accessToken instanceof AccessTokenInterface))) {
            $accessToken = null;
        }

        return $accessToken;
    }

    /**
     * Должен удалить токен из сессионного хранилища.
     */
    public function unsetAccessToken()
    {
        $this->session->delete('access_token');
    }

    /**
     * Проверяет, получен ли токен доступа.
     */
    public function hasAccessToken(): bool
    {
        return $this->session->has('access_token');
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
     * Должен локально разавторизовать пользователя.
     */
    abstract protected function deauthorizeResourceOwner();

    public function logger(): ?LoggerInterface
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
     * Заголовок, который будет показан пользователю во время процесса авторизации.
     */
    public function defaultAuthorizationHint(): ?string
    {
        return null;
    }

    /**
     * Возвращает профиль авторизованного пользователя.
     */
    public function getResourceOwner(): ResourceOwnerInterface
    {
        return $this->provider->getResourceOwner($this->getAccessToken());
    }

    /**
     * Разменивает код авторизации на токен доступа.
     *
     * @see https://pass.fc-zenit.ru/docs/oauth/access-tokens.html#выпуск-токена-доступа-по-коду-авторизации
     * @param string $code Код авторизации
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws IdentityProviderException
     */
    public function grantAuthorizationCode(string $code): AccessTokenInterface
    {
        return $this->provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);
    }

    /**
     * Возобновляет токен доступа.
     *
     * @see https://pass.fc-zenit.ru/docs/oauth/access-tokens.html#перевыпуск-токена-доступа
     * @param AccessToken|AccessTokenInterface $access_token Старый токен
     * @return AccessToken|AccessTokenInterface Новый токен
     * @throws IdentityProviderException
     */
    public function grantRefreshToken(AccessTokenInterface $access_token): AccessTokenInterface
    {
        return $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $access_token->getRefreshToken()
        ]);
    }

    /**
     * Получает токен доступа по логину и паролю.
     *
     * @see https://pass.fc-zenit.ru/docs/oauth/access-tokens.html#выпуск-токена-доступа-по-паролю
     * @param string $username логин
     * @param string $password пароль
     * @param string|array|null $scope
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws IdentityProviderException
     */
    public function grantPassword(string $username, string $password, $scope = null): AccessTokenInterface
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
     * @see https://pass.fc-zenit.ru/docs/oauth/access-tokens.html#выпуск-токена-доступа-для-клиента
     * @param string|array|null $scope
     * @return AccessToken|AccessTokenInterface токен доступа
     * @throws IdentityProviderException
     */
    public function grantClientCredentials($scope = null): AccessTokenInterface
    {
        $options = [];
        $options['scope'] = $scope;
        return $this->provider->getAccessToken('client_credentials', $options);
    }

    /**
     * Проверяет состояние токена.
     *
     * @see https://pass.fc-zenit.ru/docs/oauth/token-introspection-endpoint.html
     * @param AccessToken|AccessTokenInterface $access_token
     * @return TokenIntrospection
     * @throws IdentityProviderException
     */
    public function introspectToken(AccessTokenInterface $access_token): TokenIntrospection
    {
        return new TokenIntrospection($this->provider->introspectToken($access_token->getToken()));
    }

    /**
     * Входящий запрос к API.
     *
     * @param array $headers Заголовки запроса (в них может быть Bearer токен)
     * @param array $parameters Параметры запроса (в них может быть access_token)
     * @return ApiRequest
     */
    public function apiRequest(array $headers, array $parameters): ApiRequest
    {
        return new ApiRequest($this->provider, $headers, $parameters, $this->cache);
    }

    /**
     * Авторизует поступивший запрос к API.
     *
     * @see https://pass.fc-zenit.ru/docs/api/fundamentals.html
     * @param array $headers Заголовки запроса (в них может быть Bearer токен)
     * @param array $parameters Параметры запроса (в них может быть access_token)
     * @return TokenIntrospection
     * @throws RequestException
     * @throws IdentityProviderException
     * @deprecated use apiRequest()
     */
    public function apiRequestAuthorize(array $headers, array $parameters): TokenIntrospection
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
    public function apiRespondWithError(RequestException $e)
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
     * Установить список scope для следующего процесса авторизации.
     *
     * @param string|array $scope
     * @return static
     */
    public function setScope($scope): self
    {
        $this->options['scope'] = $scope;

        if ($this->logger)
            $this->logger->debug("UAC set scope: {$scope}");

        return $this;
    }

    /**
     * Установить заголовок процесса авторизации.
     *
     * @param string|null $hint
     * @return static
     */
    public function setAuthorizationHint(?string $hint): self
    {
        $this->options['authorization_hint'] = $hint;

        if ($this->logger)
            $this->logger->debug("UAC set authorization_hint: {$hint}");

        return $this;
    }

    /**
     * Установить адрес вебхука (oauth-сервер будет уведомлять о событиях).
     *
     * @param string|null $webhook
     * @return $this
     */
    public function setWebhook(?string $webhook): self
    {
        $this->options['webhook_uri'] = $webhook;

        if ($this->logger)
            $this->logger->debug("UAC set webhook_uri: {$webhook}");

        return $this;
    }

    /**
     * Устанавливает значение аргумента `prompt`.
     *
     * @see https://openid.net/specs/openid-connect-core-1_0.html#AuthRequest
     * @param string|null $prompt
     * @return static
     */
    public function setPrompt(?string $prompt): self
    {
        $this->options['prompt'] = $prompt;

        if ($this->logger)
            $this->logger->debug("UAC set prompt: {$prompt}");

        return $this;
    }

    /**
     * Запоминает в сессионном хранилище, что oauth-процесс запущен в popup-окне.
     *
     * @param bool $runInPopup
     * @return static
     */
    public function setRunInPopup(bool $runInPopup): self
    {
        $this->context->run_in_popup = $runInPopup;

        if ($this->logger)
            $this->logger()->debug("UAC set run_in_popup: {$runInPopup}");

        return $this;
    }

    /**
     * Закрывает popup-окно, если oauth-процесс шёл в нём. Очищает после себя сессионное хранилище.
     *
     * @return bool Если возвращает false, то нужно сделать перенаправление на адрес returnPath
     * @see self::getReturnPath()
     */
    public function closePopup(): bool
    {
        if ($this->context->run_in_popup) {
            if ($this->logger) {
                $this->logger->debug("UAC closing popup");
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
    public function setLocale(string $locale): self
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
     * @param string|null $logout_url Локальный роут для деавторизации пользователя
     * @param string|null $tickets_endpoint Полный адрес эндопоинта api билетов
     * @return UserOffice
     * @throws IdentityProviderException
     * @deprecated
     */
    public function getOnlineOffice(string $logout_url = null, string $tickets_endpoint = null): UserOffice
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
     * @return self
     */
    public function setMakeRedirectWhenInvalidState(bool $make_redirect_when_invalid_state): self
    {
        $this->make_redirect_when_invalid_state = $make_redirect_when_invalid_state;

        return $this;
    }

    /**
     * Проверяет токен и выпускает новый, если данный истек.
     *
     * Возвращает старый токен, если он еще действует; новый токен, если он был обновлен; null — если обновление не удалось.
     *
     * @param AccessTokenInterface $access_token
     * @return AccessTokenInterface|null
     */
    public function refreshAccessTokenIfRequired(AccessTokenInterface $access_token): ?AccessTokenInterface
    {
        if ($access_token->getExpires() && $access_token->hasExpired()) {
            try {
                return $this->grantRefreshToken($access_token);
            } catch (IdentityProviderException $e) {
                return null;
            }
        } else {
            return $access_token;
        }
    }
}
