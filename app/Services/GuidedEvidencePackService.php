<?php

namespace App\Services;

use App\Models\GuidedAssessment;
use App\Models\GuidedDecision;
use App\Models\GuidedEvidencePackRecord;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidedEvidencePackService
{
    /**
     * Export only currently accepted and verified evidence. Sensitive files are
     * omitted by default and require a separate, explicit permission to include.
     */
    public function export(GuidedAssessment $run, User $actor, bool $includeSensitive = false): string
    {
        if (! $actor->can('guided.report.export') || ! $run->isMember($actor, ['coordinator'])) {
            throw ValidationException::withMessages(['authorization' => 'Tidak berwenang mengekspor evidence asesmen ini.']);
        }
        if ($run->status !== 'locked' || ! $run->locked_at) {
            throw ValidationException::withMessages(['assessment' => 'Evidence Pack final memerlukan asesmen terkunci.']);
        }
        if ($includeSensitive && ! $actor->can('guided.evidence_pack.export_sensitive')) {
            throw ValidationException::withMessages(['authorization' => 'Ekspor evidence rahasia memerlukan permission terpisah.']);
        }

        $decisions = GuidedDecision::query()->whereHas('consolidation', fn ($q) =>
            $q->where('guided_assessment_id', $run->id)->where('is_current', true))
            ->where('status', 'approved')->whereNotNull('synced_at')
            ->with(['acceptances.evidence'])->get();

        $accepted = $decisions->flatMap(fn ($decision) => $decision->acceptances)
            ->where('status', 'accepted')->unique('evidence_id');
        $relative = 'iso/exports/evidence-pack-'.$run->id.'-'.Str::random(12).'.zip';
        $file = Storage::disk('local')->path($relative);
        File::ensureDirectoryExists(dirname($file));
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['export' => 'Gagal membuat arsip evidence.']);
        }

        $manifest = [
            'format' => 'ISO-KM Guided Assessment Evidence Pack v1',
            'assessment_id' => $run->id,
            'management_system_id' => $run->management_system_id,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $actor->id,
            'includes_sensitive' => $includeSensitive,
            'included' => [],
            'omitted' => [],
        ];
        $totalBytes = 0;
        try {
            foreach ($accepted as $item) {
                $evidence = $item->evidence;
                if (! $evidence || (int) $evidence->management_system_id !== (int) $run->management_system_id) {
                    $manifest['omitted'][] = ['evidence_id' => $item->evidence_id, 'reason' => 'not_in_scope'];
                    continue;
                }
                if ($evidence->status !== 'active' || $evidence->verification_status !== 'verified'
                    || ($evidence->expires_at && $evidence->expires_at->isPast())) {
                    $manifest['omitted'][] = ['evidence_id' => $evidence->id, 'reason' => 'not_currently_verified'];
                    continue;
                }
                $isSensitive = ! in_array($evidence->confidentiality, ['public', 'internal'], true);
                if ($isSensitive && ! $includeSensitive) {
                    $manifest['omitted'][] = ['evidence_id' => $evidence->id, 'reason' => 'sensitive_permission_required'];
                    continue;
                }
                $relativePath = (string) $evidence->file_path;
                if (! str_starts_with($relativePath, 'iso/evidence/')
                    || str_contains($relativePath, '..') || str_contains($relativePath, chr(92))) {
                    $manifest['omitted'][] = ['evidence_id' => $evidence->id, 'reason' => 'no_safe_local_file'];
                    continue;
                }
                $disk = Storage::disk(config('filesystems.default', 'local'));
                try {
                    if (! $disk->exists($relativePath)) { throw new \RuntimeException('Missing file'); }
                    $size = $disk->size($relativePath);
                    if ($size > 20_000_000 || $totalBytes + $size > 80_000_000) {
                        $manifest['omitted'][] = ['evidence_id' => $evidence->id, 'reason' => 'package_size_limit'];
                        continue;
                    }
                    $stream = $disk->readStream($relativePath);
                    if (! is_resource($stream)) { throw new \RuntimeException('Unreadable file'); }
                    try { $bytes = stream_get_contents($stream, $size + 1); }
                    finally { fclose($stream); }
                    if (! is_string($bytes) || strlen($bytes) !== $size) {
                        throw new \RuntimeException('Size mismatch');
                    }
                } catch (\Throwable $exception) {
                    $manifest['omitted'][] = ['evidence_id' => $evidence->id, 'reason' => 'file_unavailable'];
                    continue;
                }
                $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) { $extension = 'bin'; }
                $entry = 'evidence/EVD-'.$evidence->id.'.'.$extension;
                $zip->addFromString($entry, $bytes);
                $totalBytes += strlen($bytes);
                $manifest['included'][] = [
                    'evidence_id' => $evidence->id, 'reference' => 'EVD-'.$evidence->id,
                    'title' => $evidence->title, 'classification' => $evidence->confidentiality,
                    'captured_at' => $evidence->captured_at?->toDateString(),
                    'verified_at' => $evidence->verified_at?->toIso8601String(),
                    'entry' => $entry, 'sha256' => hash('sha256', $bytes),
                ];
            }
            $zip->addFromString('manifest.json', json_encode($manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } finally {
            $zip->close();
        }
        GuidedEvidencePackRecord::create([
            'guided_assessment_id' => $run->id,
            'generated_by' => $actor->id,
            'includes_sensitive' => $includeSensitive,
            'included_count' => count($manifest['included']),
            'omitted_count' => count($manifest['omitted']),
            'file_path' => $relative,
            'sha256' => hash_file('sha256', $file),
        ]);
        return $relative;
    }
}
