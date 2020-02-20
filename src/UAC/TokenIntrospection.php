<?php


namespace Codewiser\UAC;


/**
 * Class TokenIntrospection
 * @package Codewiser\UAC
 * @see http://oauth.fc-zenit.ru/doc/oauth/token-introspection-endpoint/
 */
class TokenIntrospection
{
    /** @var bool */
    protected $active;

    /** @var string|null */
    protected $scope;

    /** @var string|null */
    protected $clientId;

    /** @var string|null */
    protected $userName;

    /** @var int|null  */
    protected $exp;

    /** @var int|null  */
    protected $iat;

    /** @var array */
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
        $this->active = $data['active'];

        if ($this->active) {
            $this->scope = $data['scope'];
            $this->clientId = $data['client_id'];
            $this->userName = $data['username'];
            $this->exp = $data['exp'];
            $this->iat = $data['iat'];
        }
    }

    /**
     * Этот токен активен?
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Этот токен активен?
     * @deprecated
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Открывает ли токен доступ к данному scope?
     * @param $scope
     * @return bool
     */
    public function hasScope($scope)
    {
        $scopes = explode(' ', $this->getScope());
        return in_array($scope, $scopes);
    }

    /**
     * К каким scope открывает доступ этот токен
     * @return string|null
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return string|null
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @return string|null
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * @return int|null
     */
    public function getExp()
    {
        return $this->exp;
    }

    /**
     * @return int|null
     */
    public function getIat()
    {
        return $this->iat;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}