<?php

namespace App\Http\Controllers;

use App\Models\DocumentDistribution;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentDownloadController extends Controller
{
    public function __invoke(DocumentDistribution $distribution): StreamedResponse
    {
        $user = request()->user();

        if (! $user || ($distribution->user_id !== $user->id && ! $user->can('view_document'))) {
            throw new AuthorizationException('Anda tidak berhak mengakses controlled copy ini.');
        }

        $path = $distribution->revision?->file_path;
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($path && $disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = ($distribution->document?->code ?: 'document').'-rev-'.($distribution->revision?->revision ?: 'current').($extension ? '.'.$extension : '');

        return $disk->download($path, $filename);
    }
}
