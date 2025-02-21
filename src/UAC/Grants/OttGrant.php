<?php

namespace Codewiser\UAC\Grants;

/**
 * @deprecated
 */
class OttGrant extends OttToTokenGrant
{
    protected function getName()
    {
        return 'ott';
    }
}