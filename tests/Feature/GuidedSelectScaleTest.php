<?php

namespace Tests\Feature;

use App\Filament\Resources\GuidedAssessmentResource;
use App\Filament\Resources\GuidedConsolidationResource;
use App\Models\GuidedAssessment;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class GuidedSelectScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_user_selects_preload_only_fifty_but_search_later_entries(): void
    {
        $last = null;
        for ($i = 1; $i <= 60; $i++) {
            $suffix = sprintf('%03d', $i);
            $last = User::create([
                'name' => 'Candidate '.$suffix,
                'email' => 'candidate'.$suffix.'@example.test',
                'password' => 'password',
            ]);
        }

        foreach ([
            new ReflectionMethod(GuidedAssessmentResource::class, 'userOptions'),
            new ReflectionMethod(GuidedConsolidationResource::class, 'riskOwnerOptions'),
        ] as $options) {
            $initial = $options->invoke(null);
            $this->assertCount(50, $initial);
            $this->assertArrayNotHasKey($last->id, $initial);
            $this->assertSame('Candidate 060', $options->invoke(null, 'Candidate 060')[$last->id]);
        }
    }

    public function test_question_selector_searches_beyond_first_fifty_within_its_bank(): void
    {
        $standard = Standard::create(['code' => 'FORM-STD', 'name' => 'Form standard', 'edition' => '1']);
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id,
            'code' => 'FORM-BANK',
            'name' => 'Form bank',
        ]);

        $last = null;
        for ($i = 1; $i <= 60; $i++) {
            $suffix = sprintf('%03d', $i);
            $last = GuidedQuestion::create([
                'guided_question_bank_id' => $bank->id,
                'code' => 'Q-'.$suffix,
                'prompt' => 'Question '.$suffix,
                'sort_order' => $i,
            ]);
        }

        // The option query only needs the bank relation, not a saved assessment.
        $assessment = new GuidedAssessment();
        $assessment->setRelation('bank', $bank);

        $options = new ReflectionMethod(GuidedAssessmentResource::class, 'questionOptions');
        $initial = $options->invoke(null, $assessment);
        $this->assertCount(50, $initial);
        $this->assertArrayNotHasKey($last->id, $initial);
        $this->assertSame('Question 060', $options->invoke(null, $assessment, 'Q-060')[$last->id]);
    }
}
