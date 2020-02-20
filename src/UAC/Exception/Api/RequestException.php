<?php


namespace Codewiser\UAC\Exception\Api;
use Codewiser\UAC\Exception\OauthResponseException;

/**
 * Class ApiRequestException
 * @package Codewiser\UAC\Exception
 *
 * @see https://tools.ietf.org/html/rfc6750
 */
class RequestException extends OauthResponseException
{
    protected $httpErrorCode;
    protected $scope;
    protected $realm;

    /**
     * ApiRequestException constructor.
     * @param string $httpErrorCode e.g. 401 Unauthorized
     * @param null|string $error e.g. insufficient_scope
     * @param null|string $scope
     * @param null|string $realm
     * @param null|string $error_description
     * @param null|string $error_uri
     */
    public function __construct($httpErrorCode = '401 Unauthorized', $error = null, $scope = null, $realm = null, $error_description = null, $error_uri = null)
    {
        parent::__construct($error, $error_description, $error_uri);
        $this->httpErrorCode = $httpErrorCode;
        $this->scope = $scope;
        $this->realm = $realm;
    }

    /**
     * Код ошибки HTTP
     * @return string
     */
    public function getHttpCode()
    {
        return $this->httpErrorCode;
    }

    /**
     * @return null
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return null
     */
    public function getRealm()
    {
        return $this->realm;
    }
}