<?php


namespace Codewiser\UAC\Exception\Api;


class InsufficientScopeException extends RequestException
{
    public function __construct($scope, $realm = null, $error_description = null, $error_uri = null)
    {
        parent::__construct('403 Forbidden', 'insufficient_scope', $scope, $realm, $error_description, $error_uri);
    }
}