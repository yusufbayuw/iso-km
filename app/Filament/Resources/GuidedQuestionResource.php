<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedQuestionResource\Pages;
use App\Models\GuidedQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedQuestionResource extends Resource
{
    protected static ?string $model = GuidedQuestion::class;
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Pertanyaan & Kriteria';
    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool { return config('iso-km.guided_assessment_enabled', false) && (auth()->user()?->can('guided.bank.manage') ?? false); }
    public static function canCreate(): bool { return self::canViewAny(); }
    public static function canEdit($record): bool { return self::canViewAny() && $record->bank?->status === 'draft'; }
    public static function canDelete($record): bool { return self::canEdit($record); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('guided_question_bank_id')->label('Bank Pertanyaan')->relationship('bank', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('status', 'draft'))->required()->searchable()->preload()->disabledOn('edit'),
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(100)->disabledOn('edit'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Forms\Components\Toggle::make('is_required')->label('Wajib')->default(true),
            Forms\Components\Textarea::make('prompt')->label('Pertanyaan')->required()->columnSpanFull(),
            Forms\Components\Textarea::make('help_text')->label('Petunjuk')->columnSpanFull(),
            Forms\Components\Select::make('answer_type')->label('Tipe Jawaban')
                ->options(['single_choice' => 'Pilihan Tunggal', 'text' => 'Narasi', 'number' => 'Angka', 'date' => 'Tanggal'])->default('single_choice')->required(),
            Forms\Components\TagsInput::make('answer_options')->label('Pilihan Jawaban')->helperText('Wajib untuk tipe pilihan tunggal.')->columnSpanFull(),
            Forms\Components\Repeater::make('branch_rules')->label('Percabangan')->schema([
                Forms\Components\TextInput::make('when')->label('Jika jawaban bernilai')->required(),
                Forms\Components\TextInput::make('next_code')->label('Lanjut ke kode pertanyaan')->required(),
            ])->columnSpanFull(),
            Forms\Components\Repeater::make('targets')->label('Kriteria Penilaian')->relationship()->schema([
                Forms\Components\Select::make('requirement_id')->label('Requirement')->relationship('requirement', 'title')->searchable()->preload(),
                Forms\Components\Select::make('control_id')->label('Kontrol')->relationship('control', 'title', modifyQueryUsing: fn (Builder $query) => $query->where('is_group', false))->searchable()->preload(),
                Forms\Components\Textarea::make('criterion')->label('Yang harus dibuktikan')->required()->columnSpanFull(),
                Forms\Components\Select::make('evidence_profile')->label('Profil Evidence')->options(['basic' => 'Dasar','standard' => 'Ditingkatkan','critical' => 'Kritis'])->default('standard')->required(),
                Forms\Components\TagsInput::make('evidence_hints')->label('Contoh Evidence')->columnSpanFull(),
                Forms\Components\Toggle::make('is_required')->label('Kriteria Wajib')->default(true),
            ])->columns(2)->defaultItems(0)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('bank.name')->label('Bank')->searchable(),
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('prompt')->label('Pertanyaan')->wrap()->limit(90)->searchable(),
            Tables\Columns\TextColumn::make('answer_type')->label('Tipe')->badge(),
            Tables\Columns\TextColumn::make('targets_count')->label('Kriteria')->counts('targets'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageGuidedQuestions::route('/')]; }
}
