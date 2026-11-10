<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class GuidedFeatureGate
{
    public function enabled(): bool
    {
        return (bool) config('iso-km.guided_assessment_enabled', false);
    }

    public function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'guided_assessment' => 'Guided Assessment belum diaktifkan pada instalasi ini.',
            ]);
        }
    }
}
