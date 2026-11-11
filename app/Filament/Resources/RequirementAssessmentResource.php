<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequirementAssessmentResource\Pages;
use App\Models\RequirementAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use App\Models\ManagementSystem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementAssessmentResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = RequirementAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Implementasi & Kepatuhan';
    protected static ?string $modelLabel = 'Assessment Requirement';
    protected static ?string $pluralModelLabel = 'Readiness Assessment';
    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name', modifyQueryUsing: fn ($query) => app(\App\Services\ModelScopeAuthorizationService::class)->scopeAvailableSystems($query, auth()->user()))->live()->required()->searchable()->preload(),
            Forms\Components\Select::make('requirement_id')->label('Requirement')->relationship('requirement', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->required()->searchable()->preload(),
            Forms\Components\Select::make('status')->label('Status')->options(['not_assessed' => 'Belum Dinilai','nonconformant' => 'Tidak Sesuai','partial' => 'Sebagian Sesuai','conformant' => 'Sesuai','not_applicable' => 'Tidak Berlaku'])->required(),
            Forms\Components\Select::make('owner_id')->label('PIC')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\DateTimePicker::make('last_assessed_at')->label('Terakhir Dinilai')->seconds(false),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Textarea::make('notes')->label('Catatan / Gap')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('requirement.code')->label('Kode')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('requirement.title')->label('Requirement')->wrap()->searchable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => match ($state) {'conformant' => 'success','partial' => 'warning','nonconformant' => 'danger','not_applicable' => 'gray',default => 'info'}),
            Tables\Columns\TextColumn::make('owner.name')->label('PIC')->placeholder('-'),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageRequirementAssessments::route('/')]; }
}
