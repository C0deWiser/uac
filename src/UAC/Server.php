<?php

namespace Codewiser\UAC;

use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use Codewiser\UAC\Exception\IdentityProviderException;
use Codewiser\UAC\Model\User;

/**
 * OAuth сервис-провайдер
 * @package UAC
 */
class Server extends \League\OAuth2\Client\Provider\GenericProvider
{
    protected $urlServer;

//    public function __construct(array $options = [], array $collaborators = [])
//    {
//        parent::__construct($options, $collaborators);
//        if (isset($options['urlServer'])) {
//            $this->urlServer = $options['urlServer'];
//        }
//    }

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

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    public function getServerUrl()
    {
        return $this->urlServer;
    }

    /**
     * @inheritdoc
     * @throws IdentityProviderException
     */
    public function getAccessToken($grant, array $options = [])
    {
        if (!empty($options['scope']) && is_array($options['scope'])) {
            $separator = $this->getScopeSeparator();
            $options['scope'] = implode($separator, $options['scope']);
        }

        return parent::getAccessToken($grant, $options);
    }

    /**
     * Делает запрос к OAuth-серверу, чтобы проверить переданный токен
     * @param string $token
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function introspectToken($token)
    {
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $token
        ];

        $method = 'POST';
        $url = $this->urlServer . '/token_info';

        $request = $this->getRequest($method, $url, [
            'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($params)
        ]);

        return $this->getParsedResponse($request);
    }

    /**
     * Возвращает HTML-код личного кабинета пользователя
     * @param AccessToken $token
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeHtml(AccessToken $token)
    {
        $url = $this->urlServer . '/user-office/v1';
        $request = $this->getRequest('POST', $url . '/get', [
            'headers' => ['Authorization' => $token->getToken()]
        ]);
        return $this->getParsedResponse($request);
    }
    /**
     * Возвращает стили личного кабинета пользователя
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeCss()
    {
        $url = $this->urlServer . '/user-office/v1';
        $request = $this->getRequest('GET', $url . '/get-css');
        return $this->getParsedResponse($request);
    }
    /**
     * Возвращает скрипты личного кабинета пользователя
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getOnlineOfficeJs()
    {
        $url = $this->urlServer . '/user-office/v1';
        $request = $this->getRequest('GET', $url . '/get-js');
        return $this->getParsedResponse($request);
    }

    /**
     * Builds the deauthorization URL.
     *
     * @param array $options
     * @return string DeAuthorization URL
     */
    public function getDeauthorizationUrl(array $options = [])
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
        return new User($response['me']);
    }

    /**
     * Осуществляет запрос по любому данному адресу
     *
     * @param string $method метод запроса (GET, POST, PUT, DELETE)
     * @param string $requestUri path и query-string
     * @param AccessToken $token токен доступа
     * @return array
     * @throws IdentityProviderException
     */
    public function fetchResource($method, $requestUri, AccessToken $token)
    {
        $request = $this->getAuthenticatedRequest($method, $this->urlServer . $requestUri, $token);

        $response = $this->getParsedResponse($request);

        if (false === is_array($response)) {
            throw new \UnexpectedValueException(
                'Invalid response received from Authorization Server. Expected JSON.'
            );
        }

        return $response;
    }


}
