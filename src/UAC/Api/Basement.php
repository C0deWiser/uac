<?php

namespace Codewiser\UAC\Api;

use League\OAuth2\Client\Token\AccessToken;
use Codewiser\UAC\Server;

abstract class Basement
{
    protected $prefix = 'api';
    protected $version = 'v3.0';
    /**
     * @var Server
     */
    protected $provider;
    /**
     * @var AccessToken
     */
    protected $accessToken;

    public function __construct(Server $provider = null, AccessToken $accessToken = null)
    {
        $this->provider = $provider;
        $this->accessToken = $accessToken;
    }

    /**
     * URL данного api-метода
     * @param array $query
     * @return string
     */
    public function endpoint(array $query = [])
    {
        $query = $query ? http_build_query($query) : null;

        $path = [];
        $path[] = $this->prefix;
        $path[] = $this->version;
        if ($this->method()) {
            $path[] = $this->method();
        }
        $path = implode('/', $path);

        return "/{$path}/" . ($query ? '?' . $query : '');
    }

    /**
     * Название метода
     * @return string
     */
    abstract protected function method();
}
