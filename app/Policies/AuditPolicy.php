<?php

namespace App\Policies;

class AuditPolicy extends BaseIsoPolicy
{
    protected string $entity = 'audit';
}
