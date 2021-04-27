<?php


namespace Codewiser\UAC;


use Codewiser\UAC\Exception\Api\InvalidTokenException;
use Codewiser\UAC\Exception\Api\RequestException;
use Codewiser\UAC\Exception\IdentityProviderException;
use Codewiser\UAC\Model\User;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

class ApiRequest
{
    protected $provider;
    protected $cache;
    protected $headers;
    protected $parameters;
    protected $token;

    /**
     * ApiRequest constructor.
     *
     * @param Server $provider
     * @param array $headers
     * @param array $parameters
     * @param AbstractCache|null $cache
     */
    public function __construct(Server $provider, array $headers, array $parameters, $cache = null)
    {
        $this->provider = $provider;
        $this->cache = $cache;
        $this->headers = $headers;
        $this->parameters = $parameters;

        $this->token = $this->extractToken();
    }

    /**
     * Достает токен из запроса.
     *
     * @return string
     * @throws RequestException
     */
    protected function extractToken()
    {
        $token = null;

        if (isset($this->headers['Authorization'])) {
            if (strpos($this->headers['Authorization'], 'Bearer') === 0) {
                $token = substr($this->headers['Authorization'], 7);
            }
        }
        if (!$token && isset($this->parameters['access_token'])) {
            $token = $this->parameters['access_token'];
        }

        return $token;
    }

    /**
     * Удостоверяет наличие токена в запросе.
     *
     * @throws RequestException
     */
    public function validate()
    {
        if (!$this->token) {
            throw new RequestException();
        }
    }

    /**
     * Удостоверяет годность токена.
     *
     * @throws InvalidTokenException
     * @throws RequestException
     */
    public function authorize()
    {
        $info = $this->introspect();

        if (!$info->isActive()) {
            throw new InvalidTokenException();
        }
    }

    /**
     * Возвращает состояние токена.
     *
     * @see http://oauth.fc-zenit.ru/doc/oauth/token-introspection-endpoint/
     * @return TokenIntrospection
     * @throws RequestException
     */
    public function introspect()
    {
        $this->validate();

        $key = 'introspected-' . $this->token;

        if ($cache = $this->cache) {
            if ($introspected = $cache->get($key)) {
                return $introspected;
            }
        }

        try {
            $introspected = new TokenIntrospection($this->provider->introspectToken($this->token));
        } catch (\Exception $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        if ($cache) {
            $timeout = 60 * 60 * 24; // 1 день
            $cache->put($key, $introspected, $timeout);
        }

        return $introspected;
    }

    /**
     * Возвращает профиль владельца токена.
     *
     * @return User|ResourceOwnerInterface
     * @throws RequestException
     */
    public function user()
    {
        $this->validate();

        $key = 'resource-owner-' . $this->token;

        if ($cache = $this->cache) {
            if ($user = $cache->get($key)) {
                return $user;
            }
        }

        try {
            $user = $this->provider->getResourceOwner(new AccessToken(['access_token' => $this->token]));
        } catch (IdentityProviderException $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        if ($cache) {
            $timeout = 60 * 60 * 24; // 1 день
            $cache->put($key, $user, $timeout);
        }

        return $user;
    }

    /**
     * Возвращает токен из запроса.
     *
     * @return string
     */
    public function token()
    {
        return $this->token;
    }

    /**
     * Формирует заголовки ответа на запрос к API с ошибкой
     *
     * @param \Exception $e
     */
    public function respondWithError($e)
    {
        $httpCode = $e instanceof RequestException ? $e->getHttpCode() : $e->getCode();

        header($_SERVER["SERVER_PROTOCOL"] . " " . $httpCode);

        $bearer = [];

        if (method_exists($e, 'getRealm'))
            if ($i = $e->getRealm())
                $bearer[] = 'realm="' . $i . '"';

        if (method_exists($e, 'getScope'))
            if ($i = $e->getScope())
                $bearer[] = 'scope="' . $i . '"';

        if (method_exists($e, 'getMessage'))
            if ($i = $e->getMessage())
                $bearer[] = 'error="' . $i . '"';

        if (method_exists($e, 'getDescription'))
            if ($i = $e->getDescription())
                $bearer[] = 'error_description="' . $i . '"';

        if (method_exists($e, 'getUri'))
            if ($i = $e->getUri())
                $bearer[] = 'error_uri="' . $i . '"';

        $bearer = implode(', ', $bearer);

        header("WWW-Authenticate: Bearer {$bearer}");
    }


}