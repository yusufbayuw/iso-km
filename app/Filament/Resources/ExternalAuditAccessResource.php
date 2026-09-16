<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalAuditAccessResource\Pages;
use App\Models\ExternalAuditAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExternalAuditAccessResource extends Resource
{
    protected static ?string $model = ExternalAuditAccess::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $navigationLabel = 'External Auditor Access';
    protected static ?string $modelLabel = 'External Audit Access';
    protected static ?string $pluralModelLabel = 'External Auditor Access';
    protected static ?int $navigationSort = 106;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('Auditor')->relationship('user', 'name')->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} — {$record->email}")->searchable(['name','email'])->preload()->required()->disabled(fn (?ExternalAuditAccess $record): bool => $record !== null),
            Forms\Components\Select::make('management_system_id')->label('Management System')->relationship('managementSystem', 'name')->searchable()->preload()->required()->disabled(fn (?ExternalAuditAccess $record): bool => $record !== null),
            Forms\Components\DateTimePicker::make('valid_from')->label('Berlaku Mulai')->native(false)->seconds(false)->default(now()),
            Forms\Components\DateTimePicker::make('expires_at')->label('Berakhir')->native(false)->seconds(false)->required(),
            Forms\Components\Toggle::make('allow_audit_pack')->label('Izinkan External Auditor Pack')->default(false),
            Forms\Components\Placeholder::make('access_state')->label('State')->content(fn (?ExternalAuditAccess $record): string => $record?->access_state ?? 'active'),
            Forms\Components\Textarea::make('notes')->label('Catatan / Tujuan Akses')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Auditor')->searchable(),
            Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('access_state')->label('Akses')->badge(),
            Tables\Columns\TextColumn::make('valid_from')->label('Mulai')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('expires_at')->label('Berakhir')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\IconColumn::make('allow_audit_pack')->label('Pack')->boolean(),
            Tables\Columns\TextColumn::make('grantor.name')->label('Granted By')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (ExternalAuditAccess $record): bool => $record->access_state !== 'revoked'),
            Tables\Actions\Action::make('revoke')->label('Revoke')->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                ->visible(fn (ExternalAuditAccess $record): bool => $record->access_state !== 'revoked' && (auth()->user()?->can('external_audit_access.manage') ?? false))
                ->action(function (ExternalAuditAccess $record): void {
                    $record->update(['status' => 'revoked', 'revoked_by' => auth()->id(), 'revoked_at' => now()]);
                    Notification::make()->success()->title('Akses auditor dicabut')->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageExternalAuditAccesses::route('/')]; }
}
