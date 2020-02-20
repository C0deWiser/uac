<?php


namespace Codewiser\UAC\Exception\Api;


class InvalidRequestException extends RequestException
{
    public function __construct($error_description = null, $error_uri = null)
    {
        parent::__construct('401 Unauthorized', 'invalid_request', null, null, $error_description, $error_uri);
    }
}