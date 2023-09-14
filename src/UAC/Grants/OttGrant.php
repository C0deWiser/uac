<?php

namespace Codewiser\UAC\Grants;

use League\OAuth2\Client\Grant\AbstractGrant;

class OttGrant extends AbstractGrant
{

    protected function getName()
    {
        return 'ott';
    }

    protected function getRequiredRequestParameters()
    {
        return [
            'token'
        ];
    }
}