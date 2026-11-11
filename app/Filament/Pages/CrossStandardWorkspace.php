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
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class CrossStandardWorkspace extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Cross-Standard';
    protected static ?string $navigationLabel = 'Coverage Workspace';
    protected static ?string $title = 'Cross-Standard Coverage Workspace';
    protected static ?string $slug = 'cross-standard-workspace';
    protected static ?int $navigationSort = 209;
    protected static string $view = 'filament.pages.cross-standard-workspace';

    public ?int $managementSystemId = null;
    public ?array $managementSystemData = [];

    protected function getForms(): array { return ['managementSystemForm']; }

    public function managementSystemForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id')
                ->label('Target Management System')
                ->options(fn (): array => $this->systemOptions())
                ->getSearchResultsUsing(fn (string $search): array => $this->systemOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->systemLabel($value))
                ->searchable()->preload()->native(false)->live()
                ->afterStateUpdated(fn ($state) => $this->managementSystemId = filled($state) ? (int) $state : null),
        ])->statePath('managementSystemData');
    }

    private function systemOptions(?string $search = null): array
    {
        return ManagementSystem::query()->with('standard')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) =>
                $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhereHas('standard', fn (Builder $standard) => $standard->where('code', 'like', '%'.$search.'%'))))
            ->orderBy('code')->limit(50)->get()
            ->mapWithKeys(fn (ManagementSystem $system) => [$system->id => $this->formatSystemLabel($system)])->all();
    }

    private function systemLabel($value): ?string
    {
        $system = ManagementSystem::query()->with('standard')->find($value);
        return $system ? $this->formatSystemLabel($system) : null;
    }

    private function formatSystemLabel(ManagementSystem $system): string
    {
        return $system->code.' — '.($system->standard?->code ?? '-').' — '.$system->name;
    }

    public function mount(): void
    {
        $requested = request()->integer('managementSystemId');
        $this->managementSystemId = ManagementSystem::query()->when($requested > 0, fn ($query) => $query->whereKey($requested))->value('id')
            ?? ManagementSystem::query()->orderBy('code')->value('id');
        $this->managementSystemForm->fill(['id' => $this->managementSystemId]);
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
        $coverage = null;
        if ($this->managementSystemId) {
            $system = ManagementSystem::query()->find($this->managementSystemId);
            if ($system) { $coverage = app(CrossStandardCoverageService::class)->build($system); }
        }
        return compact('coverage');
    }
}
