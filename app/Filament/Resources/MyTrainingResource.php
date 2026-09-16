<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyTrainingResource\Pages;
use App\Models\Evidence;
use App\Models\TrainingAssignment;
use App\Services\ActionItemSyncService;
use App\Services\TrainingAssignmentWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTrainingResource extends Resource
{
    protected static ?string $model = TrainingAssignment::class;
    protected static ?string $slug = 'my-training';
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Pekerjaan Saya';
    protected static ?string $navigationLabel = 'Training Saya';
    protected static ?string $modelLabel = 'Training Saya';
    protected static ?string $pluralModelLabel = 'Training Saya';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('training.complete') ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['program.managementSystem', 'evidence', 'verifier']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            Tables\Columns\TextColumn::make('program.code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('program.title')->label('Program')->wrap()->searchable(),
            Tables\Columns\TextColumn::make('program.managementSystem.code')->label('Sistem')->placeholder('-'),
            Tables\Columns\IconColumn::make('program.mandatory')->label('Wajib')->boolean(),
            Tables\Columns\TextColumn::make('cycle_key')->label('Siklus')->badge(),
            Tables\Columns\TextColumn::make('due_at')->label('Jatuh Tempo')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('result')->label('Hasil')->badge(),
            Tables\Columns\TextColumn::make('valid_until')->label('Valid Sampai')->date('d/m/Y')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('start')
                ->label('Mulai')
                ->icon('heroicon-o-play')
                ->visible(fn (TrainingAssignment $record): bool => in_array($record->status, ['assigned', 'failed', 'rejected'], true))
                ->requiresConfirmation()
                ->action(function (TrainingAssignment $record): void {
                    app(TrainingAssignmentWorkflowService::class)->start($record, auth()->user());
                    app(ActionItemSyncService::class)->sync();
                    Notification::make()->success()->title('Training dimulai')->send();
                }),
            Tables\Actions\Action::make('submit')
                ->label('Submit')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (TrainingAssignment $record): bool => in_array($record->status, ['assigned', 'in_progress', 'failed', 'rejected'], true))
                ->form([
                    Forms\Components\FileUpload::make('proof_file')->label('Bukti / Sertifikat')->directory('iso/training')->downloadable()->openable(),
                    Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                ])
                ->action(function (TrainingAssignment $record, array $data): void {
                    $evidence = null;
                    if (! empty($data['proof_file'])) {
                        $program = $record->program;
                        $evidence = Evidence::query()->create([
                            'management_system_id' => $program->management_system_id,
                            'title' => 'Training - '.$program->code.' - '.auth()->user()->name.' - '.$record->cycle_key,
                            'type' => 'training_record',
                            'source' => 'employee_self_service',
                            'confidentiality' => 'internal',
                            'description' => $data['notes'] ?? null,
                            'file_path' => $data['proof_file'],
                            'owner_id' => auth()->id(),
                            'captured_at' => today(),
                            'valid_from' => today(),
                            'status' => 'active',
                            'verification_status' => 'pending',
                        ]);
                        $evidence->requirements()->sync($program->requirements()->pluck('requirements.id'));
                        $evidence->controls()->sync($program->controls()->pluck('controls.id'));
                    }

                    app(TrainingAssignmentWorkflowService::class)->submit($record, auth()->user(), $evidence, $data['notes'] ?? null);
                    app(ActionItemSyncService::class)->sync();
                    Notification::make()->success()->title('Training dikirim untuk verifikasi')->send();
                }),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'assigned' => 'Assigned','in_progress' => 'In Progress','submitted' => 'Submitted',
                'completed' => 'Completed','failed' => 'Failed','rejected' => 'Rejected','exempt' => 'Exempt',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMyTraining::route('/')];
    }
}
