<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedScopeItemResource\Pages;
use App\Models\GuidedAssessment;
use App\Models\GuidedScopeItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedScopeItemResource extends Resource
{
    protected static ?string $model = GuidedScopeItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Inventaris Konteks';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool { return auth()->user()?->can('guided.assessment.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->can('guided.assessment.manage') ?? false; }
    public static function canEdit($record): bool
    {
        return self::canCreate() && ! $record->assessment?->locked_at
            && $record->assessment?->isMember(auth()->user(), ['coordinator']);
    }
    public static function canDelete($record): bool { return self::canEdit($record); }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('assessment.memberships', fn ($q) =>
            $q->where('user_id', auth()->id())->where('role', 'coordinator'))->with('assessment');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('guided_assessment_id')->label('Periode Asesmen')->required()
                ->options(fn () => GuidedAssessment::query()->whereNull('locked_at')
                    ->whereHas('memberships', fn ($q) => $q->where('user_id', auth()->id())->where('role', 'coordinator'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->disabledOn('edit'),
            Forms\Components\Select::make('kind')->label('Jenis Data')->required()
                ->options(['asset' => 'Aset Informasi', 'activity' => 'Aktivitas Informasi']),
            Forms\Components\TextInput::make('category')->label('Kelompok/Kategori')->maxLength(255),
            Forms\Components\TextInput::make('title')->label('Nama Aset atau Aktivitas')->maxLength(255)->required(),
            Forms\Components\Select::make('in_scope')->label('Dikelola / Dilaksanakan')
                ->options(['1' => 'Ya', '0' => 'Tidak'])->nullable()
                ->helperText('Kosong berarti belum diketahui; jangan menebak.'),
            Forms\Components\Select::make('classification')->label('Klasifikasi')->options([
                'public' => 'Publik', 'internal' => 'Internal',
                'confidential' => 'Rahasia', 'restricted' => 'Terbatas',
            ]),
            Forms\Components\TagsInput::make('related_controls')->label('Kontrol Terkait')->helperText('Isi kode kontrol, mis. A.8.13, jika sudah diketahui.'),
            Forms\Components\Textarea::make('notes')->label('Catatan dan Dasar Informasi')->columnSpanFull(),
            Forms\Components\Hidden::make('created_by')->default(fn () => auth()->id()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('assessment.name')->label('Periode')->searchable(),
            Tables\Columns\TextColumn::make('kind')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('category')->label('Kelompok')->limit(35),
            Tables\Columns\TextColumn::make('title')->label('Aset/Aktivitas')->searchable()->wrap(),
            Tables\Columns\IconColumn::make('in_scope')->label('Dalam Lingkup')->boolean(),
            Tables\Columns\TextColumn::make('classification')->label('Klasifikasi')->badge(),
        ])->filters([
            Tables\Filters\SelectFilter::make('kind')->options(['asset' => 'Aset Informasi', 'activity' => 'Aktivitas Informasi']),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageGuidedScopeItems::route('/')]; }
}
