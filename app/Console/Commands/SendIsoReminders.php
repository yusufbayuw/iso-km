<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\ComplianceObligation;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\DocumentDistribution;
use App\Models\EvidenceRequest;
use App\Models\ManagementReview;
use App\Models\ManagementReviewAction;
use App\Models\Objective;
use App\Models\User;
use App\Notifications\IsoDueReminder;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SendIsoReminders extends Command
{
    protected $signature = 'iso:send-reminders';
    protected $description = 'Kirim reminder ISO untuk review, evidence, acknowledgement, audit, CAPA, objective, compliance, dan management review';

    public function handle(): int
    {
        $sent = 0;
        Document::query()->with('owner')->whereNotNull('next_review_at')->whereNotIn('status', ['obsolete'])->each(fn (Document $item) => $sent += $this->notifyDue($item, $item->owner, $item->next_review_at, 'document_review', 'Review dokumen', "Dokumen {$item->code} - {$item->title} perlu direview."));
        DocumentDistribution::query()->with(['user','document','revision'])->whereNotNull('acknowledgement_due_at')->where('status','pending')->each(fn (DocumentDistribution $item) => $sent += $this->notifyDue($item, $item->user, $item->acknowledgement_due_at, 'document_acknowledgement', 'Acknowledgement dokumen', "Mohon baca dan acknowledge {$item->document?->code} revisi {$item->revision?->revision}."));
        EvidenceRequest::query()->with(['owner','schedule'])->whereNotIn('status', ['verified','waived'])->each(fn (EvidenceRequest $item) => $sent += $this->notifyDue($item, $item->owner, $item->due_at, 'evidence_collection', 'Pengumpulan evidence', 'Evidence '.($item->schedule?->title ?? 'yang diminta').' perlu dikumpulkan'.($item->period_label ? ' untuk periode '.$item->period_label : '').'.'));
        CorrectiveAction::query()->with('owner')->whereNotNull('due_date')->whereNotIn('status', ['closed','verified','completed'])->each(fn (CorrectiveAction $item) => $sent += $this->notifyDue($item, $item->owner, $item->due_date, 'capa_due', 'Tindak lanjut CAPA', 'Corrective action mendekati atau melewati due date.'));
        Audit::query()->with('leadAuditor')->whereNotNull('planned_start_at')->whereIn('status', ['planning','scheduled'])->each(fn (Audit $item) => $sent += $this->notifyDue($item, $item->leadAuditor, $item->planned_start_at, 'audit_schedule', 'Jadwal audit', "Audit {$item->code} - {$item->title} akan dimulai."));
        Objective::query()->with('owner')->whereNotNull('end_date')->whereIn('status', ['active','at_risk'])->each(fn (Objective $item) => $sent += $this->notifyDue($item, $item->owner, $item->end_date, 'objective_due', 'Target objective', "Objective {$item->code} - {$item->title} mendekati akhir periode."));
        ComplianceObligation::query()->with('owner')->whereNotNull('next_review_at')->where('status','active')->each(fn (ComplianceObligation $item) => $sent += $this->notifyDue($item, $item->owner, $item->next_review_at, 'compliance_review', 'Review kewajiban kepatuhan', "Kewajiban {$item->code} - {$item->title} perlu direview."));
        ManagementReview::query()->with('chairperson')->whereNotNull('scheduled_at')->whereIn('status', ['planned','scheduled'])->each(fn (ManagementReview $item) => $sent += $this->notifyDue($item, $item->chairperson, $item->scheduled_at, 'management_review', 'Management review', "Management review {$item->code} - {$item->title} akan dilaksanakan."));
        ManagementReviewAction::query()->with('owner')->whereNotNull('due_date')->whereNotIn('status', ['completed','closed'])->each(fn (ManagementReviewAction $item) => $sent += $this->notifyDue($item, $item->owner, $item->due_date, 'management_review_action', 'Tindak lanjut management review', 'Action item management review mendekati atau melewati due date.'));
        $this->info("{$sent} reminder dikirim.");
        return self::SUCCESS;
    }

    private function notifyDue(Model $subject, ?User $recipient, CarbonInterface|string|null $dueDate, string $category, string $title, string $message): int
    {
        if (! $recipient || ! $dueDate) { return 0; }
        $due = $dueDate instanceof CarbonInterface ? $dueDate->copy()->startOfDay() : Carbon::parse($dueDate)->startOfDay();
        $days = (int) now()->startOfDay()->diffInDays($due, false);
        $shouldSend = in_array($days, [30,14,7,3,1,0], true) || ($days < 0 && abs($days) % 7 === 0);
        if (! $shouldSend) { return 0; }
        $key = $due->toDateString().':'.$days;
        $inserted = DB::table('reminder_dispatches')->insertOrIgnore(['remindable_type' => $subject->getMorphClass(),'remindable_id' => $subject->getKey(),'recipient_id' => $recipient->id,'category' => $category,'reminder_key' => $key,'sent_at' => now(),'created_at' => now(),'updated_at' => now()]);
        if ($inserted === 0) { return 0; }
        $prefix = $days < 0 ? 'TERLAMBAT '.abs($days).' hari. ' : ($days === 0 ? 'Jatuh tempo hari ini. ' : "{$days} hari lagi. ");
        $recipient->notify(new IsoDueReminder(category: $category, title: $title, message: $prefix.$message, dueDate: $due->toDateString()));
        return 1;
    }
}
