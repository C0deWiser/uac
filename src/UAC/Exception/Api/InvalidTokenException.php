<?php


namespace Codewiser\UAC\Exception\Api;

class InvalidTokenException extends RequestException
{
    public function __construct($error_description = null, $error_uri = null)
    {
        parent::__construct('400 Bad Request', 'invalid_token', null, null, $error_description, $error_uri);
    }
}