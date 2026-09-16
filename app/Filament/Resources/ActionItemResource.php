<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActionItemResource\Pages;
use App\Models\ActionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActionItemResource extends Resource
{
    protected static ?string $model = ActionItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationGroup = 'Pekerjaan Saya';
    protected static ?string $navigationLabel = 'My Work';
    protected static ?string $modelLabel = 'Action Item';
    protected static ?string $pluralModelLabel = 'My Work';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['owner', 'managementSystem']);
        $user = auth()->user();
        if (! $user?->can('action_item.view_all')) {
            $query->where('owner_id', $user?->id);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->searchable()->preload(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('title')->label('Pekerjaan')->required()->columnSpanFull(),
            Forms\Components\Select::make('category')->options(self::categoryOptions())->default('manual')->required(),
            Forms\Components\Select::make('priority')->options(['low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical'])->default('medium')->required(),
            Forms\Components\DateTimePicker::make('due_at')->label('Due')->native(false),
            Forms\Components\Select::make('status')->options(['open' => 'Open','in_progress' => 'In Progress','completed' => 'Completed'])->default('open')->required(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            Tables\Columns\TextColumn::make('priority')->label('Prioritas')->badge()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Pekerjaan')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('category')->label('Kategori')->badge(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->placeholder('-'),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('due_at')->label('Due')->dateTime('d/m/Y H:i')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->filters([
            Tables\Filters\SelectFilter::make('priority')->options(['low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical']),
            Tables\Filters\SelectFilter::make('category')->options(self::categoryOptions()),
            Tables\Filters\Filter::make('overdue')->query(fn (Builder $query): Builder => $query->where('status', '!=', 'completed')->whereNotNull('due_at')->where('due_at', '<', now())),
        ])->actions([
            Tables\Actions\Action::make('complete')->label('Selesai')->icon('heroicon-o-check-circle')->visible(fn (ActionItem $record): bool => ! $record->is_generated && $record->status !== 'completed')->requiresConfirmation()->action(fn (ActionItem $record) => $record->update(['status' => 'completed', 'completed_at' => now()])),
            Tables\Actions\EditAction::make()->visible(fn (ActionItem $record): bool => ! $record->is_generated),
            Tables\Actions\DeleteAction::make()->visible(fn (ActionItem $record): bool => ! $record->is_generated),
        ]);
    }

    private static function categoryOptions(): array
    {
        return [
            'manual' => 'Manual',
            'evidence_collection' => 'Evidence',
            'document_review' => 'Review Dokumen',
            'document_acknowledgement' => 'Acknowledgement Dokumen',
            'requirement_gap' => 'Gap Requirement',
            'control_implementation' => 'Implementasi Control',
            'risk_treatment' => 'Risk Treatment',
            'audit_preparation' => 'Audit',
            'capa' => 'CAPA',
            'objective_review' => 'Objective',
            'compliance_review' => 'Compliance',
            'management_review' => 'Management Review',
            'management_review_action' => 'Management Review Action',
            'training_assignment' => 'Training',
            'training_verification' => 'Training Verification',
            'competency_renewal' => 'Competence Renewal',
            'supplier_review' => 'Supplier Review',
            'supplier_assessment_execution' => 'Supplier Assessment',
            'supplier_assessment_review' => 'Supplier Assessment Review',
            'supplier_assessment' => 'Supplier Reassessment',
            'supplier_treatment' => 'Supplier Treatment',
        ];
    }

    public static function getPages(): array { return ['index' => Pages\ManageActionItems::route('/')]; }
}
