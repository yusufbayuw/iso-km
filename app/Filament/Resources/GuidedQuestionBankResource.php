<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedQuestionBankResource\Pages;
use App\Models\GuidedQuestionBank;
use App\Services\GuidedQuestionBankService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class GuidedQuestionBankResource extends Resource
{
    protected static ?string $model = GuidedQuestionBank::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Bank Pertanyaan';
    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool { return config('iso-km.guided_assessment_enabled', false) && (auth()->user()?->can('guided.bank.manage') ?? false); }
    public static function canCreate(): bool { return self::canViewAny(); }
    public static function canEdit($record): bool { return self::canViewAny() && $record->status === 'draft'; }
    public static function canDelete($record): bool { return self::canEdit($record) && ! $record->questions()->exists(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('standard_id')->label('Standar')->relationship('standard', 'name')->required()->searchable()->preload()->disabledOn('edit'),
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(100)->disabledOn('edit'),
            Forms\Components\TextInput::make('name')->label('Nama Bank Pertanyaan')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('version')->label('Versi')->numeric()->minValue(1)->default(1)->required()->disabledOn('edit'),
            Forms\Components\Hidden::make('created_by')->default(fn () => auth()->id()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('standard.code')->label('Standar'),
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('version')->label('Versi'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('questions_count')->label('Pertanyaan')->counts('questions'),
        ])->actions([
            Tables\Actions\Action::make('generate')->label('Buat Pertanyaan Awal')->icon('heroicon-o-sparkles')
                ->tooltip('Menambahkan pertanyaan induk yang belum ada dari master standar. Tidak menduplikasi atau mengganti pertanyaan existing.')
                ->modalHeading('Lengkapi pertanyaan awal')
                ->modalDescription('Hanya pertanyaan induk yang belum ada yang ditambahkan. Jawaban, pertanyaan, dan penyesuaian yang sudah ada tidak dihapus. Jika bank sudah lengkap, tidak ada pertanyaan baru.')
                ->requiresConfirmation()->visible(fn (GuidedQuestionBank $record) => $record->status === 'draft')
                ->action(function (GuidedQuestionBank $record): void {
                    $before = $record->questions()->count();
                    app(GuidedQuestionBankService::class)->generateStarterQuestions($record, auth()->user());
                    $added = max(0, $record->questions()->count() - $before);
                    Notification::make()->success()
                        ->title($added ? $added.' pertanyaan baru ditambahkan' : 'Tidak ada pertanyaan baru')
                        ->body('Jumlah pertanyaan sekarang: '.$record->questions()->count().'. Periksa isi dan Coverage sebelum diterbitkan.')
                        ->send();
                }),
            Tables\Actions\Action::make('coverage')->label('Coverage')->icon('heroicon-o-chart-bar')
                ->tooltip('Memeriksa cakupan klausul, kontrol, duplikasi, dan percabangan; tidak mengubah bank.')
                ->modalHeading('Pemeriksaan Cakupan Bank')
                ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
                ->modalContent(fn (GuidedQuestionBank $record) => view('filament.guided.coverage', ['coverage' => app(GuidedQuestionBankService::class)->coverage($record)])),
            Tables\Actions\Action::make('publish')->label('Terbitkan')->color('success')
                ->tooltip('Terbitkan bank yang sudah diperiksa agar bisa dipilih saat memulai asesmen.')
                ->modalHeading('Terbitkan Bank Pertanyaan')
                ->modalDescription('Pastikan seluruh isi pertanyaan sudah ditinjau dan Coverage lengkap. Setelah diterbitkan, isi bank tidak dapat diedit langsung; perubahan berikutnya memerlukan versi baru.')
                ->requiresConfirmation()
                ->visible(fn (GuidedQuestionBank $record) => $record->status === 'draft')
                ->action(function (GuidedQuestionBank $record): void {
                    try {
                        app(GuidedQuestionBankService::class)->publish($record, auth()->user());
                    } catch (ValidationException $exception) {
                        $message = (string) (collect($exception->errors())->flatten()->first()
                            ?? 'Bank belum memenuhi persyaratan untuk diterbitkan.');
                        Notification::make()->danger()
                            ->title('Bank belum dapat diterbitkan')
                            ->body($message)
                            ->persistent()
                            ->send();
                        return;
                    }
                    Notification::make()->success()
                        ->title('Bank pertanyaan diterbitkan')
                        ->body('Bank dapat digunakan untuk membuat asesmen baru. Untuk perubahan isi, buat versi bank berikutnya.')
                        ->send();
                }),
            Tables\Actions\EditAction::make()->label('Ubah Info Bank')
                ->tooltip('Ubah nama dan informasi bank. Isi pertanyaan dikelola dari menu Pertanyaan & Kriteria.'),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageGuidedQuestionBanks::route('/')]; }
}
