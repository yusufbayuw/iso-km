<?php

namespace Tests\Feature;

use App\Filament\Resources\GuidedQuestionResource\Pages\ManageGuidedQuestions;
use App\Models\Control;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedQuestionFormSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_editor_resolves_bank_and_control_relationship_select_queries(): void
    {
        config()->set('iso-km.guided_assessment_enabled', true);

        $user = User::create([
            'name' => 'Bank Editor',
            'email' => 'bank-editor@example.test',
            'password' => 'password',
        ]);
        foreach (['portal.access', 'guided.bank.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo(['portal.access', 'guided.bank.manage']);
        $this->actingAs($user);

        $standard = Standard::create([
            'code' => 'FORM-TEST',
            'name' => 'Form Test Standard',
            'edition' => '1',
        ]);
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id,
            'code' => 'FORM-BANK',
            'name' => 'Form Test Bank',
        ]);
        $control = Control::create([
            'standard_id' => $standard->id,
            'code' => 'FORM-CONTROL',
            'title' => 'Form Test Control',
            'is_group' => false,
        ]);
        $question = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id,
            'code' => 'FORM-Q',
            'prompt' => 'Does the control work?',
            'answer_type' => 'single_choice',
            'answer_options' => ['yes', 'no'],
        ]);
        GuidedQuestionTarget::create([
            'guided_question_id' => $question->id,
            'control_id' => $control->id,
            'criterion' => 'Working control is evidenced.',
        ]);

        Livewire::test(ManageGuidedQuestions::class)
            ->mountTableAction('edit', $question)
            ->assertSee('Bank Pertanyaan')
            ->assertSee('Kontrol')
            ->assertSee('Form Test Control');
    }
}
