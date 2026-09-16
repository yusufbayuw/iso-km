<?php

namespace App\Filament\Pages;

use App\Models\Control;
use App\Models\ControlMapping;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementMapping;
use App\Services\CrossStandardCoverageService;
use App\Services\CrossStandardMappingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CrossStandardWorkspace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Cross-Standard';
    protected static ?string $navigationLabel = 'Coverage Workspace';
    protected static ?string $title = 'Cross-Standard Coverage Workspace';
    protected static ?string $slug = 'cross-standard-workspace';
    protected static ?int $navigationSort = 209;
    protected static string $view = 'filament.pages.cross-standard-workspace';

    public ?int $managementSystemId = null;

    public function mount(): void
    {
        $requested = request()->integer('managementSystemId');
        $this->managementSystemId = ManagementSystem::query()->when($requested > 0, fn ($query) => $query->whereKey($requested))->value('id')
            ?? ManagementSystem::query()->orderBy('code')->value('id');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('cross_standard_workspace.view') ?? false;
    }

    public function acceptRequirementReuse(int $evidenceId, int $targetRequirementId, int $mappingId): void
    {
        $system = ManagementSystem::query()->findOrFail($this->managementSystemId);
        app(CrossStandardMappingService::class)->acceptRequirementReuse(
            Evidence::query()->findOrFail($evidenceId),
            $system,
            Requirement::query()->findOrFail($targetRequirementId),
            RequirementMapping::query()->with(['requirementA','requirementB'])->findOrFail($mappingId),
            auth()->user(),
        );
        Notification::make()->success()->title('Evidence reuse diterima')->send();
    }

    public function acceptControlReuse(int $evidenceId, int $targetControlId, int $mappingId): void
    {
        $system = ManagementSystem::query()->findOrFail($this->managementSystemId);
        app(CrossStandardMappingService::class)->acceptControlReuse(
            Evidence::query()->findOrFail($evidenceId),
            $system,
            Control::query()->findOrFail($targetControlId),
            ControlMapping::query()->with(['controlA','controlB'])->findOrFail($mappingId),
            auth()->user(),
        );
        Notification::make()->success()->title('Evidence reuse diterima')->send();
    }

    protected function getViewData(): array
    {
        $systems = ManagementSystem::query()->with(['standard','scope'])->orderBy('code')->get();
        $coverage = null;
        if ($this->managementSystemId) {
            $system = ManagementSystem::query()->find($this->managementSystemId);
            if ($system) { $coverage = app(CrossStandardCoverageService::class)->build($system); }
        }
        return compact('systems', 'coverage');
    }
}
