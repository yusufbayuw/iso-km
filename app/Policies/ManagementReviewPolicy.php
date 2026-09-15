<?php

namespace App\Policies;

class ManagementReviewPolicy extends BaseIsoPolicy
{
    protected string $entity = 'management::review';
}
