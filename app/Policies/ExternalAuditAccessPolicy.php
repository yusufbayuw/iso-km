<?php

namespace App\Policies;

class ExternalAuditAccessPolicy extends BaseIsoPolicy
{
    protected string $entity = 'external::audit::access';
}
