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
            getenv('REDIRECT_URI')
        );

        return new static($connector, ['is_debug' => getenv('APP_DEBUG')]);
    }

    /**
     * @param User|ResourceOwnerInterface $user
     */
    protected function authorizeResourceOwner($user)
    {
        // TODO: Implement authorizeResourceOwner() method.
    }

    protected function deauthorizeResourceOwner()
    {
        // TODO: Implement deauthorizeResourceOwner() method.
    }

    public function log($message, array $context = [])
    {
        if ($this->is_debug) {
            Logger::instance()->info($message, $context);
        }
    }

    public function defaultScopes()
    {
        return 'phone';
    }

    public function requireAuthorization($returnPath)
    {
        if (!$this->hasAccessToken()) {
            header('Location: ' . $this->getAuthorizationUrl($returnPath));
            exit;
        }
    }
}
