<?php

namespace Tests\Feature;

use App\Filament\Resources\ControlMappingResource;
use App\Filament\Resources\RequirementMappingResource;
use App\Models\Control;
use App\Models\Requirement;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SelectOptionPagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_selects_preload_50_but_search_entire_catalog_and_keep_selected_labels(): void
    {
        $standard = Standard::create([
            'code' => 'CATALOG',
            'name' => 'Test catalog',
            'edition' => '1',
        ]);

        $lastControl = null;
        $lastRequirement = null;

        for ($n = 1; $n <= 60; $n++) {
            $code = sprintf('%03d', $n);
            $lastControl = Control::create([
                'standard_id' => $standard->id,
                'code' => 'C'.$code,
                'title' => 'Control '.$code,
                'is_group' => false,
            ]);
            $lastRequirement = Requirement::create([
                'standard_id' => $standard->id,
                'code' => 'R'.$code,
                'title' => 'Requirement '.$code,
            ]);
        }

        $controlOptions = new ReflectionMethod(ControlMappingResource::class, 'controlOptions');
        $requirementOptions = new ReflectionMethod(RequirementMappingResource::class, 'requirementOptions');

        $initialControls = $controlOptions->invoke(null);
        $initialRequirements = $requirementOptions->invoke(null);

        $this->assertCount(50, $initialControls);
        $this->assertCount(50, $initialRequirements);
        $this->assertArrayNotHasKey($lastControl->id, $initialControls);
        $this->assertArrayNotHasKey($lastRequirement->id, $initialRequirements);

        $this->assertArrayHasKey($lastControl->id, $controlOptions->invoke(null, 'C060'));
        $this->assertArrayHasKey($lastRequirement->id, $requirementOptions->invoke(null, 'R060'));

        $selectedControl = new ReflectionMethod(ControlMappingResource::class, 'selectedControlLabel');
        $selectedRequirement = new ReflectionMethod(RequirementMappingResource::class, 'selectedRequirementLabel');

        $this->assertStringContainsString('C060', $selectedControl->invoke(null, $lastControl->id));
        $this->assertStringContainsString('R060', $selectedRequirement->invoke(null, $lastRequirement->id));
    }
}
