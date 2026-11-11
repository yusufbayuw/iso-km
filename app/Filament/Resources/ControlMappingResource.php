<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ControlMappingResource\Pages;
use App\Models\Control;
use App\Models\ControlMapping;
use App\Services\CrossStandardMappingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ControlMappingResource extends Resource
{
    protected static ?string $model = ControlMapping::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Cross-Standard';
    protected static ?string $navigationLabel = 'Control Mapping';
    protected static ?int $navigationSort = 211;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('control_a_id')->label('Control A')->options(fn (): array => self::controlOptions())->getSearchResultsUsing(fn (string $search): array => self::controlOptions($search))->getOptionLabelUsing(fn ($value): ?string => self::selectedControlLabel($value))->searchable()->preload()->required()->disabled(fn (?ControlMapping $record): bool => $record?->status === 'approved'),
            Forms\Components\Select::make('control_b_id')->label('Control B')->options(fn (): array => self::controlOptions())->getSearchResultsUsing(fn (string $search): array => self::controlOptions($search))->getOptionLabelUsing(fn ($value): ?string => self::selectedControlLabel($value))->searchable()->preload()->required()->disabled(fn (?ControlMapping $record): bool => $record?->status === 'approved'),
            Forms\Components\Select::make('mapping_type')->label('Mapping')->options(['equivalent' => 'Equivalent','partial' => 'Partial','supporting' => 'Supporting','related' => 'Related'])->required()->default('related'),
            Forms\Components\TextInput::make('coverage_percent')->label('Coverage (%)')->numeric()->minValue(1)->maxValue(100)->required()->default(50),
            Forms\Components\Placeholder::make('status_display')->label('Status')->content(fn (?ControlMapping $record): string => $record?->status ?? 'draft'),
            Forms\Components\Textarea::make('rationale')->label('Rationale / Batas Reuse')->columnSpanFull(),
        ])->columns(2);
    }

    private static function controlOptions(?string $search = null): array
    {
        return Control::query()->with('standard')->assessable()
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) =>
                $query->where('code', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('standard', fn (Builder $standard) => $standard->where('code', 'like', '%'.$search.'%'))))
            ->orderBy('standard_id')->orderBy('code')->limit(50)->get()
            ->mapWithKeys(fn (Control $item) => [$item->id => self::controlLabel($item)])->all();
    }

    private static function selectedControlLabel($value): ?string
    {
        $item = Control::query()->with('standard')->assessable()->find($value);
        return $item ? self::controlLabel($item) : null;
    }

    private static function controlLabel(Control $item): string
    {
        return trim(($item->standard?->code ?? '').' '.($item->standard?->edition ?? '')).' · '.$item->code.' — '.$item->title;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('controlA.standard.code')->label('Standard A'),
            Tables\Columns\TextColumn::make('controlA.code')->label('Control A')->searchable(),
            Tables\Columns\TextColumn::make('controlB.standard.code')->label('Standard B'),
            Tables\Columns\TextColumn::make('controlB.code')->label('Control B')->searchable(),
            Tables\Columns\TextColumn::make('mapping_type')->label('Mapping')->badge(),
            Tables\Columns\TextColumn::make('coverage_percent')->label('Coverage')->suffix('%'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (ControlMapping $record): bool => $record->status !== 'approved'),
            Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                ->visible(fn (ControlMapping $record): bool => in_array($record->status, ['draft','rejected'], true) && (auth()->user()?->can('cross_standard_mapping.approve') ?? false))
                ->action(function (ControlMapping $record): void {
                    app(CrossStandardMappingService::class)->approveControl($record, auth()->user());
                    Notification::make()->success()->title('Control mapping approved')->send();
                }),
            Tables\Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (ControlMapping $record): bool => in_array($record->status, ['draft','approved'], true) && (auth()->user()?->can('cross_standard_mapping.approve') ?? false))
                ->action(function (ControlMapping $record): void {
                    app(CrossStandardMappingService::class)->rejectControl($record, auth()->user());
                    Notification::make()->warning()->title('Control mapping rejected')->send();
                }),
            Tables\Actions\DeleteAction::make()->visible(fn (ControlMapping $record): bool => $record->status === 'draft'),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageControlMappings::route('/')]; }
}
