<?php

namespace Codewiser\UAC\Grants;

use League\OAuth2\Client\Grant\AbstractGrant;

class UserOfficeGrant extends AbstractGrant
{

    protected function getName()
    {
        return 'user_office';
    }

    protected function getRequiredRequestParameters()
    {
        return [
            'token'
        ];
    }
}