<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StandardResource\Pages;
use App\Models\Standard;
use App\Services\GuidedStandardCatalogImportService;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StandardResource extends Resource
{
    protected static ?string $model = Standard::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Fondasi ISO';
    protected static ?string $modelLabel = 'Standar';
    protected static ?string $pluralModelLabel = 'Standar';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(100),
            Forms\Components\TextInput::make('name')->label('Nama Standar')->required()->maxLength(255),
            Forms\Components\TextInput::make('edition')->label('Edisi/Tahun')->maxLength(50),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('edition')->label('Edisi')->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('requirements_count')->label('Requirement')->counts('requirements')->alignRight(),
            Tables\Columns\TextColumn::make('controls_count')->label('Control')->counts('controls')->alignRight(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('import_catalog')->label('Impor Katalog XLSX')->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (Standard $record) => config('iso-km.guided_assessment_enabled', false)
                    && (auth()->user()?->can('guided.bank.manage') ?? false)
                    && (auth()->user()?->can('update_standard') ?? false))
                ->form([
                    Forms\Components\FileUpload::make('file')->label('XLSX Katalog Standar')->required()
                        ->disk('local')->directory('iso/guided/catalog-imports')->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(20480)
                        ->helperText('Sheet klausul dan kontrol harus memiliki kolom Kode dan Judul. Katalog harus merupakan sumber yang sah dan tidak memuat data organisasi contoh.'),
                    Forms\Components\Checkbox::make('include_descriptions')->label('Impor juga uraian persyaratan/kontrol (opsional)')
                        ->helperText('Default hanya kode dan judul agar uraian khusus organisasi tidak tercampur.'),
                    Forms\Components\Checkbox::make('license_confirmed')->label('Saya berwenang menggunakan sumber katalog ini')->required()
                        ->rules(['accepted']),
                ])->action(function (Standard $record, array $data): void {
                    if (empty($data['license_confirmed'])) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['catalog' => 'Konfirmasi hak penggunaan katalog wajib diberikan.']);
                    }
                    $result = app(GuidedStandardCatalogImportService::class)->import(
                        $record, $data['file'], auth()->user(), (bool) ($data['include_descriptions'] ?? false)
                    );
                    Notification::make()->success()->title('Impor katalog selesai')->body(
                        $result['inserted']['requirements'].' klausul dan '.$result['inserted']['controls'].' kontrol baru ditambahkan; item lama tidak ditimpa.'
                    )->send();
                }),
            Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageStandards::route('/')]; }
}
