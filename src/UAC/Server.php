<?php

namespace Codewiser\UAC;

use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Codewiser\UAC\Exception\IdentityProviderException;
use Codewiser\UAC\Model\ResourceOwner;
use Psr\Log\LoggerInterface;

/**
 * OAuth сервис-провайдер
 * @package UAC
 */
class Server extends \League\OAuth2\Client\Provider\GenericProvider
{
    protected string $urlServer;

    protected ?string $urlLegacyServer = null;

    protected string $urlTokenIntrospection;

    protected string $locale = 'ru';

    protected array $resourceOwnerWith = [];

    protected ?LoggerInterface $logger = null;

    public function setLogger(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale)
    {
        $this->locale = $locale;
    }

    public function setResourceOwnerWith(string $with): void
    {
        $this->resourceOwnerWith[] = $with;
    }

    protected function getAllowedClientOptions(array $options): array
    {
        return ['timeout', 'proxy', 'verify'];
    }

    /**
     * @inheritdoc
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        try {
            parent::checkResponse($response, $data);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            $error_description = !empty($data['error_description']) ? $data['error_description'] : '';
            $error_uri = !empty($data['error_uri']) ? $data['error_uri'] : '';

            throw new IdentityProviderException($e->getMessage(), $e->getCode(), $e->getResponseBody(), $error_description, $error_uri);
        }
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getServerUrl(): string
    {
        return $this->urlServer;
    }

    /**
     * @inheritdoc
     */
    public function getAccessToken($grant, array $options = [])
    {
        if (!empty($options['scope']) && is_array($options['scope'])) {
            $separator = $this->getScopeSeparator();
            $options['scope'] = implode($separator, $options['scope']);
        }

        return parent::getAccessToken($grant, $options);
    }

    protected function createRequest($method, $url, $token, array $options): RequestInterface
    {
        $request = parent::createRequest($method, $url, $token, $options);

        if ($this->logger) {
            $this->logger->debug('UAC create request ' . $request->getMethod() . ' ' . $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => $request->getBody()
            ]);
        }

        return $request;
    }

    public function getRequest($method, $url, array $options = []): RequestInterface
    {
        $request = parent::getRequest($method, $url, $options);

        if ($this->logger) {
            $this->logger->debug('UAC get request ' . $request->getMethod() . ' ' . $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => $request->getBody()
            ]);
        }

        return $request;
    }

    protected function parseResponse(ResponseInterface $response)
    {
        if ($this->logger) {
            $this->logger->debug('UAC response ' . $response->getStatusCode(), [
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()
            ]);
        }

        return parent::parseResponse($response);
    }

    /**
     * Делает запрос к OAuth-серверу, чтобы проверить переданный токен
     * @param string $token
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function introspectToken(string $token)
    {
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $token
        ];

        $method = 'POST';
        $url = $this->urlTokenIntrospection;

        $request = $this->getRequest($method, $url, [
            'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($params)
        ]);

        return $this->getParsedResponse($request);
    }

    /**
     * Возвращает HTML-код личного кабинета пользователя
     * @param AccessToken $token
     * @param string $locale язык
     * @param null|string $logout_url локальный роут для деавторизации пользователя
     * @param null|string $tickets_endpoint полный адрес эндопоинта api билетов
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeHtml(AccessToken $token, $locale, $logout_url = null, $tickets_endpoint = null)
    {
        if (!$this->urlLegacyServer) {
            throw new \Exception('Set up Connector::$urlLegacyServer first');
        }

        $query = [
            'locale' => $locale,
        ];
        if ($logout_url) {
            $query['logout_url'] = $logout_url;
        }
        if ($tickets_endpoint) {
            $query['tickets_endpoint'] = $tickets_endpoint;
        }

        $url = $this->urlLegacyServer . '/user-office/v1';
        $request = $this->getRequest('POST', $url . '/get?' . http_build_query($query),
            [
                'headers' => ['Authorization' => $token->getToken()]
            ]);
        return $this->getParsedResponse($request);
    }

    /**
     * Возвращает стили личного кабинета пользователя
     * @param string $locale
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeCss($locale)
    {
        if (!$this->urlLegacyServer) {
            throw new \Exception('Set up Connector::$urlLegacyServer first');
        }

        $url = $this->urlLegacyServer . '/user-office/v1';
        $request = $this->getRequest('GET', $url . '/get-css?locale=' . $locale);
        return $this->getParsedResponse($request);
    }

    /**
     * Возвращает скрипты личного кабинета пользователя
     * @param string $locale
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeJs($locale)
    {
        if (!$this->urlLegacyServer) {
            throw new \Exception('Set up Connector::$urlLegacyServer first');
        }

        $url = $this->urlLegacyServer . '/user-office/v1';
        $request = $this->getRequest('GET', $url . '/get-js?locale=' . $locale);
        return $this->getParsedResponse($request);
    }

    /**
     * Builds the deauthorization URL.
     *
     * @param array $options
     * @return string DeAuthorization URL
     */
    public function getDeauthorizationUrl(array $options = []): string
    {
        $base = $this->getBaseAuthorizationUrl();
        $params = $this->getAuthorizationParameters($options);
        $params['response_type'] = 'leave';
        $query = $this->getAuthorizationQuery($params);

        return $this->appendQuery($base, $query);
    }

    /**
     * @inheritdoc
     */
    protected function createResourceOwner(array $response, \League\OAuth2\Client\Token\AccessToken $token)
    {
        if (isset($response['data'])) {
            return new ResourceOwner($response['data'], @$response['rules']);
        } else {
            throw new IdentityProviderException("No resource owner info", 401, $response);
        }
    }

    /**
     * Осуществляет запрос по любому данному адресу
     *
     * @param string $method метод запроса (GET, POST, PUT, DELETE)
     * @param string $requestUri path и query-string
     * @param AccessTokenInterface $token токен доступа
     * @return array
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function fetchResource(string $method, string $requestUri, AccessTokenInterface $token, array $options = []): array
    {
        $request = $this->getAuthenticatedRequest($method, $this->urlServer . $requestUri, $token, $options);

        $response = $this->getParsedResponse($request);

        if (false === is_array($response)) {
            throw new \UnexpectedValueException(
                'Invalid response received from Authorization Server. Expected JSON.'
            );
        }

        return $response;
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        $query = [
            'locale' => $this->locale
        ];

        if ($this->resourceOwnerWith) {
            $query['with'] = implode(',', $this->resourceOwnerWith);
        }

        return parent::getResourceOwnerDetailsUrl($token) . '?' . http_build_query($query);
    }
}
