<?php


namespace Codewiser\UAC;


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
     * @return mixed
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
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