<?php

namespace App\Filament\Pages;

use App\Models\ManagementSystem;
use App\Services\AuditPackService;
use App\Services\TraceabilityMatrixService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class AuditorWorkspace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $navigationLabel = 'Auditor Workspace';
    protected static ?string $title = 'Auditor Workspace';
    protected static ?string $slug = 'auditor-workspace';
    protected static ?int $navigationSort = 105;
    protected static string $view = 'filament.pages.auditor-workspace';

    public ?int $managementSystemId = null;

    public function mount(): void
    {
        $requested = request()->integer('managementSystemId');
        $this->managementSystemId = ManagementSystem::query()
            ->when($requested > 0, fn ($query) => $query->whereKey($requested))
            ->value('id') ?? ManagementSystem::query()->orderBy('code')->value('id');
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
        $systems = ManagementSystem::query()->orderBy('code')->get(['id', 'code', 'name']);
        $matrix = null;

        if ($this->managementSystemId !== null) {
            $system = ManagementSystem::query()->find($this->managementSystemId);
            if ($system) {
                $matrix = app(TraceabilityMatrixService::class)->build($system);
            }
        }

        return compact('systems', 'matrix');
    }
}
