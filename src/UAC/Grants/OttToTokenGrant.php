<?php

namespace Codewiser\UAC\Grants;

use League\OAuth2\Client\Grant\AbstractGrant;

class OttToTokenGrant extends AbstractGrant
{

    protected function getName()
    {
        return 'ott_to_token';
    }

    protected function getRequiredRequestParameters()
    {
        return [
            'token'
        ];
    }
}