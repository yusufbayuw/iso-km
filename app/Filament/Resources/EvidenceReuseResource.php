<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceReuseResource\Pages;
use App\Models\EvidenceReuse;
use App\Services\CrossStandardMappingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceReuseResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = EvidenceReuse::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Cross-Standard';
    protected static ?string $navigationLabel = 'Evidence Reuse';
    protected static ?int $navigationSort = 212;

    public static function canCreate(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('evidence')->label('Evidence')->content(fn (?EvidenceReuse $record): string => $record?->evidence?->title ?? '-'),
            Forms\Components\Placeholder::make('source_system')->label('Source System')->content(fn (?EvidenceReuse $record): string => $record?->evidence?->managementSystem?->code ?? '-'),
            Forms\Components\Placeholder::make('target_system')->label('Target System')->content(fn (?EvidenceReuse $record): string => $record?->targetManagementSystem?->code ?? '-'),
            Forms\Components\Placeholder::make('target')->label('Target')->content(fn (?EvidenceReuse $record): string => $record?->targetRequirement?->code ?? $record?->targetControl?->code ?? '-'),
            Forms\Components\Placeholder::make('status_display')->label('Status')->content(fn (?EvidenceReuse $record): string => $record?->status ?? '-'),
            Forms\Components\Textarea::make('notes')->label('Notes')->disabled()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('evidence.title')->label('Evidence')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('evidence.managementSystem.code')->label('Source'),
            Tables\Columns\TextColumn::make('targetManagementSystem.code')->label('Target System'),
            Tables\Columns\TextColumn::make('targetRequirement.code')->label('Requirement')->placeholder('-'),
            Tables\Columns\TextColumn::make('targetControl.code')->label('Control')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('-'),
            Tables\Columns\TextColumn::make('reviewed_at')->label('Reviewed')->dateTime('d/m/Y H:i')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('reject')->label('Reject Reuse')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (EvidenceReuse $record): bool => $record->status === 'accepted' && (auth()->user()?->can('cross_standard_reuse.accept') ?? false))
                ->form([Forms\Components\Textarea::make('notes')->label('Alasan')->required()])
                ->action(function (EvidenceReuse $record, array $data): void {
                    app(CrossStandardMappingService::class)->rejectReuse($record, auth()->user(), $data['notes']);
                    Notification::make()->warning()->title('Evidence reuse ditolak')->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageEvidenceReuses::route('/')]; }
}
