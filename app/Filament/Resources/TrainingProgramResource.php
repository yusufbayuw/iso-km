<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Models\TrainingProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainingProgramResource extends Resource
{
    protected static ?string $model = TrainingProgram::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'People & Competence';
    protected static ?string $modelLabel = 'Program Training';
    protected static ?string $pluralModelLabel = 'Training & Awareness';
    protected static ?int $navigationSort = 170;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode')->required(),
            Forms\Components\TextInput::make('title')->label('Program')->required()->columnSpanFull(),
            Forms\Components\Select::make('category')->label('Kategori')->options(['awareness' => 'Awareness','training' => 'Training','certification' => 'Certification','exercise' => 'Exercise / Drill','onboarding' => 'Onboarding'])->required()->default('awareness'),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('target_audience')->label('Target Peserta'),
            Forms\Components\TextInput::make('delivery_method')->label('Metode'),
            Forms\Components\Toggle::make('mandatory')->label('Wajib')->default(true),
            Forms\Components\TextInput::make('recurrence_months')->label('Ulang Setiap (bulan)')->numeric()->minValue(1),
            Forms\Components\TextInput::make('validity_months')->label('Masa Berlaku Kompetensi (bulan)')->numeric()->minValue(1),
            Forms\Components\Select::make('status')->label('Status')->options(['draft' => 'Draft','active' => 'Aktif','paused' => 'Paused','retired' => 'Retired'])->required()->default('active'),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi / Tujuan Pembelajaran')->columnSpanFull(),
            Forms\Components\Repeater::make('assignments')->label('Assignment & Completion')->relationship()->schema([
                Forms\Components\Select::make('user_id')->label('Peserta')->relationship('user', 'name')->required()->searchable()->preload(),
                Forms\Components\TextInput::make('cycle_key')->label('Siklus')->required()->default(fn () => now()->format('Y-m')),
                Forms\Components\DatePicker::make('assigned_at')->label('Ditugaskan')->native(false)->default(today()),
                Forms\Components\DatePicker::make('due_at')->label('Jatuh Tempo')->native(false),
                Forms\Components\Select::make('status')->label('Status')->options(['assigned' => 'Assigned','in_progress' => 'In Progress','completed' => 'Completed','passed' => 'Passed','failed' => 'Failed','exempt' => 'Exempt'])->required()->default('assigned'),
                Forms\Components\Select::make('result')->label('Hasil')->options(['pending' => 'Pending','pass' => 'Pass','fail' => 'Fail','attended' => 'Attended','not_applicable' => 'N/A'])->required()->default('pending'),
                Forms\Components\DateTimePicker::make('completed_at')->label('Selesai')->native(false)->seconds(false),
                Forms\Components\DatePicker::make('valid_until')->label('Valid Sampai')->native(false),
                Forms\Components\TextInput::make('score')->label('Nilai')->numeric()->minValue(0)->maxValue(100),
                Forms\Components\Select::make('evidence_id')->label('Evidence')->relationship('evidence', 'title')->searchable()->preload(),
                Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ])->columns(2)->columnSpanFull()->collapsible()->itemLabel(fn (array $state): ?string => $state['cycle_key'] ?? 'Assignment'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Program')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('category')->label('Kategori')->badge(),
            Tables\Columns\IconColumn::make('mandatory')->label('Wajib')->boolean(),
            Tables\Columns\TextColumn::make('assignments_count')->label('Assignment')->counts('assignments'),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageTrainingPrograms::route('/')]; }
}
