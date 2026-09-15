<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Notifications\IsoDueReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IsoReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_reminder_is_sent_once_for_same_threshold(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        Notification::fake();

        $owner = User::factory()->create();
        Document::create([
            'code' => 'POL-001',
            'title' => 'Kebijakan Keamanan Informasi',
            'owner_id' => $owner->id,
            'status' => 'effective',
            'next_review_at' => today()->addDays(7),
        ]);

        $this->artisan('iso:send-reminders')->assertSuccessful();
        $this->artisan('iso:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($owner, IsoDueReminder::class, 1);
        $this->assertDatabaseCount('reminder_dispatches', 1);
    }
}
