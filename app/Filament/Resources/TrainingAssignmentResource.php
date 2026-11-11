<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingAssignmentResource\Pages;
use App\Models\TrainingAssignment;
use App\Services\ActionItemSyncService;
use App\Services\TrainingAssignmentWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainingAssignmentResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = TrainingAssignment::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'People & Competence';
    protected static ?string $navigationLabel = 'Training Assignments';
    protected static ?string $modelLabel = 'Training Assignment';
    protected static ?string $pluralModelLabel = 'Training Assignments';
    protected static ?int $navigationSort = 175;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('training_program_id')->label('Program')->relationship('program', 'title')->required()->searchable()->preload(),
            Forms\Components\Select::make('user_id')->label('Peserta')->relationship('user', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('cycle_key')->label('Siklus')->required()->default(fn () => now()->format('Y-m')),
            Forms\Components\DatePicker::make('assigned_at')->label('Ditugaskan')->native(false)->default(today()),
            Forms\Components\DatePicker::make('due_at')->label('Jatuh Tempo')->native(false),
            Forms\Components\Placeholder::make('workflow_status')->label('Status')->content(fn (?TrainingAssignment $record): string => $record?->status ?? 'assigned'),
            Forms\Components\Placeholder::make('workflow_result')->label('Hasil')->content(fn (?TrainingAssignment $record): string => $record?->result ?? 'pending'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            Tables\Columns\TextColumn::make('program.code')->label('Program')->searchable(),
            Tables\Columns\TextColumn::make('program.title')->label('Judul')->wrap()->searchable(),
            Tables\Columns\TextColumn::make('user.name')->label('Peserta')->searchable(),
            Tables\Columns\TextColumn::make('cycle_key')->label('Siklus')->badge(),
            Tables\Columns\TextColumn::make('due_at')->label('Due')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('submitted_at')->label('Submit')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('result')->label('Hasil')->badge(),
            Tables\Columns\TextColumn::make('score')->label('Nilai')->placeholder('-'),
            Tables\Columns\TextColumn::make('verifier.name')->label('Verifier')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (TrainingAssignment $record): bool => $record->status === 'assigned'),
            Tables\Actions\Action::make('verify')
                ->label('Verify')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(fn (TrainingAssignment $record): bool => $record->status === 'submitted' && (auth()->user()?->can('training.verify') ?? false))
                ->form([
                    Forms\Components\Select::make('result')->label('Hasil')->options(['pass' => 'Pass','attended' => 'Attended','fail' => 'Fail','exempt' => 'Exempt'])->required(),
                    Forms\Components\TextInput::make('score')->label('Nilai')->numeric()->minValue(0)->maxValue(100),
                    Forms\Components\Textarea::make('notes')->label('Catatan Verifikasi'),
                ])
                ->action(function (TrainingAssignment $record, array $data): void {
                    app(TrainingAssignmentWorkflowService::class)->verify($record, auth()->user(), $data['result'], isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null, $data['notes'] ?? null);
                    app(ActionItemSyncService::class)->sync();
                    Notification::make()->success()->title('Training assignment diverifikasi')->send();
                }),
            Tables\Actions\Action::make('reject')
                ->label('Kembalikan')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (TrainingAssignment $record): bool => $record->status === 'submitted' && (auth()->user()?->can('training.verify') ?? false))
                ->form([Forms\Components\Textarea::make('notes')->label('Alasan')->required()])
                ->action(function (TrainingAssignment $record, array $data): void {
                    app(TrainingAssignmentWorkflowService::class)->reject($record, auth()->user(), $data['notes']);
                    app(ActionItemSyncService::class)->sync();
                    Notification::make()->warning()->title('Submission dikembalikan')->send();
                }),
            Tables\Actions\DeleteAction::make()->visible(fn (TrainingAssignment $record): bool => $record->status === 'assigned'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['assigned' => 'Assigned','in_progress' => 'In Progress','submitted' => 'Submitted','completed' => 'Completed','failed' => 'Failed','rejected' => 'Rejected','exempt' => 'Exempt']),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTrainingAssignments::route('/')];
    }
}
