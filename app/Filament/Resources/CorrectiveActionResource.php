<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorrectiveActionResource\Pages;
use App\Models\CorrectiveAction;
use App\Services\CorrectiveActionWorkflowService;
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
            Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','in_progress' => 'In Progress','verification' => 'Verification','completed' => 'Completed'])->required()->default('open')
                ->disabled(fn (?CorrectiveAction $record): bool => $record?->status === 'completed')
                ->helperText('Penyelesaian dan verifikasi otomatis dicatat dari aksi tabel.'),
            Forms\Components\Placeholder::make('verification_summary')->label('Riwayat Verifikasi')
                ->content(fn (?CorrectiveAction $record): string => $record?->verified_at
                    ? ($record->verifier?->name ?? 'Petugas').' - '.$record->verified_at->format('d/m/Y H:i').' - '.($record->effectiveness_status ?? '-')
                    : 'Belum diverifikasi')
                ->columnSpanFull(),
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
        ])->actions([
            Tables\Actions\Action::make('submit_verification')->label('Selesai, Minta Verifikasi')
                ->icon('heroicon-o-check-circle')->color('primary')->requiresConfirmation()
                ->visible(fn (CorrectiveAction $record): bool => in_array($record->status, ['open', 'in_progress'], true)
                    && auth()->check() && ((int) $record->owner_id === (int) auth()->id() || (auth()->user()?->can('capa.manage') ?? false)))
                ->action(fn (CorrectiveAction $record) => app(CorrectiveActionWorkflowService::class)->submitForVerification($record, auth()->user())),
            Tables\Actions\Action::make('verify_effectiveness')->label('Verifikasi Efektivitas')
                ->icon('heroicon-o-shield-check')->color('success')
                ->visible(fn (CorrectiveAction $record): bool => $record->status === 'verification'
                    && (auth()->user()?->can('capa.verify') ?? false)
                    && ($record->owner_id === null || (int) $record->owner_id !== (int) auth()->id()))
                ->form([
                    Forms\Components\Select::make('outcome')->label('Hasil')
                        ->options(['effective' => 'Efektif', 'ineffective' => 'Perlu perbaikan ulang'])->required(),
                    Forms\Components\Textarea::make('notes')->label('Hasil pemeriksaan')->required(),
                ])
                ->action(fn (CorrectiveAction $record, array $data) => app(CorrectiveActionWorkflowService::class)
                    ->verify($record, auth()->user(), $data['outcome'] === 'effective', $data['notes'])),
            Tables\Actions\EditAction::make()->visible(fn (CorrectiveAction $record): bool => $record->status !== 'completed'),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageCorrectiveActions::route('/')]; }
}
