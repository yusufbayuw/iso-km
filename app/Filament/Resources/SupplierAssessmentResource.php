<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierAssessmentResource\Pages;
use App\Models\SupplierAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierAssessmentResource extends Resource
{
    protected static ?string $model = SupplierAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Third Parties';
    protected static ?string $modelLabel = 'Assessment Supplier';
    protected static ?string $pluralModelLabel = 'Supplier Assessments';
    protected static ?int $navigationSort = 200;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('assessed_by')->label('Assessor')->relationship('assessor', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('assessed_at')->label('Tanggal Assessment')->native(false),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\TextInput::make('score')->label('Score')->numeric()->minValue(0)->maxValue(100),
            Forms\Components\Select::make('risk_rating')->label('Risk Rating')->options(['low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical'])->required()->default('medium'),
            Forms\Components\Select::make('status')->label('Status')->options(['planned' => 'Planned','in_progress' => 'In Progress','accepted' => 'Accepted','conditional' => 'Conditional','rejected' => 'Rejected','closed' => 'Closed'])->required()->default('planned'),
            Forms\Components\Select::make('evidence_id')->label('Evidence Assessment')->relationship('evidence', 'title')->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('summary')->label('Ringkasan Assessment')->columnSpanFull(),
            Forms\Components\Textarea::make('required_actions')->label('Tindak Lanjut yang Diperlukan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('supplier.code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('assessed_at')->label('Assessment')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('score')->label('Score')->placeholder('-'),
            Tables\Columns\TextColumn::make('risk_rating')->label('Risk')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-')->sortable(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageSupplierAssessments::route('/')]; }
}
