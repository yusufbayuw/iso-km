<?php

namespace App\Policies;

class CorrectiveActionPolicy extends BaseIsoPolicy
{
    protected string $entity = 'corrective::action';
}
