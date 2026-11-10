<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuidedWorkbookTemplateFileService
{
    public function path(string $relative): string
    {
        app(GuidedFeatureGate::class)->assertEnabled();
        $segments = explode('/', $relative);
        if (! str_starts_with($relative, 'iso/guided/templates/')
            || str_contains($relative, chr(92))
            || in_array('..', $segments, true)
            || in_array('.', $segments, true)
            || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ValidationException::withMessages(['template' => 'Path template harus berada dalam penyimpanan privat XLSX.']);
        }
        $root = Storage::disk('local')->path('iso/guided/templates');
        $path = Storage::disk('local')->path($relative);
        $resolved = realpath($path);
        $base = realpath($root);
        if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR) || ! is_file($resolved)) {
            throw ValidationException::withMessages(['template' => 'File template tidak ditemukan di direktori yang diizinkan.']);
        }
        if (filesize($resolved) > 20_000_000) {
            throw ValidationException::withMessages(['template' => 'Ukuran template melebihi batas 20 MB.']);
        }
        return $resolved;
    }
}
