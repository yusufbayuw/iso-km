<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequirementMappingResource\Pages;
use App\Models\Requirement;
use App\Models\RequirementMapping;
use App\Services\CrossStandardMappingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementMappingResource extends Resource
{
    protected static ?string $model = RequirementMapping::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Cross-Standard';
    protected static ?string $navigationLabel = 'Requirement Mapping';
    protected static ?int $navigationSort = 210;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('requirement_a_id')->label('Requirement A')->options(fn (): array => self::requirementOptions())->getSearchResultsUsing(fn (string $search): array => self::requirementOptions($search))->getOptionLabelUsing(fn ($value): ?string => self::selectedRequirementLabel($value))->searchable()->preload()->required()->disabled(fn (?RequirementMapping $record): bool => $record?->status === 'approved'),
            Forms\Components\Select::make('requirement_b_id')->label('Requirement B')->options(fn (): array => self::requirementOptions())->getSearchResultsUsing(fn (string $search): array => self::requirementOptions($search))->getOptionLabelUsing(fn ($value): ?string => self::selectedRequirementLabel($value))->searchable()->preload()->required()->disabled(fn (?RequirementMapping $record): bool => $record?->status === 'approved'),
            Forms\Components\Select::make('mapping_type')->label('Mapping')->options(['equivalent' => 'Equivalent','partial' => 'Partial','supporting' => 'Supporting','related' => 'Related'])->required()->default('related'),
            Forms\Components\TextInput::make('coverage_percent')->label('Coverage (%)')->numeric()->minValue(1)->maxValue(100)->required()->default(50),
            Forms\Components\Placeholder::make('status_display')->label('Status')->content(fn (?RequirementMapping $record): string => $record?->status ?? 'draft'),
            Forms\Components\Textarea::make('rationale')->label('Rationale / Batas Reuse')->columnSpanFull(),
        ])->columns(2);
    }

    private static function requirementOptions(?string $search = null): array
    {
        return Requirement::query()->with('standard')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) =>
                $query->where('code', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('standard', fn (Builder $standard) => $standard->where('code', 'like', '%'.$search.'%'))))
            ->orderBy('standard_id')->orderBy('code')->limit(50)->get()
            ->mapWithKeys(fn (Requirement $item) => [$item->id => self::requirementLabel($item)])->all();
    }

    private static function selectedRequirementLabel($value): ?string
    {
        $item = Requirement::query()->with('standard')->find($value);
        return $item ? self::requirementLabel($item) : null;
    }

    private static function requirementLabel(Requirement $item): string
    {
        return trim(($item->standard?->code ?? '').' '.($item->standard?->edition ?? '')).' · '.$item->code.' — '.$item->title;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('requirementA.standard.code')->label('Standard A'),
            Tables\Columns\TextColumn::make('requirementA.code')->label('Req A')->searchable(),
            Tables\Columns\TextColumn::make('requirementB.standard.code')->label('Standard B'),
            Tables\Columns\TextColumn::make('requirementB.code')->label('Req B')->searchable(),
            Tables\Columns\TextColumn::make('mapping_type')->label('Mapping')->badge(),
            Tables\Columns\TextColumn::make('coverage_percent')->label('Coverage')->suffix('%'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (RequirementMapping $record): bool => $record->status !== 'approved'),
            Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                ->visible(fn (RequirementMapping $record): bool => in_array($record->status, ['draft','rejected'], true) && (auth()->user()?->can('cross_standard_mapping.approve') ?? false))
                ->action(function (RequirementMapping $record): void {
                    app(CrossStandardMappingService::class)->approveRequirement($record, auth()->user());
                    Notification::make()->success()->title('Requirement mapping approved')->send();
                }),
            Tables\Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (RequirementMapping $record): bool => in_array($record->status, ['draft','approved'], true) && (auth()->user()?->can('cross_standard_mapping.approve') ?? false))
                ->action(function (RequirementMapping $record): void {
                    app(CrossStandardMappingService::class)->rejectRequirement($record, auth()->user());
                    Notification::make()->warning()->title('Requirement mapping rejected')->send();
                }),
            Tables\Actions\DeleteAction::make()->visible(fn (RequirementMapping $record): bool => $record->status === 'draft'),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageRequirementMappings::route('/')]; }
}
