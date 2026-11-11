<?php

namespace App\Filament\Pages;

use App\Models\ManagementSystem;
use App\Services\AuditPackService;
use App\Services\TraceabilityMatrixService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class AuditorWorkspace extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $navigationLabel = 'Auditor Workspace';
    protected static ?string $title = 'Auditor Workspace';
    protected static ?string $slug = 'auditor-workspace';
    protected static ?int $navigationSort = 105;
    protected static string $view = 'filament.pages.auditor-workspace';

    public ?int $managementSystemId = null;
    public ?array $managementSystemData = [];

    protected function getForms(): array { return ['managementSystemForm']; }

    public function managementSystemForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id')
                ->label('Sistem Manajemen')
                ->options(fn (): array => $this->systemOptions())
                ->getSearchResultsUsing(fn (string $search): array => $this->systemOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->systemLabel($value))
                ->searchable()->preload()->native(false)->live()
                ->afterStateUpdated(fn ($state) => $this->managementSystemId = filled($state) ? (int) $state : null),
        ])->statePath('managementSystemData');
    }

    private function systemOptions(?string $search = null): array
    {
        return ManagementSystem::query()
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) =>
                $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->orderBy('code')->limit(50)->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (ManagementSystem $system) => [$system->id => $system->code.' — '.$system->name])->all();
    }

    private function systemLabel($value): ?string
    {
        $system = ManagementSystem::query()->find($value);
        return $system ? $system->code.' — '.$system->name : null;
    }

    public function mount(): void
    {
        $requested = request()->integer('managementSystemId');
        $this->managementSystemId = ManagementSystem::query()
            ->when($requested > 0, fn ($query) => $query->whereKey($requested))
            ->value('id') ?? ManagementSystem::query()->orderBy('code')->value('id');
        $this->managementSystemForm->fill(['id' => $this->managementSystemId]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('auditor_workspace.view') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('auditPack')
                ->label('Download Auditor Pack')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->managementSystemId !== null && (auth()->user()?->can('audit_pack.export') ?? false))
                ->action(function (AuditPackService $service) {
                    $system = ManagementSystem::query()->findOrFail($this->managementSystemId);
                    $path = $service->generate($system);

                    return response()->download($path, basename($path))->deleteFileAfterSend(true);
                }),
        ];
    }

    protected function getViewData(): array
    {
        $matrix = null;

        if ($this->managementSystemId !== null) {
            $system = ManagementSystem::query()->find($this->managementSystemId);
            if ($system) {
                $matrix = app(TraceabilityMatrixService::class)->build($system);
            }
        }

        return compact('matrix');
    }
}
