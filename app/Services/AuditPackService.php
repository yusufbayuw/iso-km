<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Evidence;
use App\Models\ManagementReview;
use App\Models\ManagementSystem;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AuditPackService
{
    public function __construct(private TraceabilityMatrixService $traceabilityMatrix) {}

    public function generate(ManagementSystem $system): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP extension zip diperlukan untuk membuat Auditor Pack.');
        }

        $directory = storage_path('app/tmp/audit-packs');
        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            'auditor-pack-%s-%s-%s.zip',
            Str::slug($system->code ?: $system->name),
            now()->format('Ymd-His'),
            Str::lower(Str::random(6)),
        );
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Auditor Pack tidak dapat dibuat.');
        }

        foreach ($this->files($system) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    public function files(ManagementSystem $system): array
    {
        $system->loadMissing(['standard', 'scope', 'assuranceSnapshot']);
        $matrix = $this->traceabilityMatrix->build($system);

        return [
            'manifest.json' => json_encode($this->manifest($system, $matrix['summary']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'traceability-matrix.csv' => $this->traceabilityCsv($matrix['rows']->all()),
            'statement-of-applicability.csv' => $this->soaCsv($system),
            'risk-register.csv' => $this->riskCsv($system),
            'evidence-register.csv' => $this->evidenceCsv($system),
            'audit-register.csv' => $this->auditCsv($system),
            'findings-capa.csv' => $this->findingsCapaCsv($system),
            'management-review.csv' => $this->managementReviewCsv($system),
        ];
    }

    private function manifest(ManagementSystem $system, array $traceabilitySummary): array
    {
        $snapshot = $system->assuranceSnapshot;

        return [
            'generated_at' => now()->toIso8601String(),
            'management_system' => [
                'code' => $system->code,
                'name' => $system->name,
                'status' => $system->status,
                'standard' => trim(($system->standard?->code ?? '').' '.($system->standard?->edition ?? '')),
                'scope' => $system->scope?->name,
            ],
            'assurance_snapshot' => $snapshot ? [
                'requirement_readiness' => $snapshot->requirement_readiness,
                'control_implementation' => $snapshot->control_implementation,
                'evidence_coverage' => $snapshot->evidence_coverage,
                'evidence_freshness' => $snapshot->evidence_freshness,
                'risk_health' => $snapshot->risk_health,
                'findings_health' => $snapshot->findings_health,
                'action_health' => $snapshot->action_health,
                'confidence_level' => $snapshot->confidence_level,
                'calculated_at' => $snapshot->calculated_at?->toIso8601String(),
            ] : null,
            'traceability_summary' => $traceabilitySummary,
            'disclaimer' => 'Pack ini adalah representasi data internal dan bukan keputusan sertifikasi atau pernyataan otomatis bahwa organisasi compliant.',
        ];
    }

    private function traceabilityCsv(array $rows): string
    {
        return $this->csv(
            ['Requirement Code','Requirement','Assessment','Owner','Linked Controls','Documents','Effective Documents','Evidence','Verified Fresh Evidence','Open Risks','High Risks','Open Findings','Open CAPA','Next Review','Attention State','Notes'],
            array_map(fn (array $row) => [
                $row['requirement_code'], $row['requirement_title'], $row['assessment_status'], $row['owner'], $row['controls'],
                $row['documents'], $row['effective_documents'], $row['evidences'], $row['verified_fresh_evidence'],
                $row['open_risks'], $row['high_risks'], $row['open_findings'], $row['open_capa'], $row['next_review_at'],
                $row['attention_state'], $row['notes'],
            ], $rows),
        );
    }

    private function soaCsv(ManagementSystem $system): string
    {
        $rows = StatementOfApplicability::query()
            ->where('management_system_id', $system->id)
            ->with(['control', 'owner'])
            ->get()
            ->sortBy(fn (StatementOfApplicability $entry) => $entry->control?->code)
            ->map(fn (StatementOfApplicability $entry) => [
                $entry->control?->code,
                $entry->control?->title,
                $entry->control?->category,
                $entry->applicable ? 'Yes' : 'No',
                $entry->justification,
                $entry->implementation_status,
                $entry->owner?->name,
                $entry->last_reviewed_at?->toDateTimeString(),
                $entry->next_review_at?->toDateString(),
                $entry->implementation_notes,
            ])->all();

        return $this->csv(['Control','Title','Category','Applicable','Justification','Implementation Status','Owner','Last Reviewed','Next Review','Implementation Notes'], $rows);
    }

    private function riskCsv(ManagementSystem $system): string
    {
        $rows = Risk::query()
            ->where('management_system_id', $system->id)
            ->with('owner')
            ->orderBy('code')
            ->get()
            ->map(fn (Risk $risk) => [
                $risk->code, $risk->title, $risk->category, $risk->owner?->name, $risk->likelihood, $risk->impact,
                $risk->inherent_score, $risk->treatment_strategy, $risk->treatment_plan, $risk->residual_likelihood,
                $risk->residual_impact, $risk->residual_score, $risk->status, $risk->target_date?->toDateString(),
            ])->all();

        return $this->csv(['Code','Risk','Category','Owner','Likelihood','Impact','Inherent Score','Treatment Strategy','Treatment Plan','Residual Likelihood','Residual Impact','Residual Score','Status','Target Date'], $rows);
    }

    private function evidenceCsv(ManagementSystem $system): string
    {
        $rows = Evidence::query()
            ->where('management_system_id', $system->id)
            ->with(['owner', 'verifier', 'requirements', 'controls'])
            ->orderByDesc('captured_at')
            ->get()
            ->map(fn (Evidence $evidence) => [
                $evidence->title, $evidence->type, $evidence->source, $evidence->confidentiality, $evidence->owner?->name,
                $evidence->captured_at?->toDateString(), $evidence->valid_from?->toDateString(), $evidence->expires_at?->toDateString(),
                $evidence->status, $evidence->verification_status, $evidence->verifier?->name, $evidence->verified_at?->toDateTimeString(),
                $evidence->requirements->pluck('code')->implode(', '), $evidence->controls->pluck('code')->implode(', '), $evidence->description,
            ])->all();

        return $this->csv(['Evidence','Type','Source','Confidentiality','Owner','Captured','Valid From','Expires','Lifecycle Status','Verification','Verifier','Verified At','Requirements','Controls','Description'], $rows);
    }

    private function auditCsv(ManagementSystem $system): string
    {
        $rows = Audit::query()
            ->where('management_system_id', $system->id)
            ->with(['leadAuditor', 'requirements', 'controls'])
            ->orderByDesc('planned_start_at')
            ->get()
            ->map(fn (Audit $audit) => [
                $audit->code, $audit->title, $audit->type, $audit->status, $audit->leadAuditor?->name,
                $audit->planned_start_at?->toDateTimeString(), $audit->planned_end_at?->toDateTimeString(),
                $audit->actual_start_at?->toDateTimeString(), $audit->actual_end_at?->toDateTimeString(),
                $audit->requirements->pluck('code')->implode(', '), $audit->controls->pluck('code')->implode(', '),
                $audit->scope, $audit->objectives, $audit->criteria, $audit->summary,
            ])->all();

        return $this->csv(['Code','Audit','Type','Status','Lead Auditor','Planned Start','Planned End','Actual Start','Actual End','Requirements','Controls','Scope','Objectives','Criteria','Summary'], $rows);
    }

    private function findingsCapaCsv(ManagementSystem $system): string
    {
        $rows = [];
        $findings = AuditFinding::query()
            ->whereHas('audit', fn ($query) => $query->where('management_system_id', $system->id))
            ->with(['audit', 'requirement', 'control', 'owner', 'correctiveActions.owner', 'correctiveActions.verifier'])
            ->orderBy('code')
            ->get();

        foreach ($findings as $finding) {
            $actions = $finding->correctiveActions;
            if ($actions->isEmpty()) {
                $rows[] = [$finding->audit?->code,$finding->code,$finding->finding_type,$finding->severity,$finding->title,$finding->status,$finding->requirement?->code,$finding->control?->code,$finding->owner?->name,$finding->due_date?->toDateString(),$finding->root_cause,$finding->correction,null,null,null,null,null,null,null,null];
                continue;
            }

            foreach ($actions as $action) {
                $rows[] = [$finding->audit?->code,$finding->code,$finding->finding_type,$finding->severity,$finding->title,$finding->status,$finding->requirement?->code,$finding->control?->code,$finding->owner?->name,$finding->due_date?->toDateString(),$finding->root_cause,$finding->correction,$action->action,$action->owner?->name,$action->due_date?->toDateString(),$action->status,$action->completed_at?->toDateTimeString(),$action->verifier?->name,$action->effectiveness_status,$action->effectiveness_notes];
            }
        }

        return $this->csv(['Audit','Finding Code','Type','Severity','Finding','Finding Status','Requirement','Control','Finding Owner','Finding Due','Root Cause','Correction','Corrective Action','Action Owner','Action Due','Action Status','Completed At','Verifier','Effectiveness','Effectiveness Notes'], $rows);
    }

    private function managementReviewCsv(ManagementSystem $system): string
    {
        $rows = [];
        $reviews = ManagementReview::query()
            ->where('management_system_id', $system->id)
            ->with(['chairperson', 'actions.owner'])
            ->orderByDesc('scheduled_at')
            ->get();

        foreach ($reviews as $review) {
            if ($review->actions->isEmpty()) {
                $rows[] = [$review->code,$review->title,$review->status,$review->chairperson?->name,$review->scheduled_at?->toDateTimeString(),$review->held_at?->toDateTimeString(),$review->inputs_summary,$review->outputs_summary,$review->decisions,null,null,null,null,null];
                continue;
            }
            foreach ($review->actions as $action) {
                $rows[] = [$review->code,$review->title,$review->status,$review->chairperson?->name,$review->scheduled_at?->toDateTimeString(),$review->held_at?->toDateTimeString(),$review->inputs_summary,$review->outputs_summary,$review->decisions,$action->action,$action->owner?->name,$action->due_date?->toDateString(),$action->status,$action->notes];
            }
        }

        return $this->csv(['Review Code','Review','Status','Chairperson','Scheduled','Held','Inputs Summary','Outputs Summary','Decisions','Action','Action Owner','Action Due','Action Status','Action Notes'], $rows);
    }

    private function csv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $header);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($value) => is_bool($value) ? ($value ? 'Yes' : 'No') : $value, $row));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return $contents;
    }
}
