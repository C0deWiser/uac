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

    protected $access_token = 'token_not_loaded';

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

    protected function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->access_token = $accessToken;

        $_SESSION['accessToken'] = serialize($accessToken);
    }

    public function getAccessToken()
    {
        if ($this->access_token == 'token_not_loaded') {
            $this->access_token = isset($_SESSION['accessToken']) ? unserialize($_SESSION['accessToken']) : null;
        }
        return $this->access_token;
    }

    /**
     * Должен удалить токен из сессионного хранилища
     * @return void
     */
    protected function unsetAccessToken()
    {
        if (isset($_SESSION['accessToken'])) {
            unset($_SESSION['accessToken']);
        }
        $this->access_token = null;
    }

    public function log($message, array $context = [])
    {
        if($this->is_debug) Logger::instance()->info($message, $context);
    }

    public function defaultScopes()
    {
        return 'phone';
    }
}
