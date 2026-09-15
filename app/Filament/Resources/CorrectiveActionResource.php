<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorrectiveActionResource\Pages;
use App\Models\CorrectiveAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CorrectiveActionResource extends Resource
{
    protected static ?string $model = CorrectiveAction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $modelLabel = 'Corrective Action';
    protected static ?string $pluralModelLabel = 'CAPA Tracker';
    protected static ?int $navigationSort = 130;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('audit_finding_id')->label('Finding')->relationship('finding', 'title')->required()->searchable()->preload(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Textarea::make('action')->label('Corrective Action')->required()->columnSpanFull(),
            Forms\Components\DatePicker::make('due_date')->label('Due Date')->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','in_progress' => 'In Progress','verification' => 'Verification','completed' => 'Completed'])->required()->default('open'),
            Forms\Components\DateTimePicker::make('completed_at')->label('Completed At')->seconds(false),
            Forms\Components\Select::make('verified_by')->label('Verified By')->relationship('verifier', 'name')->searchable()->preload(),
            Forms\Components\DateTimePicker::make('verified_at')->label('Verified At')->seconds(false),
            Forms\Components\Textarea::make('verification_notes')->label('Verification Notes')->columnSpanFull(),
            Forms\Components\Select::make('effectiveness_status')->label('Efektivitas')->options(['pending' => 'Pending','effective' => 'Effective','ineffective' => 'Ineffective'])->required()->default('pending'),
            Forms\Components\Textarea::make('effectiveness_notes')->label('Effectiveness Notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('finding.code')->label('Finding')->searchable(),
            Tables\Columns\TextColumn::make('action')->label('Action')->limit(80)->wrap(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('due_date')->label('Due')->date('d/m/Y')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('effectiveness_status')->label('Efektivitas')->badge(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageCorrectiveActions::route('/')]; }
}
