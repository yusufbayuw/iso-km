<?php

namespace App\Filament\Pages;

use App\Models\ExternalAuditAccess;
use App\Models\ManagementSystem;
use App\Services\ExternalAuditPackService;
use App\Services\TraceabilityMatrixService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ExternalAuditorWorkspace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $navigationLabel = 'External Audit Workspace';
    protected static ?string $title = 'External Auditor Workspace';
    protected static ?string $slug = 'external-auditor-workspace';
    protected static ?int $navigationSort = 107;
    protected static string $view = 'filament.pages.external-auditor-workspace';

    public ?int $managementSystemId = null;

    public function mount(): void
    {
        $systems = $this->availableSystems();
        $requested = request()->integer('managementSystemId');
        $this->managementSystemId = $systems->firstWhere('id', $requested)?->id ?? $systems->first()?->id;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('external_auditor_workspace.view') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('externalAuditPack')
                ->label('Download Read-Only Audit Pack')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(function (): bool {
                    if (! $this->managementSystemId || ! (auth()->user()?->can('external_audit_pack.export') ?? false)) { return false; }
                    if (auth()->user()?->can('external_audit_access.manage')) { return true; }
                    return (bool) $this->currentAccess()?->allow_audit_pack;
                })
                ->action(function (ExternalAuditPackService $service) {
                    $system = $this->resolveSystem();
                    $path = $service->generate($system);
                    return response()->download($path, basename($path))->deleteFileAfterSend(true);
                }),
        ];
    }

    protected function getViewData(): array
    {
        $systems = $this->availableSystems();
        $matrix = null;
        $access = null;
        if ($this->managementSystemId) {
            $system = $this->resolveSystem();
            $matrix = app(TraceabilityMatrixService::class)->build($system);
            $access = $this->currentAccess();
        }
        return compact('systems', 'matrix', 'access');
    }

    private function availableSystems(): Collection
    {
        $user = auth()->user();
        if (! $user) { return collect(); }
        if ($user->can('external_audit_access.manage')) {
            return ManagementSystem::query()->with(['standard','scope'])->orderBy('code')->get();
        }
        return ExternalAuditAccess::query()->currentlyActive()->where('user_id', $user->id)
            ->with('managementSystem.standard')->get()->pluck('managementSystem')->filter()->unique('id')->sortBy('code')->values();
    }

    private function currentAccess(): ?ExternalAuditAccess
    {
        if (! $this->managementSystemId || auth()->user()?->can('external_audit_access.manage')) { return null; }
        return ExternalAuditAccess::query()->currentlyActive()
            ->where('user_id', auth()->id())->where('management_system_id', $this->managementSystemId)->latest('id')->first();
    }

    private function resolveSystem(): ManagementSystem
    {
        $system = $this->availableSystems()->firstWhere('id', $this->managementSystemId);
        abort_unless($system, 403);
        return ManagementSystem::query()->with(['standard','scope','assuranceSnapshot'])->findOrFail($system->id);
    }
}
