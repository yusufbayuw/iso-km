<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\CompetencyRecord;
use App\Models\Evidence;
use App\Models\ManagementReview;
use App\Models\ManagementSystem;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use App\Models\Supplier;
use App\Models\SupplierAssessment;
use App\Models\TrainingProgram;
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

        $filename = sprintf('auditor-pack-%s-%s-%s.zip', Str::slug($system->code ?: $system->name), now()->format('Ymd-His'), Str::lower(Str::random(6)));
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
            'training-register.csv' => $this->trainingCsv($system),
            'competence-register.csv' => $this->competenceCsv($system),
            'supplier-register.csv' => $this->supplierCsv($system),
            'supplier-assessments.csv' => $this->supplierAssessmentCsv($system),
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
                'attention_counts' => $snapshot->attention_counts,
                'calculated_at' => $snapshot->calculated_at?->toIso8601String(),
            ] : null,
            'traceability_summary' => $traceabilitySummary,
            'included_registers' => ['SoA','Risk','Evidence','Training','Competence','Supplier','Supplier Assessment','Audit','Finding/CAPA','Management Review'],
            'disclaimer' => 'Pack ini adalah representasi data internal dan bukan keputusan sertifikasi atau pernyataan otomatis bahwa organisasi compliant.',
        ];
    }

    private function traceabilityCsv(array $rows): string
    {
        return $this->csv(
            ['Requirement Code','Requirement','Assessment','Owner','Linked Controls','Documents','Effective Documents','Evidence','Verified Fresh Evidence','Open Risks','High Risks','Training Programs','Competence Records','Suppliers','High Risk Suppliers','Open Findings','Open CAPA','Next Review','Attention State','Notes'],
            array_map(fn (array $row) => [
                $row['requirement_code'], $row['requirement_title'], $row['assessment_status'], $row['owner'], $row['controls'],
                $row['documents'], $row['effective_documents'], $row['evidences'], $row['verified_fresh_evidence'], $row['open_risks'], $row['high_risks'],
                $row['training_programs'], $row['competence_records'], $row['suppliers'], $row['high_risk_suppliers'],
                $row['open_findings'], $row['open_capa'], $row['next_review_at'], $row['attention_state'], $row['notes'],
            ], $rows),
        );
    }

    private function soaCsv(ManagementSystem $system): string
    {
        $rows = StatementOfApplicability::query()->where('management_system_id', $system->id)->with(['control', 'owner'])->get()
            ->sortBy(fn (StatementOfApplicability $entry) => $entry->control?->code)
            ->map(fn (StatementOfApplicability $entry) => [$entry->control?->code,$entry->control?->title,$entry->control?->category,$entry->applicable ? 'Yes' : 'No',$entry->justification,$entry->implementation_status,$entry->owner?->name,$entry->last_reviewed_at?->toDateTimeString(),$entry->next_review_at?->toDateString(),$entry->implementation_notes])->all();
        return $this->csv(['Control','Title','Category','Applicable','Justification','Implementation Status','Owner','Last Reviewed','Next Review','Implementation Notes'], $rows);
    }

    private function riskCsv(ManagementSystem $system): string
    {
        $rows = Risk::query()->where('management_system_id', $system->id)->with('owner')->orderBy('code')->get()
            ->map(fn (Risk $risk) => [$risk->code,$risk->title,$risk->category,$risk->owner?->name,$risk->likelihood,$risk->impact,$risk->inherent_score,$risk->treatment_strategy,$risk->treatment_plan,$risk->residual_likelihood,$risk->residual_impact,$risk->residual_score,$risk->status,$risk->target_date?->toDateString()])->all();
        return $this->csv(['Code','Risk','Category','Owner','Likelihood','Impact','Inherent Score','Treatment Strategy','Treatment Plan','Residual Likelihood','Residual Impact','Residual Score','Status','Target Date'], $rows);
    }

    private function evidenceCsv(ManagementSystem $system): string
    {
        $rows = Evidence::query()->where('management_system_id', $system->id)->with(['owner','verifier','requirements','controls','suppliers'])->orderByDesc('captured_at')->get()
            ->map(fn (Evidence $evidence) => [$evidence->title,$evidence->type,$evidence->source,$evidence->confidentiality,$evidence->owner?->name,$evidence->captured_at?->toDateString(),$evidence->valid_from?->toDateString(),$evidence->expires_at?->toDateString(),$evidence->status,$evidence->verification_status,$evidence->verifier?->name,$evidence->verified_at?->toDateTimeString(),$evidence->requirements->pluck('code')->implode(', '),$evidence->controls->pluck('code')->implode(', '),$evidence->suppliers->pluck('code')->implode(', '),$evidence->description])->all();
        return $this->csv(['Evidence','Type','Source','Confidentiality','Owner','Captured','Valid From','Expires','Lifecycle Status','Verification','Verifier','Verified At','Requirements','Controls','Suppliers','Description'], $rows);
    }

    private function trainingCsv(ManagementSystem $system): string
    {
        $rows = [];
        $programs = TrainingProgram::query()->where('management_system_id', $system->id)->with(['owner','requirements','controls','assignments.user','assignments.evidence'])->orderBy('code')->get();
        foreach ($programs as $program) {
            if ($program->assignments->isEmpty()) {
                $rows[] = [$program->code,$program->title,$program->category,$program->mandatory,$program->target_audience,$program->owner?->name,$program->status,$program->next_review_at?->toDateString(),$program->requirements->pluck('code')->implode(', '),$program->controls->pluck('code')->implode(', '),null,null,null,null,null,null,null,null,null,null];
                continue;
            }
            foreach ($program->assignments as $assignment) {
                $rows[] = [$program->code,$program->title,$program->category,$program->mandatory,$program->target_audience,$program->owner?->name,$program->status,$program->next_review_at?->toDateString(),$program->requirements->pluck('code')->implode(', '),$program->controls->pluck('code')->implode(', '),$assignment->user?->name,$assignment->cycle_key,$assignment->assigned_at?->toDateString(),$assignment->due_at?->toDateString(),$assignment->completed_at?->toDateTimeString(),$assignment->valid_until?->toDateString(),$assignment->status,$assignment->result,$assignment->score,$assignment->evidence?->title];
            }
        }
        return $this->csv(['Program Code','Program','Category','Mandatory','Target Audience','Owner','Program Status','Program Review','Requirements','Controls','Participant','Cycle','Assigned','Due','Completed','Valid Until','Assignment Status','Result','Score','Evidence'], $rows);
    }

    private function competenceCsv(ManagementSystem $system): string
    {
        $rows = CompetencyRecord::query()->where('management_system_id', $system->id)->with(['user','assessor','evidence','requirements','controls'])->orderBy('user_id')->get()
            ->map(fn (CompetencyRecord $record) => [$record->user?->name,$record->competency,$record->source,$record->proficiency_level,$record->assessor?->name,$record->assessed_at?->toDateString(),$record->valid_until?->toDateString(),$record->status,$record->evidence?->title,$record->requirements->pluck('code')->implode(', '),$record->controls->pluck('code')->implode(', '),$record->notes])->all();
        return $this->csv(['Person','Competency','Source','Level','Assessor','Assessed At','Valid Until','Status','Evidence','Requirements','Controls','Notes'], $rows);
    }

    private function supplierCsv(ManagementSystem $system): string
    {
        $rows = Supplier::query()->whereHas('managementSystems', fn ($query) => $query->where('management_systems.id', $system->id))->with(['owner','requirements','controls','evidences'])->orderBy('code')->get()
            ->map(fn (Supplier $supplier) => [$supplier->code,$supplier->name,$supplier->category,$supplier->service_description,$supplier->owner?->name,$supplier->criticality,$supplier->status,$supplier->contact_name,$supplier->contact_email,$supplier->next_review_at?->toDateString(),$supplier->requirements->pluck('code')->implode(', '),$supplier->controls->pluck('code')->implode(', '),$supplier->evidences->pluck('title')->implode(' | '),$supplier->notes])->all();
        return $this->csv(['Code','Supplier','Category','Service','Owner','Criticality','Status','Contact','Email','Next Review','Requirements','Controls','Evidence','Notes'], $rows);
    }

    private function supplierAssessmentCsv(ManagementSystem $system): string
    {
        $rows = SupplierAssessment::query()->where('management_system_id', $system->id)->with(['supplier','assessor','evidence'])->orderByDesc('assessed_at')->get()
            ->map(fn (SupplierAssessment $assessment) => [$assessment->supplier?->code,$assessment->supplier?->name,$assessment->assessor?->name,$assessment->assessed_at?->toDateString(),$assessment->next_review_at?->toDateString(),$assessment->score,$assessment->risk_rating,$assessment->status,$assessment->evidence?->title,$assessment->summary,$assessment->required_actions])->all();
        return $this->csv(['Supplier Code','Supplier','Assessor','Assessed At','Next Review','Score','Risk Rating','Status','Evidence','Summary','Required Actions'], $rows);
    }

    private function auditCsv(ManagementSystem $system): string
    {
        $rows = Audit::query()->where('management_system_id', $system->id)->with(['leadAuditor','requirements','controls'])->orderByDesc('planned_start_at')->get()
            ->map(fn (Audit $audit) => [$audit->code,$audit->title,$audit->type,$audit->status,$audit->leadAuditor?->name,$audit->planned_start_at?->toDateTimeString(),$audit->planned_end_at?->toDateTimeString(),$audit->actual_start_at?->toDateTimeString(),$audit->actual_end_at?->toDateTimeString(),$audit->requirements->pluck('code')->implode(', '),$audit->controls->pluck('code')->implode(', '),$audit->scope,$audit->objectives,$audit->criteria,$audit->summary])->all();
        return $this->csv(['Code','Audit','Type','Status','Lead Auditor','Planned Start','Planned End','Actual Start','Actual End','Requirements','Controls','Scope','Objectives','Criteria','Summary'], $rows);
    }

    private function findingsCapaCsv(ManagementSystem $system): string
    {
        $rows = [];
        $findings = AuditFinding::query()->whereHas('audit', fn ($query) => $query->where('management_system_id', $system->id))->with(['audit','requirement','control','owner','correctiveActions.owner','correctiveActions.verifier'])->orderBy('code')->get();
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
        $reviews = ManagementReview::query()->where('management_system_id', $system->id)->with(['chairperson','actions.owner'])->orderByDesc('scheduled_at')->get();
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
        foreach ($rows as $row) { fputcsv($stream, array_map(fn ($value) => is_bool($value) ? ($value ? 'Yes' : 'No') : $value, $row)); }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return $contents;
    }
}
