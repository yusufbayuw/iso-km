<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_revision_follows_controlled_workflow_and_replaces_previous_effective_revision(): void
    {
        $user = User::factory()->create();

        foreach ([
            'document.submit_review', 'document.review', 'document.review_any',
            'document.approve', 'document.approve_any', 'document.make_effective',
            'document.obsolete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        $document = Document::create([
            'code' => 'SOP-001',
            'title' => 'SOP Pengendalian Dokumen',
            'owner_id' => $user->id,
            'review_frequency_months' => 12,
        ]);

        $old = DocumentRevision::create([
            'document_id' => $document->id,
            'revision' => '00',
            'status' => 'effective',
            'prepared_by' => $user->id,
            'reviewer_id' => $user->id,
            'approver_id' => $user->id,
            'is_current' => true,
            'effective_at' => today()->subMonth(),
        ]);

        $revision = DocumentRevision::create([
            'document_id' => $document->id,
            'revision' => '01',
            'prepared_by' => $user->id,
            'reviewer_id' => $user->id,
            'approver_id' => $user->id,
        ]);

        $service = app(DocumentWorkflowService::class);
        $service->submitForReview($revision, $user);
        $this->assertSame('in_review', $revision->refresh()->status);

        $service->review($revision, $user, 'Sesuai untuk approval.');
        $this->assertSame('reviewed', $revision->refresh()->status);

        $service->approve($revision, $user);
        $this->assertSame('approved', $revision->refresh()->status);

        $service->makeEffective($revision, $user);

        $this->assertSame('effective', $revision->refresh()->status);
        $this->assertTrue($revision->is_current);
        $this->assertSame('obsolete', $old->refresh()->status);
        $this->assertFalse($old->is_current);
        $this->assertSame('effective', $document->refresh()->status);
        $this->assertNotNull($document->next_review_at);
        $this->assertSame(1, DocumentRevision::query()->where('document_id', $document->id)->where('is_current', true)->count());
        $this->assertTrue(ActivityLog::query()->where('subject_type', $revision->getMorphClass())->where('subject_id', $revision->id)->exists());
    }
}
