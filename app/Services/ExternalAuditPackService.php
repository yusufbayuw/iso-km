<?php

namespace App\Services;

use App\Models\AuditFinding;
use App\Models\Evidence;
use App\Models\EvidenceReuse;
use App\Models\ManagementSystem;
use App\Models\StatementOfApplicability;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExternalAuditPackService
{
    public function __construct(private TraceabilityMatrixService $traceabilityMatrix) {}

    public function generate(ManagementSystem $system): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP extension zip diperlukan untuk membuat External Auditor Pack.');
        }

        $directory = storage_path('app/tmp/external-audit-packs');
        File::ensureDirectoryExists($directory);
        $filename = sprintf('external-auditor-pack-%s-%s-%s.zip', Str::slug($system->code ?: $system->name), now()->format('Ymd-His'), Str::lower(Str::random(6)));
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('External Auditor Pack tidak dapat dibuat.');
        }
        foreach ($this->files($system) as $name => $contents) { $zip->addFromString($name, $contents); }
        $zip->close();
        return $path;
    }

    public function files(ManagementSystem $system): array
    {
        $system->loadMissing(['standard','scope','assuranceSnapshot']);
        $matrix = $this->traceabilityMatrix->build($system);
        return [
            'manifest.json' => json_encode($this->manifest($system, $matrix['summary']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'traceability-matrix.csv' => $this->traceabilityCsv($matrix['rows']->all()),
            'statement-of-applicability.csv' => $this->soaCsv($system),
            'evidence-index.csv' => $this->evidenceCsv($system),
            'cross-standard-evidence-reuse.csv' => $this->reuseCsv($system),
            'findings-summary.csv' => $this->findingsCsv($system),
        ];
    }

    private function manifest(ManagementSystem $system, array $summary): array
    {
        $snapshot = $system->assuranceSnapshot;
        return [
            'generated_at' => now()->toIso8601String(),
            'management_system' => [
                'code' => $system->code,
                'name' => $system->name,
                'standard' => trim(($system->standard?->code ?? '').' '.($system->standard?->edition ?? '')),
                'scope' => $system->scope?->name,
            ],
            'traceability_summary' => $summary,
            'assurance_snapshot' => $snapshot ? [
                'requirement_readiness' => $snapshot->requirement_readiness,
                'control_implementation' => $snapshot->control_implementation,
                'evidence_coverage' => $snapshot->evidence_coverage,
                'evidence_freshness' => $snapshot->evidence_freshness,
                'confidence_level' => $snapshot->confidence_level,
                'calculated_at' => $snapshot->calculated_at?->toIso8601String(),
            ] : null,
            'raw_files_included' => false,
            'disclaimer' => 'Pack read-only ini berisi metadata dan traceability audit. Tidak memuat file evidence mentah dan bukan keputusan sertifikasi.',
        ];
    }

    private function traceabilityCsv(array $rows): string
    {
        return $this->csv(
            ['Requirement','Title','Assessment','Direct Verified Evidence','Accepted Reused Evidence','Total Verified Support','High Risks','High Risk Suppliers','Open Findings','Open CAPA','Attention'],
            array_map(fn (array $row) => [$row['requirement_code'],$row['requirement_title'],$row['assessment_status'],$row['verified_fresh_evidence'],$row['verified_fresh_reused_evidence'],$row['verified_fresh_evidence_total'],$row['high_risks'],$row['high_risk_suppliers'],$row['open_findings'],$row['open_capa'],$row['attention_state']], $rows),
        );
    }

    private function soaCsv(ManagementSystem $system): string
    {
        $rows = StatementOfApplicability::query()->where('management_system_id', $system->id)->with('control')->get()->map(fn ($entry) => [
            $entry->control?->code,$entry->control?->title,$entry->applicable ? 'Yes' : 'No',$entry->implementation_status,$entry->last_reviewed_at?->toDateTimeString(),$entry->next_review_at?->toDateString(),
        ])->all();
        return $this->csv(['Control','Title','Applicable','Implementation Status','Last Reviewed','Next Review'], $rows);
    }

    private function evidenceCsv(ManagementSystem $system): string
    {
        $rows = Evidence::query()->where('management_system_id', $system->id)->with(['requirements','controls'])->orderByDesc('captured_at')->get()->map(fn (Evidence $evidence) => [
            $evidence->title,$evidence->type,$evidence->source,$evidence->captured_at?->toDateString(),$evidence->expires_at?->toDateString(),$evidence->status,$evidence->verification_status,$evidence->requirements->pluck('code')->implode(', '),$evidence->controls->pluck('code')->implode(', '),
        ])->all();
        return $this->csv(['Evidence','Type','Source','Captured','Expires','Status','Verification','Requirements','Controls'], $rows);
    }

    private function reuseCsv(ManagementSystem $system): string
    {
        $rows = EvidenceReuse::query()->accepted()->where('target_management_system_id', $system->id)
            ->with(['evidence.managementSystem.standard','targetRequirement','targetControl','reviewer'])
            ->get()->map(fn (EvidenceReuse $reuse) => [
                $reuse->evidence?->title,
                $reuse->evidence?->managementSystem?->code,
                $reuse->evidence?->managementSystem?->standard?->code,
                $reuse->targetRequirement?->code,
                $reuse->targetControl?->code,
                $reuse->reviewer?->name,
                $reuse->reviewed_at?->toDateTimeString(),
            ])->all();
        return $this->csv(['Evidence','Source System','Source Standard','Target Requirement','Target Control','Reuse Reviewer','Accepted At'], $rows);
    }

    private function findingsCsv(ManagementSystem $system): string
    {
        $rows = AuditFinding::query()->whereHas('audit', fn ($query) => $query->where('management_system_id', $system->id))
            ->with(['audit','requirement','control','correctiveActions'])->get()->map(fn (AuditFinding $finding) => [
                $finding->audit?->code,$finding->code,$finding->finding_type,$finding->severity,$finding->status,$finding->requirement?->code,$finding->control?->code,$finding->due_date?->toDateString(),$finding->correctiveActions->whereNotIn('status', ['closed','verified','completed'])->count(),
            ])->all();
        return $this->csv(['Audit','Finding','Type','Severity','Status','Requirement','Control','Due','Open CAPA'], $rows);
    }

    private function csv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $header);
        foreach ($rows as $row) { fputcsv($stream, $row); }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return $contents;
    }
}
