<?php

namespace Codewiser\UAC\Grants;

use League\OAuth2\Client\Grant\AbstractGrant;

class TokenToOttGrant extends AbstractGrant
{

    protected function getName()
    {
        return 'token_to_ott';
    }

    protected function getRequiredRequestParameters()
    {
        return [
            'token'
        ];
    }
}