<?php


namespace Codewiser\UAC;


/**
 * Class TokenIntrospection
 * @package Codewiser\UAC
 * @see https://pass.fc-zenit.ru/docs/oauth/token-introspection-endpoint.html
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
    protected array $data;

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
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Этот токен активен?
     * @deprecated
     */
    public function getActive(): bool
    {
        return $this->active;
    }

    /**
     * Открывает ли токен доступ к данному scope?
     */
    public function hasScope(string $scope): bool
    {
        $scopes = explode(' ', $this->getScope());
        return in_array($scope, $scopes);
    }

    /**
     * К каким scope открывает доступ этот токен.
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getExp(): ?int
    {
        return $this->exp;
    }

    public function getIat(): ?int
    {
        return $this->iat;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
