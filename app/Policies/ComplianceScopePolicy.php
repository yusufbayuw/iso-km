<?php

namespace App\Policies;

class ComplianceScopePolicy extends BaseIsoPolicy
{
    protected string $entity = 'compliance::scope';
}
