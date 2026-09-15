<?php

namespace App\Policies;

class EvidenceRequestPolicy extends BaseIsoPolicy
{
    protected string $entity = 'evidence::request';
}
