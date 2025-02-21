<?php

namespace Codewiser\UAC\Grants;

/**
 * @deprecated
 */
class UserOfficeGrant extends TokenToOttGrant
{
    protected function getName()
    {
        return 'user_office';
    }
}