<?php


namespace Codewiser\UAC;


use Codewiser\UAC\Contracts\CacheContract;
use Codewiser\UAC\Exception\Api\InvalidTokenException;
use Codewiser\UAC\Exception\Api\RequestException;
use Codewiser\UAC\Exception\IdentityProviderException;
use Codewiser\UAC\Model\ResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

class ApiRequest
{
    protected Server $provider;
    protected CacheContract $cache;
    protected array $headers;
    protected array $parameters;
    protected ?string $token;

    /**
     * ApiRequest constructor.
     *
     * @param Server $provider
     * @param array $headers
     * @param array $parameters
     * @param CacheContract $cache
     */
    public function __construct(Server $provider, array $headers, array $parameters, CacheContract $cache)
    {
        $this->provider = $provider;
        $this->cache = $cache;

        foreach ($headers as $name => $header) {
            $headers[strtolower($name)] = $header;
        }

        $this->headers = $headers;
        $this->parameters = $parameters;

        $this->token = $this->extractToken();
    }

    /**
     * Достает токен из запроса.
     */
    protected function extractToken(): ?string
    {
        $token = null;

        if (isset($this->headers['authorization'])) {
            if (strpos($this->headers['authorization'], 'Bearer') === 0) {
                $token = trim(substr($this->headers['authorization'], 7));
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
     * @see https://pass.fc-zenit.ru/docs/oauth/token-introspection-endpoint.html
     * @return TokenIntrospection
     * @throws RequestException
     */
    public function introspect(): TokenIntrospection
    {
        $this->validate();

        $key = 'introspected-' . $this->token;

        if ($introspected = $this->cache->get($key)) {
            return $introspected;
        }

        try {
            $introspected = new TokenIntrospection($this->provider->introspectToken($this->token));
        } catch (\Exception $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        $timeout = 60 * 60 * 24; // 1 день
        $this->cache->set($key, $introspected, $timeout);

        return $introspected;
    }

    /**
     * Возвращает профиль владельца токена.
     *
     * @return ResourceOwner|ResourceOwnerInterface
     * @throws RequestException
     */
    public function user(): ?ResourceOwnerInterface
    {
        $this->validate();

        $key = 'resource-owner-' . $this->token;

        if ($user = $this->cache->get($key)) {
            return $user;
        }

        try {
            $user = $this->provider->getResourceOwner(new AccessToken(['access_token' => $this->token]));
        } catch (IdentityProviderException $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        $timeout = 60 * 60 * 24; // 1 день
        $this->cache->set($key, $user, $timeout);

        return $user;
    }

    /**
     * Возвращает токен из запроса.
     */
    public function token(): ?string
    {
        return $this->token;
    }

    /**
     * Формирует и отправляет заголовки ответа на запрос к API с ошибкой
     *
     * @param \Exception $e
     */
    public function respondWithError(\Exception $e)
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
