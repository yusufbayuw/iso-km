<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedDecisionResource\Pages;
use App\Models\Evidence;
use App\Models\GuidedDecision;
use App\Services\GuidedAssessmentWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedDecisionResource extends Resource
{
    protected static ?string $model = GuidedDecision::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Review & Persetujuan';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canAny(['guided.assessment.review', 'guided.evidence.review', 'guided.risk.approve', 'guided.management.approve', 'guided.assessment.manage']) ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('consolidation.assessment.memberships', fn ($q) => $q->where('user_id', auth()->id())
                ->whereIn('role', ['reviewer', 'assessor', 'risk_owner', 'management', 'coordinator']))
            ->with(['consolidation.assessment', 'consolidation.question', 'target', 'riskOwner']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('consolidation.assessment.name')->label('Asesmen'),
            Tables\Columns\TextColumn::make('consolidation.question.code')->label('Pertanyaan'),
            Tables\Columns\TextColumn::make('target.criterion')->label('Kriteria')->wrap()->limit(80),
            Tables\Columns\TextColumn::make('proposed_status')->label('Usulan')->badge(),
            Tables\Columns\TextColumn::make('risk_score')->label('Risiko')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Review')->badge(),
            Tables\Columns\TextColumn::make('synced_at')->label('Publikasi')->dateTime('d/m/Y H:i')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('evidence')->label('Nilai Evidence')->icon('heroicon-o-document-magnifying-glass')
                ->visible(fn (GuidedDecision $record) => auth()->user()?->can('guided.evidence.review')
                    && $record->consolidation->assessment->isMember(auth()->user(), ['reviewer', 'assessor'])
                    && in_array($record->status, ['submitted', 'evidence_review'], true))
                ->form([
                    Forms\Components\Select::make('evidence_id')->label('Bukti dari jawaban sumber')->required()->searchable()
                        ->options(function (GuidedDecision $record): array {
                            $ids = $record->consolidation->sources()->with('evidences')->get()
                                ->flatMap(fn ($answer) => $answer->evidences->pluck('id'))->unique()->all();
                            return Evidence::query()->whereIn('id', $ids)
                                ->where('management_system_id', $record->consolidation->assessment->management_system_id)
                                ->pluck('title', 'id')->all();
                        }),
                    Forms\Components\Select::make('outcome')->label('Hasil')->options(['accepted' => 'Diterima untuk kriteria ini', 'rejected' => 'Tidak Memadai'])->required(),
                    Forms\Components\Textarea::make('reason')->label('Alasan')->required(),
                ])->action(function (GuidedDecision $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->acceptEvidence($record, Evidence::findOrFail($data['evidence_id']),
                        auth()->user(), $data['outcome'] === 'accepted', $data['reason']);
                    Notification::make()->success()->title('Hasil pemeriksaan bukti dicatat')->send();
                }),
            Tables\Actions\Action::make('approve')->label('Putuskan')->icon('heroicon-o-check-badge')
                ->visible(fn (GuidedDecision $record) => in_array($record->status, ['submitted', 'in_review'], true)
                    && $record->consolidation->is_current
                    && $record->consolidation->assessment->isMember(auth()->user(), ['assessor', 'risk_owner', 'management']))
                ->form([
                    Forms\Components\Select::make('outcome')->label('Keputusan')->options(['approved' => 'Setujui','rejected' => 'Kembalikan untuk revisi'])->required(),
                    Forms\Components\Textarea::make('notes')->label('Alasan Keputusan')->required(),
                ])->action(function (GuidedDecision $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->approve($record, auth()->user(), $data['notes'], $data['outcome'] === 'approved');
                    Notification::make()->success()->title('Keputusan dicatat')->send();
                }),
            Tables\Actions\Action::make('publish')->label('Publikasikan')->icon('heroicon-o-arrow-up-tray')->requiresConfirmation()
                ->visible(fn (GuidedDecision $record) => $record->status === 'approved' && ! $record->synced_at
                    && $record->consolidation->assessment->isMember(auth()->user(), ['coordinator'])
                    && (auth()->user()?->can('guided.assessment.manage') ?? false))
                ->action(function (GuidedDecision $record): void {
                    app(GuidedAssessmentWorkflowService::class)->publish($record, auth()->user());
                    Notification::make()->success()->title('Keputusan dipublikasikan ke register ISO-KM')->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ListGuidedDecisions::route('/')]; }
}
