<?php

namespace App\Policies;

class AuditFindingPolicy extends BaseIsoPolicy
{
    protected string $entity = 'audit::finding';
}
