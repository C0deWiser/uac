<?php

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Codewiser\UAC\AbstractClient;
use Codewiser\UAC\Connector;
use Codewiser\UAC\Logger;
use Codewiser\UAC\Model\User;

class UacClient extends AbstractClient
{
    public static function instance()
    {
        $connector = new Connector(
            getenv('OAUTH_SERVER_URL'),
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('REDIRECT_URI'),
            new Context()
        );

        return new static($connector);
    }

    /**
     * {@inheritDoc}
     */
    protected function authorizeResourceOwner($user)
    {
        // Authorize local user
    }

    /**
     * {@inheritDoc}
     */
    protected function deauthorizeResourceOwner()
    {
        // Un-authorize local user
    }

    public function log($message, array $context = [])
    {
        Logger::instance()->info($message, $context);
    }

    public function defaultScopes()
    {
        return ['phone', 'email'];
    }

    /**
     * Отправим пользователя на oauth-сервер, если у нас нет токена
     * @param string $returnPath после авторизации вернем пользователя на этот адрес
     */
    public function requireAuthorization($returnPath)
    {
        if (!$this->hasAccessToken()) {
            if (isset($_SESSION['oauth-exception'])) {
                unset($_SESSION['oauth-exception']);
                header('HTTP/1.0 403 Forbidden');
                echo 'Authorization Required!';
                die();
            }
            $this->setReturnPath($returnPath);
            header('Location: ' . $this->getAuthorizationUrl());
            exit;
        }
    }
}
