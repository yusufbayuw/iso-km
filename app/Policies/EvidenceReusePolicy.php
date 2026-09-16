<?php

namespace App\Policies;

class EvidenceReusePolicy extends BaseIsoPolicy
{
    protected string $entity = 'evidence::reuse';
}
