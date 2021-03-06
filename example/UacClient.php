<?php

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Codewiser\UAC\AbstractClient;
use Codewiser\UAC\Connector;
use Codewiser\UAC\Logger;
use Codewiser\UAC\Model\ResourceOwner;

class UacClient extends AbstractClient
{
    protected static $client;

    public static function instance()
    {
        if (self::$client) {
            return self::$client;
        }

        $connector = new Connector(
            getenv('OAUTH_SERVER_URL'),
            getenv('CLIENT_ID'),
            getenv('CLIENT_SECRET'),
            getenv('REDIRECT_URI'),
            new Context()
        );
        $connector->verify = false;

        self::$client = new static($connector);
        return self::$client;
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
        return [
            'phone',
            'email',
            'user_read',
            'user_write',
            'user_phone',
            'user_password',
            'user_address',
            'user_cars',
            'user_subscriptions'
        ];
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
