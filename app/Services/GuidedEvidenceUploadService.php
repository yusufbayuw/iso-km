<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\GuidedAnswerDraft;
use App\Models\GuidedAssignment;
use App\Models\GuidedQuestionTarget;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class GuidedEvidenceUploadService
{
    /**
     * Register private uploads once, link them to the question's mapped
     * requirements/controls, and add them to the existing wizard draft.
     * No additional permission or duplicate evidence entry is required.
     *
     * @param array<UploadedFile> $files
     * @param array<int|string> $existingEvidenceIds
     */
    public function uploadAndAttach(
        GuidedAssignment $assignment,
        User $actor,
        string $contextKey,
        ?string $value,
        ?string $notes,
        array $existingEvidenceIds,
        int $expectedVersion,
        array $files
    ): GuidedAnswerDraft {
        if (count($files) < 1 || count($files) > 5) {
            throw ValidationException::withMessages([
                'files' => 'Pilih 1 sampai 5 file bukti untuk diunggah.',
            ]);
        }

        foreach ($files as $file) {
            Validator::make(['file' => $file], [
                'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx,xlsx', 'max:10240'],
            ])->validate();
        }

        // Re-read the assignment. Never trust the Livewire model or form payload
        // for ownership, scope, question target, or the locked state.
        $assignment = GuidedAssignment::query()
            ->with(['assessment', 'question'])
            ->findOrFail($assignment->id);
        $run = $assignment->assessment;
        if ($assignment->user_id !== $actor->id
            || ! $actor->can('guided.answer.submit')
            || ! $run->isMember($actor, ['respondent'])) {
            throw ValidationException::withMessages([
                'authorization' => 'Hanya responden yang ditugaskan yang dapat mengunggah bukti.',
            ]);
        }

        if ($run->locked_at
            || in_array($run->status, ['locked', 'closed'], true)
            || ! in_array($assignment->status, ['assigned', 'in_progress', 'submitted'], true)
            || $assignment->question->guided_question_bank_id !== $run->guided_question_bank_id) {
            throw ValidationException::withMessages([
                'assignment' => 'Asesmen atau penugasan tidak lagi dapat menerima bukti.',
            ]);
        }

        app(GuidedEvidenceAccessService::class)->assertSelectable(
            $assignment, $actor, $existingEvidenceIds
        );

        // An optimistic-lock conflict on the draft must not leave partial
        // database records or files behind.
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $assignment, $actor, $contextKey, $value, $notes,
                $existingEvidenceIds, $expectedVersion, $files, &$storedPaths
            ): GuidedAnswerDraft {
                $run = $assignment->assessment;
                $question = $assignment->question;

                $requirementIds = GuidedQuestionTarget::query()
                    ->where('guided_question_id', $question->id)
                    ->whereNotNull('requirement_id')
                    ->pluck('requirement_id')->unique()->all();
                $controlIds = GuidedQuestionTarget::query()
                    ->where('guided_question_id', $question->id)
                    ->whereNotNull('control_id')
                    ->pluck('control_id')->unique()->all();

                $ids = $existingEvidenceIds;

                foreach ($files as $file) {
                    /** @var UploadedFile $file */
                    $path = Storage::disk('local')->putFile('iso/evidence', $file);
                    if (! is_string($path) || $path === '') {
                        throw ValidationException::withMessages([
                            'files' => 'Penyimpanan file bukti gagal. Silakan ulangi.',
                        ]);
                    }
                    $storedPaths[] = $path;

                    $originalName = trim((string) preg_replace(
                        '/[\x00-\x1f\x7f<>]+/u', ' ',
                        basename($file->getClientOriginalName())
                    ));
                    $title = mb_substr($originalName ?: 'Bukti asesmen', 0, 255);
                    $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

                    $evidence = Evidence::query()->create([
                        'management_system_id' => $run->management_system_id,
                        'title' => $title,
                        'type' => $isImage ? 'screenshot' : 'report',
                        'source' => 'Guided Assessment #'.$run->id.' / '.$question->code,
                        'description' => 'Diunggah dari asesmen '.$run->name
                            .' untuk pertanyaan '.$question->code.' (konteks: '.$contextKey.').',
                        'file_path' => $path,
                        'owner_id' => $actor->id,
                        'captured_at' => today(),
                        'confidentiality' => 'confidential',
                        'status' => 'active',
                        'verification_status' => 'pending',
                    ]);

                    if ($requirementIds) {
                        $evidence->requirements()->syncWithoutDetaching($requirementIds);
                    }
                    if ($controlIds) {
                        $evidence->controls()->syncWithoutDetaching($controlIds);
                    }
                    $ids[] = $evidence->id;
                }

                return app(GuidedWizardDraftService::class)->saveDraft(
                    $assignment, $actor, $contextKey, $value, $notes,
                    array_values(array_unique($ids)), $expectedVersion
                );
            });
        } catch (Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }
    }
}
